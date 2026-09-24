<?php
/**
 * Autenticación del panel de administración.
 *
 * Sesiones de PHP + contraseñas con `password_hash()` (bcrypt). Aquí está
 * todo lo relativo a iniciar y cerrar sesión, proteger páginas y emitir y
 * comprobar los testigos anti-CSRF de los formularios del panel.
 *
 * Hay dos roles, y la diferencia es de alcance, no de confianza:
 *
 *   administrador  el panel completo: altas, usuarios y las acciones que
 *                  cambian el estado de un alta.
 *   operador       entra a registrar altas y a consultar el listado, y lo
 *                  hace siempre a nombre del establecimiento que tiene
 *                  asignado en su cuenta.
 *
 * El formulario de altas tambien pasa por aquí: dejó de ser público cuando
 * se creó el rol de operador, que es quien lo llena.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/redes.php';

define('ROL_ADMINISTRADOR', 'administrador');
define('ROL_OPERADOR', 'operador');

/** Los dos roles, con su nombre para mostrar. */
function roles_disponibles()
{
    return array(
        ROL_ADMINISTRADOR => 'Administrador',
        ROL_OPERADOR      => 'Operador',
    );
}

/** Nombre legible de un rol. */
function nombre_rol($rol)
{
    $roles = roles_disponibles();
    return isset($roles[$rol]) ? $roles[$rol] : $rol;
}

// ---------------------------------------------------------------------
// Sesión
// ---------------------------------------------------------------------

/**
 * Arranca la sesión con cookies endurecidas (HttpOnly, SameSite y, detrás de
 * HTTPS, Secure). Es segura de llamar varias veces.
 */
function sesion_iniciar()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    // Detrás del proxy de CapRover el TLS termina antes de llegar a PHP:
    // la cabecera X-Forwarded-Proto es lo que delata que la visita es HTTPS.
    $seguro = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params(array(
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $seguro,
        'samesite' => 'Lax',
    ));
    session_start();
}

/** Usuario de la sesión actual, o null si no hay nadie autenticado. */
function usuario_actual()
{
    sesion_iniciar();
    if (empty($_SESSION['usuario_id'])) {
        return null;
    }
    return array(
        'id'              => (int) $_SESSION['usuario_id'],
        'username'        => isset($_SESSION['username']) ? $_SESSION['username'] : '',
        'nombre_completo' => isset($_SESSION['nombre_completo']) ? $_SESSION['nombre_completo'] : '',
        'rol'             => isset($_SESSION['rol']) ? $_SESSION['rol'] : ROL_OPERADOR,
        'red_salud'       => isset($_SESSION['red_salud']) ? $_SESSION['red_salud'] : null,
        'nombre_establecimiento' => isset($_SESSION['nombre_establecimiento'])
            ? $_SESSION['nombre_establecimiento'] : null,
    );
}

/** ¿Hay una sesión abierta? */
function hay_sesion()
{
    return usuario_actual() !== null;
}

/**
 * Protege una página del panel: si no hay sesión, redirige al login
 * recordando a dónde se quería entrar.
 */
function exigir_sesion()
{
    if (hay_sesion()) {
        // La cookie de sesión sobrevive al borrado de la cuenta: sin esta
        // comprobación, un usuario eliminado o desactivado seguiría dentro
        // del panel hasta cerrar el navegador. Se comprueba una vez por
        // petición, no en cada llamada a usuario_actual().
        $yo = usuario_actual();
        try {
            $vigente = buscar_usuario($yo['id']);
        } catch (Exception $e) {
            error_log('[altas] exigir_sesion: ' . $e->getMessage());
            $vigente = null;
        }

        if ($vigente && (int) $vigente['activo'] === 1) {
            // El rol se relee de la base en cada petición: si un
            // administrador cambia el de alguien que está dentro, el
            // cambio vale desde la página siguiente y no al reingresar.
            $_SESSION['rol']                    = $vigente['rol'];
            $_SESSION['red_salud']              = $vigente['red_salud'];
            $_SESSION['nombre_establecimiento'] = $vigente['nombre_establecimiento'];

            $yo['rol']                    = $vigente['rol'];
            $yo['red_salud']              = $vigente['red_salud'];
            $yo['nombre_establecimiento'] = $vigente['nombre_establecimiento'];
            return $yo;
        }

        cerrar_sesion();
        header('Location: ' . ruta_base() . 'login.php?motivo=cuenta');
        exit;
    }

    $destino = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    header('Location: ' . ruta_base() . 'login.php?volver=' . rawurlencode($destino));
    exit;
}

/**
 * Establecimiento al que está atada la sesión, o null si no tiene ninguno.
 *
 * Devuelve array('red_salud' => ..., 'municipio' => ..., 'nombre_establecimiento' => ...).
 * Es la única fuente válida para registrar un alta: lo que llegue en el
 * formulario se descarta cuando esto no es null.
 */
function establecimiento_de_sesion()
{
    $yo = usuario_actual();
    if (!$yo || empty($yo['nombre_establecimiento']) || empty($yo['red_salud'])) {
        return null;
    }
    return array(
        'red_salud'              => $yo['red_salud'],
        'municipio'              => municipio_de_red($yo['red_salud']),
        'nombre_establecimiento' => $yo['nombre_establecimiento'],
    );
}

/**
 * ¿Esta sesión puede ver un alta de ese establecimiento?
 *
 * El administrador los abarca todos; el operador, solo el suyo. Se
 * comprueba en cada entrega de datos de un alta concreta (PDF, adjunto,
 * verificación de código), no solo al pintar el listado.
 */
function puede_ver_establecimiento($establecimiento)
{
    $mio = establecimiento_de_sesion();
    return $mio === null || $mio['nombre_establecimiento'] === $establecimiento;
}

/** ¿La sesión actual es de un administrador? */
function es_administrador()
{
    $yo = usuario_actual();
    return $yo !== null && $yo['rol'] === ROL_ADMINISTRADOR;
}

/**
 * Protege una página reservada al administrador. Un operador autenticado no
 * se queda fuera del panel: vuelve a su pantalla de altas con un aviso, que
 * es menos desconcertante que un 403 en blanco.
 */
function exigir_administrador()
{
    $yo = exigir_sesion();
    if ($yo['rol'] !== ROL_ADMINISTRADOR) {
        header('Location: ' . ruta_base() . 'admin/index.php?aviso=solo_administrador');
        exit;
    }
    return $yo;
}

/** Variante para los scripts que responden JSON: 401 en vez de redirección. */
function exigir_sesion_json()
{
    if (!hay_sesion()) {
        error_json('Su sesión expiró. Vuelva a iniciar sesión.', 401);
    }
    return usuario_actual();
}

/** Igual que exigir_administrador(), para los scripts que responden JSON. */
function exigir_administrador_json()
{
    $yo = exigir_sesion_json();
    if ($yo['rol'] !== ROL_ADMINISTRADOR) {
        error_json('Esta acción está reservada al administrador del sistema.', 403);
    }
    return $yo;
}

/**
 * Prefijo de URL del sitio, para que los enlaces funcionen igual si la
 * aplicación vive en la raíz del dominio o en un subdirectorio.
 */
function ruta_base()
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    // Este archivo está en lib/, y las páginas del panel en admin/.
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/index.php';
    $dir    = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if (substr($dir, -6) === '/admin') {
        $dir = substr($dir, 0, -6);
    }
    $base = $dir === '' ? '/' : $dir . '/';
    return $base;
}

// ---------------------------------------------------------------------
// Inicio y cierre de sesión
// ---------------------------------------------------------------------

/**
 * Comprueba las credenciales y abre la sesión.
 *
 * @return array|string El usuario si todo fue bien, o un mensaje de error.
 */
function iniciar_sesion($usuarioOCorreo, $clave)
{
    $usuarioOCorreo = trim((string) $usuarioOCorreo);
    $clave          = (string) $clave;

    if ($usuarioOCorreo === '' || $clave === '') {
        return 'Ingrese su usuario y su contraseña.';
    }

    // Cada marcador se nombra una sola vez: con sentencias preparadas nativas
    // (PDO::ATTR_EMULATE_PREPARES en false) MySQL no admite repetir el mismo
    // nombre en una consulta.
    $st = db()->prepare(
        'SELECT * FROM usuarios WHERE username = :usuario OR correo = :correo LIMIT 1'
    );
    $st->execute(array(
        ':usuario' => $usuarioOCorreo,
        ':correo'  => $usuarioOCorreo,
    ));
    $usuario = $st->fetch();

    // Mensaje único: no se revela si el usuario existe o si falló la clave.
    $generico = 'Usuario o contraseña incorrectos.';

    if (!$usuario) {
        // Se compara igual contra un hash ficticio para que el tiempo de
        // respuesta no delate qué usuarios existen.
        password_verify($clave, '$2y$10$usuarioinexistenteusuarioinexistenteusuarioinexistentexxxxx');
        return $generico;
    }

    if (!password_verify($clave, $usuario['password_hash'])) {
        return $generico;
    }

    if ((int) $usuario['activo'] !== 1) {
        return 'Su cuenta está desactivada. Consulte con el administrador.';
    }

    sesion_iniciar();
    // Contra la fijación de sesión: el identificador cambia al autenticarse.
    session_regenerate_id(true);

    $_SESSION['usuario_id']      = (int) $usuario['id'];
    $_SESSION['username']        = $usuario['username'];
    $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
    $_SESSION['rol']             = $usuario['rol'];
    $_SESSION['red_salud']              = $usuario['red_salud'];
    $_SESSION['nombre_establecimiento'] = $usuario['nombre_establecimiento'];

    db()->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?')
        ->execute(array((int) $usuario['id']));

    return $usuario;
}

/** Cierra la sesión y borra su cookie. */
function cerrar_sesion()
{
    sesion_iniciar();
    $_SESSION = array();

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ---------------------------------------------------------------------
// Registro de usuarios
// ---------------------------------------------------------------------

/** ¿Todavía no hay ningún usuario? Entonces el registro está abierto. */
function sin_usuarios()
{
    try {
        return (int) db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() === 0;
    } catch (Exception $e) {
        error_log('[altas] sin_usuarios: ' . $e->getMessage());
        return false;
    }
}

/**
 * Comprueba los datos de un usuario del panel.
 *
 * La usan tanto el registro como la edición, para que las dos pantallas
 * apliquen exactamente las mismas reglas. La contraseña es obligatoria al
 * crear y opcional al editar: en la edición, dejarla vacía significa
 * «no la cambies».
 *
 * @param array    $in            Datos del formulario.
 * @param int|null $idExcluir     Al editar, el id del propio usuario: sus
 *                                valores no cuentan como duplicados.
 * @param bool     $claveOpcional true en la edición.
 * @return array array('errores' => array) o array('datos' => array)
 */
function validar_datos_usuario(array $in, $idExcluir = null, $claveOpcional = false)
{
    $errores = array();

    $username = strtolower(limpiar_texto(isset($in['username']) ? $in['username'] : '', 60));
    if ($username === '') {
        $errores['username'] = 'Indique un nombre de usuario.';
    } elseif (!preg_match('/^[a-z0-9._-]{3,60}$/', $username)) {
        $errores['username'] = 'El usuario admite de 3 a 60 caracteres: letras, números, punto, guion y guion bajo.';
    }

    $nombre = limpiar_texto(isset($in['nombre_completo']) ? $in['nombre_completo'] : '', 160);
    if ($nombre === '') {
        $errores['nombre_completo'] = 'Indique el nombre completo.';
    }

    $ci = limpiar_texto(isset($in['ci']) ? $in['ci'] : '', 30);
    if ($ci === '') {
        $errores['ci'] = 'Indique el número de cédula de identidad.';
    }

    $telefono = limpiar_texto(isset($in['telefono']) ? $in['telefono'] : '', 30);
    if ($telefono === '') {
        $errores['telefono'] = 'Indique un teléfono de contacto.';
    } elseif (!preg_match('/^[0-9+()\s-]{6,30}$/', $telefono)) {
        $errores['telefono'] = 'El teléfono solo admite números, espacios y los signos + ( ) -';
    }

    $correo = limpiar_texto(isset($in['correo']) ? $in['correo'] : '', 160);
    if ($correo === '') {
        $errores['correo'] = 'Indique un correo electrónico.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores['correo'] = 'El correo electrónico no tiene un formato válido.';
    }

    // El rol viene de una lista cerrada. El primer usuario del sistema es
    // administrador por definición: si no lo fuera, nadie podría crear a
    // los demás ni administrar el panel.
    $rol = isset($in['rol']) ? (string) $in['rol'] : '';
    if (sin_usuarios()) {
        $rol = ROL_ADMINISTRADOR;
    } elseif (!array_key_exists($rol, roles_disponibles())) {
        $errores['rol'] = 'Seleccione el rol de la cuenta.';
    }

    // Red y establecimiento: obligatorios para el operador, porque son los
    // que el formulario va a dar por sentados; vacíos para el
    // administrador, que no está atado a ninguno.
    $red             = limpiar_texto(isset($in['red_salud']) ? $in['red_salud'] : '', 255);
    $establecimiento = limpiar_texto(isset($in['nombre_establecimiento']) ? $in['nombre_establecimiento'] : '', 255);

    if ($rol === ROL_ADMINISTRADOR) {
        $red             = null;
        $establecimiento = null;
    } else {
        if ($red === '') {
            $errores['red_salud'] = 'Seleccione la red de salud del operador.';
        } elseif (!red_valida($red)) {
            $errores['red_salud'] = 'Esa red no pertenece al catálogo del SEDES Oruro.';
        }

        if ($establecimiento === '') {
            $errores['nombre_establecimiento'] = 'Seleccione el establecimiento del operador.';
        } elseif (!isset($errores['red_salud']) && !establecimiento_de_red($red, $establecimiento)) {
            $errores['nombre_establecimiento'] = 'Ese establecimiento no corresponde a la red «' . $red . '».';
        }
    }

    $clave   = isset($in['clave']) ? (string) $in['clave'] : '';
    $repetir = isset($in['clave_repetida']) ? (string) $in['clave_repetida'] : '';

    // Al editar, una contraseña vacía significa «déjala como está».
    $cambiaClave = !$claveOpcional || $clave !== '' || $repetir !== '';

    if ($cambiaClave) {
        if (strlen($clave) < 8) {
            $errores['clave'] = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif ($clave !== $repetir) {
            $errores['clave_repetida'] = 'Las contraseñas no coinciden.';
        }
    }

    if ($errores) {
        return array('errores' => $errores);
    }

    // Duplicados: se avisa con precisión en qué campo está el conflicto.
    $sql = 'SELECT username, ci, correo FROM usuarios WHERE (username = :u OR ci = :c OR correo = :e)';
    $par = array(':u' => $username, ':c' => $ci, ':e' => $correo);
    if ($idExcluir !== null) {
        $sql .= ' AND id <> :id';
        $par[':id'] = (int) $idExcluir;
    }

    $st = db()->prepare($sql);
    $st->execute($par);

    foreach ($st->fetchAll() as $existente) {
        if ($existente['username'] === $username) {
            $errores['username'] = 'Ese nombre de usuario ya está registrado.';
        }
        if ($existente['ci'] === $ci) {
            $errores['ci'] = 'Esa cédula de identidad ya está registrada.';
        }
        if (strcasecmp($existente['correo'], $correo) === 0) {
            $errores['correo'] = 'Ese correo electrónico ya está registrado.';
        }
    }

    if ($errores) {
        return array('errores' => $errores);
    }

    return array('datos' => array(
        'username'        => $username,
        'nombre_completo' => $nombre,
        'ci'              => $ci,
        'telefono'        => $telefono,
        'correo'          => $correo,
        'rol'             => $rol,
        'red_salud'       => $red,
        'nombre_establecimiento' => $establecimiento,
        'clave'           => $cambiaClave ? $clave : null,
    ));
}

/**
 * Da de alta un usuario del panel.
 *
 * @return array array('ok' => true, 'id' => int) o array('errores' => array)
 */
function registrar_usuario(array $in)
{
    $revision = validar_datos_usuario($in);
    if (isset($revision['errores'])) {
        return array('errores' => $revision['errores']);
    }
    $d = $revision['datos'];

    try {
        $st = db()->prepare(
            'INSERT INTO usuarios
               (username, nombre_completo, ci, telefono, correo, rol,
                red_salud, nombre_establecimiento, password_hash)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute(array(
            $d['username'], $d['nombre_completo'], $d['ci'], $d['telefono'], $d['correo'], $d['rol'],
            $d['red_salud'], $d['nombre_establecimiento'],
            password_hash($d['clave'], PASSWORD_DEFAULT),
        ));
        return array('ok' => true, 'id' => (int) db()->lastInsertId());
    } catch (Exception $e) {
        error_log('[altas] registrar_usuario: ' . $e->getMessage());
        return array('errores' => array('general' => 'No fue posible registrar el usuario. Intente nuevamente.'));
    }
}

/**
 * ¿Queda algún administrador activo aparte del usuario indicado?
 *
 * Es la comprobación que impide quedarse sin nadie que administre el panel,
 * ya sea rebajando de rol al último administrador o eliminándolo.
 */
function hay_otro_administrador($idExcluido)
{
    try {
        $st = db()->prepare(
            'SELECT COUNT(*) FROM usuarios WHERE rol = ? AND activo = 1 AND id <> ?'
        );
        $st->execute(array(ROL_ADMINISTRADOR, (int) $idExcluido));
        return (int) $st->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log('[altas] hay_otro_administrador: ' . $e->getMessage());
        // Ante la duda, se responde que no: bloquear un cambio es
        // reversible; quedarse sin administrador, no.
        return false;
    }
}

/** Devuelve un usuario por su id, o null si no existe. */
function buscar_usuario($id)
{
    $st = db()->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
    $st->execute(array((int) $id));
    $fila = $st->fetch();
    return $fila ? $fila : null;
}

/**
 * Modifica un usuario existente. La contraseña solo se cambia si se envió
 * una nueva.
 *
 * @return array array('ok' => true, 'clave_cambiada' => bool) o array('errores' => array)
 */
function actualizar_usuario($id, array $in)
{
    $id = (int) $id;
    if (!buscar_usuario($id)) {
        return array('errores' => array('general' => 'El usuario ya no existe.'));
    }

    $revision = validar_datos_usuario($in, $id, true);
    if (isset($revision['errores'])) {
        return array('errores' => $revision['errores']);
    }
    $d = $revision['datos'];

    // Rebajar al último administrador dejaría el sistema sin nadie que
    // pueda crear usuarios ni administrar las altas, y sin forma de
    // deshacerlo desde el propio panel.
    if ($d['rol'] !== ROL_ADMINISTRADOR && !hay_otro_administrador($id)) {
        return array('errores' => array(
            'rol' => 'Esta es la única cuenta de administrador activa. Nombre a otro '
                   . 'administrador antes de cambiarle el rol.',
        ));
    }

    try {
        if ($d['clave'] !== null) {
            $st = db()->prepare(
                'UPDATE usuarios
                    SET username = ?, nombre_completo = ?, ci = ?, telefono = ?, correo = ?,
                        rol = ?, red_salud = ?, nombre_establecimiento = ?, password_hash = ?
                  WHERE id = ?'
            );
            $st->execute(array(
                $d['username'], $d['nombre_completo'], $d['ci'], $d['telefono'], $d['correo'], $d['rol'],
                $d['red_salud'], $d['nombre_establecimiento'],
                password_hash($d['clave'], PASSWORD_DEFAULT), $id,
            ));
        } else {
            $st = db()->prepare(
                'UPDATE usuarios
                    SET username = ?, nombre_completo = ?, ci = ?, telefono = ?, correo = ?,
                        rol = ?, red_salud = ?, nombre_establecimiento = ?
                  WHERE id = ?'
            );
            $st->execute(array(
                $d['username'], $d['nombre_completo'], $d['ci'], $d['telefono'], $d['correo'], $d['rol'],
                $d['red_salud'], $d['nombre_establecimiento'], $id,
            ));
        }

        // Si el usuario se editó a sí mismo, la sesión debe reflejarlo.
        $sesion = usuario_actual();
        if ($sesion && $sesion['id'] === $id) {
            $_SESSION['username']        = $d['username'];
            $_SESSION['nombre_completo'] = $d['nombre_completo'];
            $_SESSION['rol']                    = $d['rol'];
            $_SESSION['red_salud']              = $d['red_salud'];
            $_SESSION['nombre_establecimiento'] = $d['nombre_establecimiento'];
        }

        return array('ok' => true, 'clave_cambiada' => $d['clave'] !== null);
    } catch (Exception $e) {
        error_log('[altas] actualizar_usuario: ' . $e->getMessage());
        return array('errores' => array('general' => 'No fue posible guardar los cambios. Intente nuevamente.'));
    }
}

/**
 * Borra un usuario del panel.
 *
 * Hay dos casos que se bloquean, y no por prudencia decorativa:
 *
 *   - Borrarse a uno mismo: cerraría la sesión a mitad de la operación.
 *   - Borrar al último usuario: `registro.php` se abre al público cuando la
 *     tabla de usuarios está vacía, para poder crear el primer
 *     administrador. Dejarla en cero en un sitio ya publicado abriría el
 *     registro a cualquiera que pase por la URL.
 *
 * Es un borrado definitivo, sin papelera. Para retirar el acceso sin perder
 * el rastro de quién entró, la opción sigue siendo desactivar la cuenta.
 *
 * @return array array('ok' => true, 'username' => string) o array('error' => string)
 */
function eliminar_usuario($id)
{
    $id     = (int) $id;
    $sesion = usuario_actual();

    if ($sesion && $sesion['id'] === $id) {
        return array('error' => 'No puede eliminar su propia cuenta. Pida a otro usuario que lo haga.');
    }

    $usuario = buscar_usuario($id);
    if (!$usuario) {
        return array('error' => 'El usuario ya no existe.');
    }

    try {
        $total = (int) db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
        if ($total <= 1) {
            return array('error' => 'No se puede eliminar el único usuario del sistema: el registro '
                . 'quedaría abierto al público.');
        }

        if ($usuario['rol'] === ROL_ADMINISTRADOR && !hay_otro_administrador($id)) {
            return array('error' => 'No se puede eliminar la única cuenta de administrador: '
                . 'el panel se quedaría sin quien lo administre.');
        }

        db()->prepare('DELETE FROM usuarios WHERE id = ?')->execute(array($id));
        return array('ok' => true, 'username' => $usuario['username']);
    } catch (Exception $e) {
        error_log('[altas] eliminar_usuario: ' . $e->getMessage());
        return array('error' => 'No fue posible eliminar el usuario.');
    }
}

// ---------------------------------------------------------------------
// Testigo anti-CSRF
// ---------------------------------------------------------------------

/** Devuelve (creándolo si hace falta) el testigo de la sesión. */
function csrf_token()
{
    sesion_iniciar();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Campo oculto listo para pegar dentro de un formulario. */
function csrf_campo()
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

/** ¿El testigo recibido coincide con el de la sesión? */
function csrf_valido($recibido)
{
    sesion_iniciar();
    return !empty($_SESSION['csrf']) && is_string($recibido)
        && hash_equals($_SESSION['csrf'], $recibido);
}

// ---------------------------------------------------------------------
// Salida segura en HTML
// ---------------------------------------------------------------------

/** Escapa texto para insertarlo en HTML sin riesgo de XSS. */
function h($texto)
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
