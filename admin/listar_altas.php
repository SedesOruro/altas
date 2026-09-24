<?php
/**
 * listar_altas.php — datos de la tabla del panel.
 *
 * Devuelve en JSON las altas registradas, ya filtradas y paginadas, junto
 * con el identificador del documento firmado cuando existe (para habilitar
 * el botón «Ver PDF subido»).
 *
 * Parámetros (GET):
 *   q           texto libre: código, paciente, historia clínica o cédula
 *   estado      generado | verificado | rechazado  (vacío = todos)
 *   desde/hasta rango sobre la fecha de alta solicitada (AAAA-MM-DD)
 *   pagina      número de página, desde 1
 *   por_pagina  10 | 25 | 50 | 100
 */

require_once __DIR__ . '/../lib/auth.php';

exigir_metodo('GET');
$sesion = exigir_sesion_json();

// ---------------------------------------------------------------------
// Filtros
// ---------------------------------------------------------------------
$q      = limpiar_texto(isset($_GET['q']) ? $_GET['q'] : '', 80);
$estado = isset($_GET['estado']) ? trim((string) $_GET['estado']) : '';
$desde  = isset($_GET['desde']) ? trim((string) $_GET['desde']) : '';
$hasta  = isset($_GET['hasta']) ? trim((string) $_GET['hasta']) : '';

if (!in_array($estado, array('generado', 'verificado', 'rechazado'), true)) {
    $estado = '';
}
if (!fecha_valida($desde)) { $desde = ''; }
if (!fecha_valida($hasta)) { $hasta = ''; }

// Un rango al revés se endereza en lugar de devolver cero resultados.
if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
    $intercambio = $desde;
    $desde       = $hasta;
    $hasta       = $intercambio;
}

$condiciones = array();
$parametros  = array();

// Alcance de la cuenta: el operador solo ve las altas de su
// establecimiento; el administrador, que no tiene ninguno asignado, las ve
// todas. Es una condición más del WHERE, así que también acota el total y
// la paginación, no solo las filas visibles.
$miEstablecimiento = establecimiento_de_sesion();
if ($miEstablecimiento) {
    $condiciones[]                    = 'a.nombre_establecimiento = :establecimiento';
    $parametros[':establecimiento']   = $miEstablecimiento['nombre_establecimiento'];
}

if ($q !== '') {
    // Un marcador distinto por columna: con sentencias preparadas nativas
    // MySQL no admite repetir el mismo nombre dentro de una consulta.
    $condiciones[] = '(a.codigo_alta LIKE :q1 OR a.nombre_paciente LIKE :q2 '
                   . 'OR a.numero_historia_clinica LIKE :q3 OR a.ci_pasaporte LIKE :q4)';
    $patron = '%' . $q . '%';
    foreach (array(':q1', ':q2', ':q3', ':q4') as $marcador) {
        $parametros[$marcador] = $patron;
    }
}
if ($estado !== '') {
    $condiciones[]         = 'a.estado = :estado';
    $parametros[':estado'] = $estado;
}
if ($desde !== '') {
    $condiciones[]        = 'a.fecha_solicitud >= :desde';
    $parametros[':desde'] = $desde;
}
if ($hasta !== '') {
    $condiciones[]        = 'a.fecha_solicitud <= :hasta';
    $parametros[':hasta'] = $hasta;
}

$where = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';

// ---------------------------------------------------------------------
// Paginación
// ---------------------------------------------------------------------
$porPagina = isset($_GET['por_pagina']) ? (int) $_GET['por_pagina'] : 25;
if (!in_array($porPagina, array(10, 25, 50, 100), true)) {
    $porPagina = 25;
}
$pagina = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;

try {
    $st = db()->prepare('SELECT COUNT(*) FROM altas a' . $where);
    $st->execute($parametros);
    $total = (int) $st->fetchColumn();

    $paginas = max(1, (int) ceil($total / $porPagina));
    if ($pagina > $paginas) {
        $pagina = $paginas;
    }
    $desplazamiento = ($pagina - 1) * $porPagina;

    // El adjunto que se muestra es el último registrado para cada alta, y
    // solo mientras el alta esté verificada: al devolverla a pendiente el
    // documento anterior deja de contar como documento vigente, así que el
    // panel lo trata como si no hubiera ninguno. La fila sigue en la tabla
    // `alta_adjuntos` como historial; lo que cambia es que no se ofrece.
    $sql = 'SELECT a.*,
                   ad.id   AS adjunto_id,
                   ad.nombre_archivo_original AS adjunto_nombre,
                   ad.tipo_mime  AS adjunto_tipo,
                   ad.created_at AS adjunto_fecha
            FROM altas a
            LEFT JOIN (
                SELECT alta_id, MAX(id) AS ultimo FROM alta_adjuntos GROUP BY alta_id
            ) ult ON ult.alta_id = a.id AND a.estado = "verificado"
            LEFT JOIN alta_adjuntos ad ON ad.id = ult.ultimo'
         . $where
         . ' ORDER BY a.id DESC LIMIT ' . (int) $porPagina . ' OFFSET ' . (int) $desplazamiento;

    $st = db()->prepare($sql);
    $st->execute($parametros);
    $filas = $st->fetchAll();
} catch (Exception $e) {
    error_log('[altas] listar_altas: ' . $e->getMessage());
    error_json('No fue posible consultar las altas registradas.', 500);
}

responder(array(
    'ok'         => true,
    'alcance'    => $miEstablecimiento ? $miEstablecimiento['nombre_establecimiento'] : null,
    'total'      => $total,
    'pagina'     => $pagina,
    'paginas'    => $paginas,
    'por_pagina' => $porPagina,
    'desde_fila' => $total === 0 ? 0 : $desplazamiento + 1,
    'hasta_fila' => min($desplazamiento + $porPagina, $total),
    'altas'      => $filas,
));
