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

exigir_sesion();

// Cifras del encabezado. Son cuatro consultas muy simples; hacerlas aquí
// evita un viaje extra del navegador al cargar el panel.
$resumen = array('total' => 0, 'verificadas' => 0, 'pendientes' => 0, 'hoy' => 0);
$errorBd = null;

try {
    $pdo = db();
    $resumen['total']       = (int) $pdo->query('SELECT COUNT(*) FROM altas')->fetchColumn();
    $resumen['verificadas'] = (int) $pdo->query("SELECT COUNT(*) FROM altas WHERE estado = 'verificado'")->fetchColumn();
    $resumen['pendientes']  = (int) $pdo->query("SELECT COUNT(*) FROM altas WHERE estado = 'generado'")->fetchColumn();
    $resumen['hoy']         = (int) $pdo->query('SELECT COUNT(*) FROM altas WHERE DATE(created_at) = CURDATE()')->fetchColumn();
} catch (Exception $e) {
    error_log('[altas] panel resumen: ' . $e->getMessage());
    $errorBd = 'No fue posible leer el resumen desde la base de datos.';
}

admin_cabecera('Altas registradas', 'panel');
?>

<?php if ($errorBd): ?>
  <div class="alert alert-danger"><?= h($errorBd) ?></div>
<?php endif; ?>

<!-- Resumen -->
<div class="row">
  <?php
  $tarjetas = array(
      array('Altas registradas', $resumen['total'],       'bg-primary'),
      array('Con documento firmado', $resumen['verificadas'], 'bg-success'),
      array('Pendientes de subir',   $resumen['pendientes'],  'bg-warning'),
      array('Registradas hoy',       $resumen['hoy'],         'bg-info'),
  );
  foreach ($tarjetas as $t): ?>
    <div class="col-6 col-lg-3">
      <div class="small-box <?= $t[2] ?>">
        <div class="inner">
          <h3><?= (int) $t[1] ?></h3>
          <p><?= h($t[0]) ?></p>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Tabla -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title">Listado de altas</h3>
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
            <th>CI/Pasaporte</th>
            <th>Documento firmado</th>
            <th>Registrada</th>
          </tr>
        </thead>
        <tbody id="cuerpo-tabla">
          <tr><td colspan="22" class="text-center text-muted py-4">Cargando…</td></tr>
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

<?php admin_pie(array('assets/admin.js?v=1')); ?>
