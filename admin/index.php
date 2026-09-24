<?php
/**
 * Panel de administración — altas registradas.
 *
 * Muestra un resumen de cifras y la tabla completa de altas, con filtros y
 * paginación. Los datos de la tabla se piden por `fetch()` a
 * listar_altas.php, de modo que filtrar o cambiar de página no recarga la
 * página entera.
 */

require_once __DIR__ . '/_plantilla.php';

$sesion  = exigir_sesion();
$esAdmin = $sesion['rol'] === ROL_ADMINISTRADOR;

// Aviso al operador que intentó entrar a una pantalla de administración.
$avisoRol = (isset($_GET['aviso']) && $_GET['aviso'] === 'solo_administrador');

// Cifras del encabezado. Son cuatro consultas muy simples; hacerlas aquí
// evita un viaje extra del navegador al cargar el panel.
//
// Cuentan lo mismo que muestra la tabla: si la cuenta está atada a un
// establecimiento, solo sus altas. Un resumen del departamento entero
// encima de una tabla de un solo hospital sería un dato engañoso.
$miEstablecimiento = establecimiento_de_sesion();

$resumen = array('total' => 0, 'verificadas' => 0, 'pendientes' => 0, 'hoy' => 0);
$errorBd = null;

$donde      = $miEstablecimiento ? ' WHERE nombre_establecimiento = :establecimiento' : '';
$parametros = $miEstablecimiento
    ? array(':establecimiento' => $miEstablecimiento['nombre_establecimiento'])
    : array();

/** Cuenta altas aplicando el alcance de la cuenta y una condición extra. */
function contar_altas($extra, $donde, $parametros)
{
    $sql = 'SELECT COUNT(*) FROM altas' . $donde;
    if ($extra !== '') {
        $sql .= ($donde === '' ? ' WHERE ' : ' AND ') . $extra;
    }
    $st = db()->prepare($sql);
    $st->execute($parametros);
    return (int) $st->fetchColumn();
}

try {
    $resumen['total']       = contar_altas('', $donde, $parametros);
    $resumen['verificadas'] = contar_altas("estado = 'verificado'", $donde, $parametros);
    $resumen['pendientes']  = contar_altas("estado = 'generado'", $donde, $parametros);
    $resumen['hoy']         = contar_altas('DATE(created_at) = CURDATE()', $donde, $parametros);
} catch (Exception $e) {
    error_log('[altas] panel resumen: ' . $e->getMessage());
    $errorBd = 'No fue posible leer el resumen desde la base de datos.';
}

admin_cabecera('Altas registradas', 'panel');
?>

<?php if ($avisoRol): ?>
  <div class="alert alert-warning py-2">
    Esa sección está reservada al administrador del sistema.
  </div>
<?php endif; ?>

<!-- Acción principal del módulo de altas. Para el operador es lo primero
     que necesita al entrar, así que va arriba y destacada. -->
<div class="barra-accion">
  <a href="<?= h(ruta_base()) ?>registro_altas.php" class="btn btn-primary btn-accion">
    <?= icono('nueva', 18) ?> Registrar nueva alta
  </a>
  <span class="text-muted small">
    <?php if ($miEstablecimiento): ?>
      Se registrará a nombre de <strong><?= h($miEstablecimiento['nombre_establecimiento']) ?></strong>.
    <?php else: ?>
      Llene el formulario para obtener el código correlativo y el PDF oficial.
    <?php endif; ?>
  </span>
</div>

<?php if ($errorBd): ?>
  <div class="alert alert-danger"><?= h($errorBd) ?></div>
<?php endif; ?>

<!-- Resumen -->
<div class="row">
  <?php
  // La clave de cada tarjeta permite que admin.js refresque la cifra sin
  // recargar la página cuando una acción cambia el estado de un alta.
  $tarjetas = array(
      array('Altas registradas',     $resumen['total'],       'bg-primary', 'total'),
      array('Con documento firmado', $resumen['verificadas'], 'bg-success', 'verificadas'),
      array('Pendientes de subir',   $resumen['pendientes'],  'bg-warning', 'pendientes'),
      array('Registradas hoy',       $resumen['hoy'],         'bg-info',    'hoy'),
  );
  foreach ($tarjetas as $t): ?>
    <div class="col-6 col-lg-3">
      <div class="small-box <?= $t[2] ?>">
        <div class="inner">
          <h3 data-resumen="<?= $t[3] ?>"><?= (int) $t[1] ?></h3>
          <p><?= h($t[0]) ?></p>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Tabla -->
<!-- El testigo anti-CSRF se publica aquí para que admin.js lo envíe en las
     acciones que modifican datos (devolver un alta a pendiente), y el rol
     para que no dibuje botones que el servidor va a rechazar. -->
<div class="card" data-csrf="<?= h(csrf_token()) ?>" data-rol="<?= h($sesion['rol']) ?>">
  <div class="card-header">
    <h3 class="card-title">
      Listado de altas
      <?php if ($miEstablecimiento): ?>
        <span class="card-title__alcance">· <?= h($miEstablecimiento['nombre_establecimiento']) ?></span>
      <?php endif; ?>
    </h3>
  </div>

  <div class="card-body">
    <form id="filtros" class="filtros" autocomplete="off">
      <div class="form-group filtros__buscar">
        <label for="f-q">Buscar</label>
        <input type="search" class="form-control" id="f-q" name="q"
               placeholder="Código, paciente, historia clínica o cédula">
      </div>
      <div class="form-group">
        <label for="f-estado">Estado</label>
        <select class="form-control" id="f-estado" name="estado">
          <option value="">Todos</option>
          <option value="verificado">Con documento firmado</option>
          <option value="generado">Pendiente de subir</option>
          <option value="rechazado">Rechazada</option>
        </select>
      </div>
      <div class="form-group">
        <label for="f-desde">Alta desde</label>
        <input type="date" class="form-control" id="f-desde" name="desde">
      </div>
      <div class="form-group">
        <label for="f-hasta">Alta hasta</label>
        <input type="date" class="form-control" id="f-hasta" name="hasta">
      </div>
      <div class="form-group">
        <label for="f-por-pagina">Por página</label>
        <select class="form-control" id="f-por-pagina" name="por_pagina">
          <option value="10">10</option>
          <option value="25" selected>25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
      </div>
      <div class="form-group filtros__acciones">
        <button type="button" class="btn btn-default btn-block" id="btn-limpiar">Limpiar</button>
      </div>
    </form>

    <p class="alert py-2" id="aviso-tabla" role="status" hidden></p>

    <div class="table-responsive tabla-visual">
      <table class="table table-sm table-hover table-bordered" id="tabla-altas">
        <thead>
          <tr>
            <th>Opciones</th>
            <th>Código</th>
            <th>Estado</th>
            <th>Paciente</th>
            <th>Edad</th>
            <th>Sexo</th>
            <th>Historia clínica</th>
            <th>Referencia</th>
            <th>Domicilio</th>
            <th>Establecimiento</th>
            <th>Red de salud</th>
            <th>Municipio</th>
            <th>Servicio/Unidad</th>
            <th>Internación</th>
            <th>Diagnósticos de ingreso</th>
            <th>Alta solicitada</th>
            <th>Diagnósticos de egreso</th>
            <th>Motivo del alta</th>
            <th>Parentesco</th>
            <th>Nombre del firmante</th>
            <th>CI/Pasaporte</th>
            <th>Teléfono</th>
            <th>Dirección</th>
            <th>Documento firmado</th>
            <th>Registrada</th>
          </tr>
        </thead>
        <tbody id="cuerpo-tabla">
          <tr><td colspan="25" class="text-center text-muted py-4">Cargando…</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card-footer">
    <div class="paginacion">
      <span class="paginacion__resumen" id="resumen-paginacion"></span>
      <div class="btn-group" role="group" aria-label="Paginación">
        <button type="button" class="btn btn-sm btn-default" id="btn-primera">«</button>
        <button type="button" class="btn btn-sm btn-default" id="btn-anterior">Anterior</button>
        <span class="btn btn-sm btn-default disabled" id="indicador-pagina">1 / 1</span>
        <button type="button" class="btn btn-sm btn-default" id="btn-siguiente">Siguiente</button>
        <button type="button" class="btn btn-sm btn-default" id="btn-ultima">»</button>
      </div>
    </div>
  </div>
</div>

<?php admin_pie(array('assets/admin.js?v=6')); ?>
