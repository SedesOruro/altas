<?php
/**
 * devolver_pendiente.php — devuelve un alta al estado «pendiente».
 *
 * Sirve para cuando el documento firmado llegó ilegible, incompleto o
 * corresponde a otro paciente: el alta vuelve a `generado` y el
 * establecimiento puede subir el documento otra vez con el mismo código,
 * sin que haga falta tocar la base de datos a mano.
 *
 * El archivo anterior NO se borra. Queda en el historial de adjuntos —el
 * panel muestra siempre el último— porque es un documento clínico firmado y
 * eliminarlo con un clic no debería ser una operación de rutina.
 *
 * Entrada: POST con `codigo_alta`, `csrf` y, opcionalmente, `motivo`.
 * Salida: JSON.
 */

require_once __DIR__ . '/../lib/auth.php';

exigir_metodo('POST');
$sesion = exigir_sesion_json();

$in = entrada_post();

if (!csrf_valido(isset($in['csrf']) ? $in['csrf'] : '')) {
    error_json('La sesión del formulario caducó. Recargue la página e intente de nuevo.', 403);
}

$codigo = normalizar_codigo(isset($in['codigo_alta']) ? $in['codigo_alta'] : '');
if (!codigo_valido($codigo)) {
    error_json('El código de alta no tiene un formato válido.', 400);
}

try {
    $alta = buscar_alta_por_codigo($codigo);
} catch (Exception $e) {
    error_log('[altas] devolver_pendiente: ' . $e->getMessage());
    error_json('No fue posible consultar la base de datos.', 500);
}

if (!$alta) {
    error_json('El código de alta no corresponde a ninguna solicitud registrada.', 404);
}

if ($alta['estado'] === 'generado') {
    error_json('El alta ' . $alta['codigo_alta'] . ' ya está pendiente de subir.', 409);
}

$motivo = limpiar_texto(isset($in['motivo']) ? $in['motivo'] : '', 200);

try {
    db()->prepare('UPDATE altas SET estado = "generado" WHERE id = ?')
        ->execute(array((int) $alta['id']));
} catch (Exception $e) {
    error_log('[altas] devolver_pendiente (update): ' . $e->getMessage());
    error_json('No fue posible cambiar el estado del alta.', 500);
}

// No hay tabla de auditoría, así que el rastro de quién devolvió qué alta
// queda en el log del servidor. Es lo mínimo para poder reconstruir después
// por qué un alta verificada volvió a pendiente.
error_log(sprintf(
    '[altas] %s devolvió %s a pendiente%s',
    $sesion['username'],
    $alta['codigo_alta'],
    $motivo !== '' ? ' (motivo: ' . $motivo . ')' : ''
));

responder(array(
    'ok'          => true,
    'mensaje'     => 'El alta ' . $alta['codigo_alta'] . ' volvió a pendiente. '
                   . 'El establecimiento ya puede subir el documento otra vez.',
    'codigo_alta' => $alta['codigo_alta'],
    'estado'      => 'generado',
));
