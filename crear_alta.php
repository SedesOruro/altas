<?php
/**
 * crear_alta.php
 * Recibe los datos del "Formato de Notificacion de Alta Solicitada",
 * los valida en servidor, los guarda en MySQL y genera el codigo
 * correlativo unico (ALTA-000001).
 *
 * Respuesta: JSON con el registro completo.
 */

require_once __DIR__ . '/config.php';

exigir_metodo('POST');
limitar_intentos('crear', 40, 600);

$in = entrada_post();

// ---------------------------------------------------------------------
// Definicion de campos: clave => array(etiqueta, obligatorio, maxLongitud)
// ---------------------------------------------------------------------
$camposTexto = array(
    'nombre_establecimiento'  => array('Nombre del Establecimiento de Salud', true, 255),
    'servicio_unidad'         => array('Servicio/Unidad', true, 255),
    'nombre_paciente'         => array('Nombres y Apellidos', true, 255),
    'numero_historia_clinica' => array('Número de Historia Clínica', true, 100),
    'numero_referencia'       => array('Número de Referencia', false, 100),
    'domicilio'               => array('Domicilio', true, 255),
    'motivo_alta'             => array('Motivo de Alta según Paciente', true, 5000),
    'dni_documento'           => array('DNI/Documento de Identidad', true, 50),
);

$errores = array();
$datos   = array();

foreach ($camposTexto as $clave => $def) {
    list($etiqueta, $obligatorio, $max) = $def;
    $valor = limpiar_texto(isset($in[$clave]) ? $in[$clave] : '', $max);
    if ($obligatorio && $valor === '') {
        $errores[$clave] = 'El campo "' . $etiqueta . '" es obligatorio.';
    }
    $datos[$clave] = $valor;
}

// Campos de fecha / hora
if (!fecha_valida(isset($in['fecha_internacion']) ? trim($in['fecha_internacion']) : '')) {
    $errores['fecha_internacion'] = 'La "Fecha de Internación" es obligatoria y debe tener formato AAAA-MM-DD.';
} else {
    $datos['fecha_internacion'] = trim($in['fecha_internacion']);
}

if (!fecha_valida(isset($in['fecha_solicitud']) ? trim($in['fecha_solicitud']) : '')) {
    $errores['fecha_solicitud'] = 'La "Fecha de la Solicitud" es obligatoria y debe tener formato AAAA-MM-DD.';
} else {
    $datos['fecha_solicitud'] = trim($in['fecha_solicitud']);
}

$hora = hora_normalizada(isset($in['hora_solicitud']) ? trim($in['hora_solicitud']) : '');
if ($hora === null) {
    $errores['hora_solicitud'] = 'La "Hora de la Solicitud" es obligatoria y debe tener formato HH:MM.';
} else {
    $datos['hora_solicitud'] = $hora;
}

// Coherencia: la solicitud de alta no puede ser anterior a la internacion.
if (!$errores && $datos['fecha_solicitud'] < $datos['fecha_internacion']) {
    $errores['fecha_solicitud'] = 'La fecha de solicitud no puede ser anterior a la fecha de internación.';
}

if ($errores) {
    error_json('Hay campos obligatorios sin completar o con formato inválido.', 400, array('campos' => $errores));
}

// El campo opcional se guarda como NULL cuando viene vacio.
if ($datos['numero_referencia'] === '') {
    $datos['numero_referencia'] = null;
}

// ---------------------------------------------------------------------
// Insercion + generacion del correlativo dentro de una sola transaccion.
// El codigo se deriva del AUTO_INCREMENT, por lo que es unico por
// construccion incluso ante solicitudes simultaneas.
// ---------------------------------------------------------------------
try {
    $pdo = db();
    $pdo->beginTransaction();

    $sql = 'INSERT INTO altas
              (nombre_establecimiento, servicio_unidad, nombre_paciente,
               numero_historia_clinica, numero_referencia, domicilio,
               fecha_internacion, motivo_alta, fecha_solicitud,
               hora_solicitud, dni_documento, estado)
            VALUES
              (:nombre_establecimiento, :servicio_unidad, :nombre_paciente,
               :numero_historia_clinica, :numero_referencia, :domicilio,
               :fecha_internacion, :motivo_alta, :fecha_solicitud,
               :hora_solicitud, :dni_documento, "generado")';

    $st = $pdo->prepare($sql);
    $st->execute(array(
        ':nombre_establecimiento'  => $datos['nombre_establecimiento'],
        ':servicio_unidad'         => $datos['servicio_unidad'],
        ':nombre_paciente'         => $datos['nombre_paciente'],
        ':numero_historia_clinica' => $datos['numero_historia_clinica'],
        ':numero_referencia'       => $datos['numero_referencia'],
        ':domicilio'               => $datos['domicilio'],
        ':fecha_internacion'       => $datos['fecha_internacion'],
        ':motivo_alta'             => $datos['motivo_alta'],
        ':fecha_solicitud'         => $datos['fecha_solicitud'],
        ':hora_solicitud'          => $datos['hora_solicitud'],
        ':dni_documento'           => $datos['dni_documento'],
    ));

    $id = (int) $pdo->lastInsertId();

    $pdo->prepare('UPDATE altas SET codigo_alta = CONCAT(?, LPAD(id, 6, "0")) WHERE id = ?')
        ->execute(array(PREFIJO_CODIGO, $id));

    $st = $pdo->prepare('SELECT * FROM altas WHERE id = ?');
    $st->execute(array($id));
    $registro = $st->fetch();

    $pdo->commit();
} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[altas] crear_alta: ' . $e->getMessage());
    error_json('No fue posible registrar la solicitud de alta. Intente nuevamente.', 500);
}

responder(array(
    'ok'          => true,
    'mensaje'     => 'Solicitud registrada correctamente.',
    'codigo_alta' => $registro['codigo_alta'],
    'registro'    => $registro,
    'pdf_url'     => 'generar_pdf.php?codigo=' . rawurlencode($registro['codigo_alta']),
), 201);
