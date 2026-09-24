<?php
/**
 * subir_adjunto.php
 * Recibe el documento de alta ya firmado en físico (escaneado o fotografiado),
 * valida que el código de alta exista y guarda el archivo en el servidor.
 *
 * Entrada: POST multipart/form-data con:
 *   - codigo_alta : texto (ej. ALTA-000001)
 *   - archivo     : PDF o JPG
 *
 * Salida: JSON.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';

exigir_metodo('POST');
$sesion = exigir_sesion_json();
limitar_intentos('subir', 20, 600);

// ---------------------------------------------------------------------
// 1) Código de alta
// ---------------------------------------------------------------------
$codigo = normalizar_codigo(isset($_POST['codigo_alta']) ? $_POST['codigo_alta'] : '');

if ($codigo === '') {
    error_json('Debe ingresar el código de alta.', 400);
}
if (!codigo_valido($codigo)) {
    error_json('El código de alta no tiene un formato válido (ejemplo: ALTA-000001).', 400);
}

try {
    $alta = buscar_alta_por_codigo($codigo);
} catch (Exception $e) {
    error_log('[altas] subir_adjunto (consulta): ' . $e->getMessage());
    error_json('No fue posible consultar la base de datos.', 500);
}

if (!$alta) {
    error_json('El código de alta ingresado no corresponde a ninguna solicitud registrada.', 404);
}

// Cada establecimiento sube los documentos de sus propias altas.
if (!puede_ver_establecimiento($alta['nombre_establecimiento'])) {
    error_json('Ese código corresponde a otro establecimiento de salud.', 403);
}

// Un alta ya verificada no admite otra carga: lo que bloquea es el estado,
// no la simple existencia de un adjunto anterior. Así, cuando el panel
// devuelve un alta a «pendiente» porque el documento llegó ilegible o
// incompleto, el establecimiento puede volver a subirlo sin intervención
// técnica, y el archivo anterior se conserva como historial.
if ($alta['estado'] === 'verificado') {
    $cuando = '';
    try {
        $st = db()->prepare('SELECT created_at FROM alta_adjuntos WHERE alta_id = ? ORDER BY id DESC LIMIT 1');
        $st->execute(array((int) $alta['id']));
        $previo = $st->fetch();
        if ($previo) {
            $cuando = ' el ' . date('d/m/Y H:i', strtotime($previo['created_at']));
        }
    } catch (Exception $e) {
        error_log('[altas] subir_adjunto (previo): ' . $e->getMessage());
    }

    error_json(
        'El alta ' . $alta['codigo_alta'] . ' ya tiene un documento firmado registrado' . $cuando
        . '. Si necesita reemplazarlo, pida al administrador que devuelva el alta a pendiente.',
        409
    );
}

// ---------------------------------------------------------------------
// 2) Validación del archivo recibido
// ---------------------------------------------------------------------
if (!isset($_FILES['archivo'])) {
    // Un POST vacío suele significar que se superó post_max_size del php.ini.
    if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 0) {
        error_json('El archivo enviado supera el tamaño máximo permitido por el servidor.', 413);
    }
    error_json('Debe adjuntar el documento firmado (PDF o JPG).', 400);
}

$archivo = $_FILES['archivo'];

switch ($archivo['error']) {
    case UPLOAD_ERR_OK:
        break;
    case UPLOAD_ERR_NO_FILE:
        error_json('Debe adjuntar el documento firmado (PDF o JPG).', 400);
        break;
    case UPLOAD_ERR_INI_SIZE:
    case UPLOAD_ERR_FORM_SIZE:
        error_json('El archivo supera el tamaño máximo permitido (10 MB).', 413);
        break;
    case UPLOAD_ERR_PARTIAL:
        error_json('La carga del archivo se interrumpió. Intente nuevamente.', 400);
        break;
    default:
        error_log('[altas] subir_adjunto: error de carga ' . $archivo['error']);
        error_json('No fue posible recibir el archivo. Intente nuevamente.', 500);
}

if (!is_uploaded_file($archivo['tmp_name'])) {
    error_json('El archivo recibido no es válido.', 400);
}

if ((int) $archivo['size'] <= 0) {
    error_json('El archivo está vacío.', 400);
}

if ((int) $archivo['size'] > MAX_UPLOAD_BYTES) {
    error_json('El archivo supera el tamaño máximo permitido (10 MB).', 413);
}

// Tipo MIME REAL del contenido: no se confía en la extensión ni en el
// encabezado que envía el navegador, que pueden ser falseados.
$mime = null;
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mime = finfo_file($finfo, $archivo['tmp_name']);
        finfo_close($finfo);
    }
}
if ($mime === null && function_exists('mime_content_type')) {
    $mime = mime_content_type($archivo['tmp_name']);
}
if ($mime === 'image/jpg' || $mime === 'image/pjpeg') {
    $mime = 'image/jpeg';
}

$permitidos = $GLOBALS['MIME_PERMITIDOS'];
if (!$mime || !isset($permitidos[$mime])) {
    error_json('Solo se aceptan archivos PDF o JPG. El archivo enviado no es de un tipo permitido.', 400);
}
$extension = $permitidos[$mime];

// ---------------------------------------------------------------------
// 3) Guardado en disco con nombre seguro y único
// ---------------------------------------------------------------------
if (!is_dir(UPLOAD_DIR) && !@mkdir(UPLOAD_DIR, 0770, true) && !is_dir(UPLOAD_DIR)) {
    error_log('[altas] subir_adjunto: no se pudo crear ' . UPLOAD_DIR);
    error_json('No fue posible almacenar el archivo en el servidor.', 500);
}
if (!is_writable(UPLOAD_DIR)) {
    error_log('[altas] subir_adjunto: sin permisos de escritura en ' . UPLOAD_DIR);
    error_json('No fue posible almacenar el archivo en el servidor.', 500);
}

// El nombre en disco se construye desde cero: nunca se usa el nombre que
// envía el cliente (evita path traversal y caracteres problemáticos).
$nombreGuardado = $alta['codigo_alta'] . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
$rutaDestino    = UPLOAD_DIR . DIRECTORY_SEPARATOR . $nombreGuardado;

// El nombre original se conserva solo como dato informativo, ya saneado.
$nombreOriginal = limpiar_texto(basename(str_replace('\\', '/', (string) $archivo['name'])), 255);
$nombreOriginal = preg_replace('/[^\p{L}\p{N}\.\-_ ]+/u', '_', $nombreOriginal);
if ($nombreOriginal === '' || $nombreOriginal === null) {
    $nombreOriginal = 'documento.' . $extension;
}

if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
    error_log('[altas] subir_adjunto: fallo move_uploaded_file hacia ' . $rutaDestino);
    error_json('No fue posible almacenar el archivo en el servidor.', 500);
}
@chmod($rutaDestino, 0640);

// ---------------------------------------------------------------------
// 4) Registro en base de datos y cambio de estado
// ---------------------------------------------------------------------
try {
    $pdo = db();
    $pdo->beginTransaction();

    $st = $pdo->prepare(
        'INSERT INTO alta_adjuntos
           (alta_id, codigo_alta, nombre_archivo_original, nombre_archivo_guardado,
            ruta_archivo, tipo_mime, tamano_bytes)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $st->execute(array(
        (int) $alta['id'],
        $alta['codigo_alta'],
        $nombreOriginal,
        $nombreGuardado,
        'uploads/altas/' . $nombreGuardado,
        $mime,
        (int) $archivo['size'],
    ));
    $adjuntoId = (int) $pdo->lastInsertId();

    $pdo->prepare('UPDATE altas SET estado = "verificado" WHERE id = ?')
        ->execute(array((int) $alta['id']));

    $pdo->commit();
} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    @unlink($rutaDestino); // No dejar archivos huérfanos si falla el registro.
    error_log('[altas] subir_adjunto (registro): ' . $e->getMessage());
    error_json('No fue posible registrar el documento. Intente nuevamente.', 500);
}

responder(array(
    'ok'          => true,
    'mensaje'     => 'Documento recibido y registrado correctamente.',
    'codigo_alta' => $alta['codigo_alta'],
    'paciente'    => $alta['nombre_paciente'],
    'estado'      => 'verificado',
    'adjunto'     => array(
        'id'              => $adjuntoId,
        'nombre_original' => $nombreOriginal,
        'tipo'            => $mime,
        'tamano_bytes'    => (int) $archivo['size'],
        'recibido_en'     => date('d/m/Y H:i'),
    ),
), 201);
