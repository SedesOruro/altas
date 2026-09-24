<?php
/**
 * crear_alta.php
 * Recibe los datos del "Formulario de Notificación de Alta Solicitada",
 * los valida en servidor, los guarda en MySQL y genera el código
 * correlativo único (ALTA-000001).
 *
 * Respuesta: JSON con el registro completo.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/redes.php';

exigir_metodo('POST');

// El formulario dejó de ser público: registrar un alta es trabajo del
// personal autenticado, sea operador o administrador.
$sesion = exigir_sesion_json();

limitar_intentos('crear', 40, 600);

$in = entrada_post();

// ---------------------------------------------------------------------
// Campos de texto: clave => array(etiqueta, obligatorio, maxLongitud)
// ---------------------------------------------------------------------
$camposTexto = array(
    // 1. Datos del Establecimiento de Salud
    // (red, municipio y establecimiento se validan aparte, contra el catálogo)
    'servicio_unidad'         => array('Servicio/Unidad', true, 255),
    // 2. Información del Paciente
    'nombre_paciente'         => array('Nombres y Apellidos', true, 255),
    'numero_historia_clinica' => array('Número de Historia Clínica', true, 100),
    'numero_referencia'       => array('Número de Referencia', false, 100),
    'domicilio'               => array('Domicilio', true, 255),
    // 3. Detalles de la Internación
    'diagnosticos_ingreso'    => array('Diagnósticos de Ingreso', true, 300),
    'diagnosticos_egreso'     => array('Diagnósticos de Egreso', true, 300),
    // 4. Declaración de Alta Solicitada
    'motivo_alta'             => array('Motivo de Alta según Paciente', true, 5000),
    // 5. Firmas y Fecha (datos de quien firma el alta)
    'grado_parentesco'        => array('Grado de Parentesco', false, 120),
    'nombre_firmante'         => array('Nombre Completo', true, 255),
    'ci_pasaporte'            => array('N° de Cédula de Identidad/Pasaporte', true, 50),
    'telefono_firmante'       => array('Teléfono', false, 50),
    'direccion_firmante'      => array('Dirección', false, 255),
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

// ---------------------------------------------------------------------
// Red de salud, municipio y establecimiento
//
// Hay dos caminos, y el que manda es el de la cuenta:
//
//   - Con establecimiento asignado (el operador), los tres datos salen de
//     la sesión y lo que traiga el formulario se descarta. Eso es lo que
//     impide registrar un alta a nombre de otro hospital manipulando la
//     petición, venga o no del formulario.
//   - Sin asignación (el administrador), se eligen y se validan contra el
//     catálogo de lib/redes.php: la red decide el municipio y acota los
//     establecimientos posibles.
// ---------------------------------------------------------------------
$miEstablecimiento = establecimiento_de_sesion();

if ($miEstablecimiento) {
    $datos['red_salud']              = $miEstablecimiento['red_salud'];
    $datos['municipio']              = $miEstablecimiento['municipio'];
    $datos['nombre_establecimiento'] = $miEstablecimiento['nombre_establecimiento'];
} else {
    $red = limpiar_texto(isset($in['red_salud']) ? $in['red_salud'] : '', 255);

    if ($red === '') {
        $errores['red_salud'] = 'Seleccione la red de salud.';
    } elseif (!red_valida($red)) {
        $errores['red_salud'] = 'La red de salud indicada no pertenece al catálogo del SEDES Oruro.';
    } else {
        $datos['red_salud'] = $red;

        // El municipio no se toma del cliente: se deriva de la red, que es
        // lo que evita que lleguen combinaciones imposibles.
        $datos['municipio'] = municipio_de_red($red);

        $establecimiento = limpiar_texto(isset($in['nombre_establecimiento']) ? $in['nombre_establecimiento'] : '', 255);
        if ($establecimiento === '') {
            $errores['nombre_establecimiento'] = 'Seleccione el establecimiento de salud.';
        } elseif (!establecimiento_de_red($red, $establecimiento)) {
            $errores['nombre_establecimiento'] = 'Ese establecimiento no corresponde a la red «' . $red . '».';
        } else {
            $datos['nombre_establecimiento'] = $establecimiento;
        }
    }
}

// ---------------------------------------------------------------------
// Edad, unidad de edad y sexo
// ---------------------------------------------------------------------
$edadCruda = isset($in['edad']) ? trim((string) $in['edad']) : '';
if ($edadCruda === '' || !preg_match('/^\d{1,3}$/', $edadCruda)) {
    $errores['edad'] = 'La "Edad" es obligatoria y debe ser un número entero.';
} else {
    $datos['edad'] = (int) $edadCruda;
}

$unidadesValidas = array('anios', 'meses', 'dias');
$unidad = isset($in['edad_unidad']) ? trim((string) $in['edad_unidad']) : 'anios';
if (!in_array($unidad, $unidadesValidas, true)) {
    $errores['edad_unidad'] = 'Seleccione si la edad está expresada en años, meses o días.';
} else {
    $datos['edad_unidad'] = $unidad;
}

$sexo = isset($in['sexo']) ? strtoupper(trim((string) $in['sexo'])) : '';
if ($sexo !== 'M' && $sexo !== 'F') {
    $errores['sexo'] = 'El campo "Sexo" es obligatorio.';
} else {
    $datos['sexo'] = $sexo;
}

// Coherencia clínica básica: nadie vive 150 años.
if (!isset($errores['edad']) && isset($datos['edad_unidad'])) {
    $topes = array('anios' => 130, 'meses' => 1560, 'dias' => 47450);
    if ($datos['edad'] > $topes[$datos['edad_unidad']]) {
        $errores['edad'] = 'La edad indicada no es válida.';
    }
}

// ---------------------------------------------------------------------
// Fechas y horas
// ---------------------------------------------------------------------
$fechas = array(
    'fecha_internacion' => 'Fecha de Internación',
    'fecha_solicitud'   => 'Fecha de Alta Solicitada',
);
foreach ($fechas as $clave => $etiqueta) {
    $valor = isset($in[$clave]) ? trim((string) $in[$clave]) : '';
    if (!fecha_valida($valor)) {
        $errores[$clave] = 'La "' . $etiqueta . '" es obligatoria y debe tener formato AAAA-MM-DD.';
    } else {
        $datos[$clave] = $valor;
    }
}

$horas = array(
    'hora_internacion' => 'Hora de Internación',
    'hora_solicitud'   => 'Hora de Solicitud',
);
foreach ($horas as $clave => $etiqueta) {
    $valor = hora_normalizada(isset($in[$clave]) ? trim((string) $in[$clave]) : '');
    if ($valor === null) {
        $errores[$clave] = 'La "' . $etiqueta . '" es obligatoria y debe tener formato HH:MM.';
    } else {
        $datos[$clave] = $valor;
    }
}

// El alta no puede producirse antes del ingreso.
if (!isset($errores['fecha_internacion'], $errores['fecha_solicitud'])
    && isset($datos['fecha_internacion'], $datos['fecha_solicitud'])) {
    $ingreso = $datos['fecha_internacion'] . ' ' . (isset($datos['hora_internacion']) ? $datos['hora_internacion'] : '00:00:00');
    $alta    = $datos['fecha_solicitud'] . ' ' . (isset($datos['hora_solicitud']) ? $datos['hora_solicitud'] : '00:00:00');
    if (strtotime($alta) < strtotime($ingreso)) {
        $errores['fecha_solicitud'] = 'La fecha y hora del alta no pueden ser anteriores a las de la internación.';
    }
}

if ($errores) {
    error_json('Hay campos obligatorios sin completar o con formato inválido.', 400, array('campos' => $errores));
}

// Los campos opcionales se guardan como NULL cuando vienen vacíos.
foreach (array('numero_referencia', 'grado_parentesco', 'telefono_firmante', 'direccion_firmante') as $opcional) {
    if ($datos[$opcional] === '') {
        $datos[$opcional] = null;
    }
}

// ---------------------------------------------------------------------
// Inserción + generación del correlativo dentro de una sola transacción.
// El código se deriva del AUTO_INCREMENT, por lo que es único por
// construcción incluso ante solicitudes simultáneas.
// ---------------------------------------------------------------------
$columnas = array(
    'nombre_establecimiento', 'red_salud', 'municipio', 'servicio_unidad',
    'nombre_paciente', 'edad', 'edad_unidad', 'sexo', 'numero_historia_clinica',
    'numero_referencia', 'domicilio',
    'fecha_internacion', 'hora_internacion', 'diagnosticos_ingreso',
    'fecha_solicitud', 'hora_solicitud', 'diagnosticos_egreso',
    'motivo_alta',
    'grado_parentesco', 'nombre_firmante', 'ci_pasaporte',
    'telefono_firmante', 'direccion_firmante',
);

try {
    $pdo = db();
    $pdo->beginTransaction();

    $marcadores = array();
    $valores    = array();
    foreach ($columnas as $columna) {
        $marcadores[] = ':' . $columna;
        $valores[':' . $columna] = $datos[$columna];
    }

    $sql = 'INSERT INTO altas (' . implode(', ', $columnas) . ', estado) VALUES ('
         . implode(', ', $marcadores) . ', "generado")';

    $st = $pdo->prepare($sql);
    $st->execute($valores);

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
