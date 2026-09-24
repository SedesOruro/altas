<?php
/**
 * verificar_codigo.php
 * Comprueba si un código de alta existe antes de permitir la subida del
 * documento firmado. Lo usa la Sección B de la página al salir del campo
 * "Código de Alta".
 *
 * Uso:  verificar_codigo.php?codigo=ALTA-000001
 *
 * Por privacidad no devuelve los datos clínicos completos del paciente:
 * solo lo necesario para que el usuario confirme que es el registro correcto.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';

exigir_metodo('GET');
$sesion = exigir_sesion_json();
limitar_intentos('verificar', 30, 300);

$codigo = normalizar_codigo(isset($_GET['codigo']) ? $_GET['codigo'] : '');

if (!codigo_valido($codigo)) {
    error_json('El código de alta no tiene un formato válido (ejemplo: ALTA-000001).', 400);
}

try {
    $alta = buscar_alta_por_codigo($codigo);
} catch (Exception $e) {
    error_log('[altas] verificar_codigo: ' . $e->getMessage());
    error_json('No fue posible consultar la base de datos.', 500);
}

if (!$alta) {
    error_json('El código de alta ingresado no corresponde a ninguna solicitud registrada.', 404);
}

// Un alta de otro establecimiento se trata como inexistente en la
// respuesta al operador, salvo que se le diga de quién es: saber que el
// código existe no le sirve y expondría datos de otro hospital.
if (!puede_ver_establecimiento($alta['nombre_establecimiento'])) {
    error_json('Ese código corresponde a otro establecimiento de salud.', 403);
}

try {
    $st = db()->prepare('SELECT COUNT(*) FROM alta_adjuntos WHERE alta_id = ?');
    $st->execute(array((int) $alta['id']));
    $adjuntos = (int) $st->fetchColumn();
} catch (Exception $e) {
    $adjuntos = 0;
}

responder(array(
    'ok'          => true,
    'codigo_alta' => $alta['codigo_alta'],
    'paciente'    => $alta['nombre_paciente'],
    'servicio'    => $alta['servicio_unidad'],
    'estado'      => $alta['estado'],
    'adjuntos'    => $adjuntos,
));
