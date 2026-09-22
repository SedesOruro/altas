<?php
/**
 * Autenticación del panel de administración.
 *
 * Sesiones de PHP + contraseñas con `password_hash()` (bcrypt). Aquí está
 * todo lo relativo a iniciar y cerrar sesión, proteger páginas y emitir y
 * comprobar los testigos anti-CSRF de los formularios del panel.
 *
 * El formulario público de altas no pasa por aquí: sigue siendo abierto.
 */

require_once __DIR__ . '/../config.php';

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

/** Variante para los scripts que responden JSON: 401 en vez de redirección. */
function exigir_sesion_json()
{
    if (!hay_sesion()) {
        error_json('Su sesión expiró. Vuelva a iniciar sesión.', 401);
    }
    return usuario_actual();
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
            'INSERT INTO usuarios (username, nombre_completo, ci, telefono, correo, password_hash)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $st->execute(array(
            $d['username'], $d['nombre_completo'], $d['ci'], $d['telefono'], $d['correo'],
            password_hash($d['clave'], PASSWORD_DEFAULT),
        ));
        return array('ok' => true, 'id' => (int) db()->lastInsertId());
    } catch (Exception $e) {
        error_log('[altas] registrar_usuario: ' . $e->getMessage());
        return array('errores' => array('general' => 'No fue posible registrar el usuario. Intente nuevamente.'));
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

    try {
        if ($d['clave'] !== null) {
            $st = db()->prepare(
                'UPDATE usuarios
                    SET username = ?, nombre_completo = ?, ci = ?, telefono = ?, correo = ?, password_hash = ?
                  WHERE id = ?'
            );
            $st->execute(array(
                $d['username'], $d['nombre_completo'], $d['ci'], $d['telefono'], $d['correo'],
                password_hash($d['clave'], PASSWORD_DEFAULT), $id,
            ));
        } else {
            $st = db()->prepare(
                'UPDATE usuarios
                    SET username = ?, nombre_completo = ?, ci = ?, telefono = ?, correo = ?
                  WHERE id = ?'
            );
            $st->execute(array(
                $d['username'], $d['nombre_completo'], $d['ci'], $d['telefono'], $d['correo'], $id,
            ));
        }

        // Si el usuario se editó a sí mismo, la sesión debe reflejarlo.
        $sesion = usuario_actual();
        if ($sesion && $sesion['id'] === $id) {
            $_SESSION['username']        = $d['username'];
            $_SESSION['nombre_completo'] = $d['nombre_completo'];
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
