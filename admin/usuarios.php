<?php
/**
 * Panel — usuarios del sistema.
 *
 * Listado simple y activación/desactivación de cuentas. El alta de usuarios
 * se hace en registro.php, que ya trae todas las validaciones.
 *
 * No se borran cuentas: se desactivan. Una cuenta borrada dejaría sin
 * explicación los accesos ya registrados, y desactivar cumple la misma
 * función práctica.
 */

require_once __DIR__ . '/_plantilla.php';

$sesion = exigir_sesion();
$aviso  = '';
$error  = '';

// --- Activar o desactivar una cuenta ----------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido(isset($_POST['csrf']) ? $_POST['csrf'] : '')) {
        $error = 'La sesión del formulario caducó. Vuelva a intentarlo.';
    } else {
        $id     = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $activo = !empty($_POST['activar']) ? 1 : 0;

        if ($id === $sesion['id'] && $activo === 0) {
            $error = 'No puede desactivar su propia cuenta.';
        } elseif ($id > 0) {
            try {
                db()->prepare('UPDATE usuarios SET activo = ? WHERE id = ?')->execute(array($activo, $id));
                $aviso = $activo ? 'Cuenta activada.' : 'Cuenta desactivada.';
            } catch (Exception $e) {
                error_log('[altas] usuarios: ' . $e->getMessage());
                $error = 'No fue posible actualizar la cuenta.';
            }
        }
    }
}

// --- Listado -----------------------------------------------------------
$usuarios = array();
try {
    $usuarios = db()->query(
        'SELECT id, username, nombre_completo, ci, telefono, correo, activo, ultimo_acceso, created_at
         FROM usuarios ORDER BY nombre_completo'
    )->fetchAll();
} catch (Exception $e) {
    error_log('[altas] usuarios listado: ' . $e->getMessage());
    $error = 'No fue posible leer los usuarios.';
}

/** Fecha y hora legible, o un guion si nunca ocurrió. */
function fecha_legible($valor)
{
    if (!$valor) {
        return '—';
    }
    $ts = strtotime($valor);
    return $ts ? date('d/m/Y H:i', $ts) : '—';
}

admin_cabecera('Usuarios', 'usuarios');
?>

<?php if ($aviso): ?><div class="alert alert-success py-2"><?= h($aviso) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger py-2"><?= h($error) ?></div><?php endif; ?>

<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <h3 class="card-title mb-0">Usuarios del panel</h3>
    <a href="<?= h(ruta_base()) ?>registro.php" class="btn btn-sm btn-primary">Registrar usuario</a>
  </div>

  <div class="card-body table-responsive p-0">
    <table class="table table-sm table-hover mb-0">
      <thead>
        <tr>
          <th>Usuario</th>
          <th>Nombre completo</th>
          <th>CI</th>
          <th>Teléfono</th>
          <th>Correo electrónico</th>
          <th>Estado</th>
          <th>Último acceso</th>
          <th>Alta</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$usuarios): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">No hay usuarios registrados.</td></tr>
        <?php endif; ?>

        <?php foreach ($usuarios as $u): ?>
          <tr>
            <td><strong><?= h($u['username']) ?></strong></td>
            <td><?= h($u['nombre_completo']) ?></td>
            <td><?= h($u['ci']) ?></td>
            <td><?= h($u['telefono']) ?></td>
            <td><?= h($u['correo']) ?></td>
            <td>
              <?php if ((int) $u['activo'] === 1): ?>
                <span class="badge badge-success">Activa</span>
              <?php else: ?>
                <span class="badge badge-secondary">Desactivada</span>
              <?php endif; ?>
            </td>
            <td class="text-nowrap"><?= h(fecha_legible($u['ultimo_acceso'])) ?></td>
            <td class="text-nowrap"><?= h(fecha_legible($u['created_at'])) ?></td>
            <td class="text-right text-nowrap">
              <?php if ((int) $u['id'] === $sesion['id']): ?>
                <span class="text-muted small">Su cuenta</span>
              <?php else: ?>
                <form method="post" action="" class="d-inline">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <?php if ((int) $u['activo'] === 1): ?>
                    <button type="submit" class="btn btn-xs btn-default">Desactivar</button>
                  <?php else: ?>
                    <input type="hidden" name="activar" value="1">
                    <button type="submit" class="btn btn-xs btn-success">Activar</button>
                  <?php endif; ?>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php admin_pie(); ?>
