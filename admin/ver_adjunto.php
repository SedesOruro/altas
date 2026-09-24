<?php
/**
 * ver_adjunto.php — entrega el documento firmado que subió el personal.
 *
 * Uso:  ver_adjunto.php?id=12            (lo muestra en el navegador)
 *       ver_adjunto.php?id=12&descargar=1 (lo descarga)
 *
 * Exige sesión: los documentos escaneados contienen datos clínicos y la
 * firma del paciente, así que no se sirven desde una URL pública. El
 * archivo se lee del disco y se envía desde aquí, nunca enlazando
 * directamente a la carpeta `uploads/`.
 *
 * Y no basta con tener sesión: un operador solo recibe los documentos de
 * su propio establecimiento. Sin esta comprobación, el filtro del listado
 * sería decorativo, porque bastaría con probar identificadores.
 */

require_once __DIR__ . '/../lib/auth.php';

exigir_metodo('GET');

// Sin sesión no hay documento: se responde en JSON porque el enlace se abre
// en una pestaña nueva y así el mensaje queda claro.
if (!hay_sesion()) {
    error_json('Debe iniciar sesión para ver el documento firmado.', 401);
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    error_json('Documento no indicado.', 400);
}

try {
    $st = db()->prepare(
        'SELECT ad.*, a.codigo_alta, a.nombre_establecimiento
         FROM alta_adjuntos ad
         INNER JOIN altas a ON a.id = ad.alta_id
         WHERE ad.id = ? LIMIT 1'
    );
    $st->execute(array($id));
    $adjunto = $st->fetch();
} catch (Exception $e) {
    error_log('[altas] ver_adjunto: ' . $e->getMessage());
    error_json('No fue posible consultar la base de datos.', 500);
}

if (!$adjunto) {
    error_json('El documento solicitado no existe.', 404);
}

if (!puede_ver_establecimiento($adjunto['nombre_establecimiento'])) {
    error_json('El documento solicitado pertenece a otro establecimiento de salud.', 403);
}

// El nombre guardado se construyó en el servidor, pero se vuelve a acotar
// a su parte final para que un valor manipulado en la base de datos no
// pueda apuntar fuera de la carpeta de subidas.
$nombre = basename(str_replace('\\', '/', (string) $adjunto['nombre_archivo_guardado']));
$ruta   = UPLOAD_DIR . DIRECTORY_SEPARATOR . $nombre;

if ($nombre === '' || !is_file($ruta)) {
    error_log('[altas] ver_adjunto: falta el archivo ' . $ruta);
    error_json('El archivo ya no se encuentra en el servidor.', 404);
}

$tipo = isset($GLOBALS['MIME_PERMITIDOS'][$adjunto['tipo_mime']]) ? $adjunto['tipo_mime'] : 'application/octet-stream';
$ext  = isset($GLOBALS['MIME_PERMITIDOS'][$tipo]) ? $GLOBALS['MIME_PERMITIDOS'][$tipo] : 'bin';

$descarga  = !empty($_GET['descargar']);
$visible   = $adjunto['codigo_alta'] . '_firmado.' . $ext;

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $tipo);
header('Content-Length: ' . filesize($ruta));
header('Content-Disposition: ' . ($descarga ? 'attachment' : 'inline') . '; filename="' . $visible . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($ruta);
