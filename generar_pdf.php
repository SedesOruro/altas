<?php
/**
 * generar_pdf.php
 * Entrega el PDF del formato de alta solicitada correspondiente a un código.
 *
 * Uso:  generar_pdf.php?codigo=ALTA-000001
 *       generar_pdf.php?codigo=ALTA-000001&modo=inline   (ver en el navegador)
 *
 * A diferencia del resto de scripts, este responde con el binario del PDF.
 * Los errores sí se devuelven en JSON para que el frontend pueda mostrarlos.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/pdf_alta.php';

exigir_metodo('GET');
limitar_intentos('pdf', 60, 300);

$codigo = normalizar_codigo(isset($_GET['codigo']) ? $_GET['codigo'] : '');

if (!codigo_valido($codigo)) {
    error_json('El código de alta no tiene un formato válido (ejemplo: ALTA-000001).', 400);
}

try {
    $alta = buscar_alta_por_codigo($codigo);
} catch (Exception $e) {
    error_log('[altas] generar_pdf: ' . $e->getMessage());
    error_json('No fue posible consultar la base de datos.', 500);
}

if (!$alta) {
    error_json('El código de alta ingresado no corresponde a ninguna solicitud registrada.', 404);
}

try {
    $pdf = construir_pdf_alta($alta);
} catch (Exception $e) {
    error_log('[altas] generar_pdf (construcción): ' . $e->getMessage());
    error_json('No fue posible generar el documento PDF.', 500);
}

$modo          = (isset($_GET['modo']) && $_GET['modo'] === 'inline') ? 'I' : 'D';
$nombreArchivo = $alta['codigo_alta'] . '_alta_solicitada.pdf';

// Se limpia cualquier salida previa para no corromper el binario del PDF.
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');
$pdf->Output($modo, $nombreArchivo);
