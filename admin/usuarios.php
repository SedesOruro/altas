<?php
/**
 * Panel — usuarios del sistema.
 *
 * Listado con las tres operaciones sobre cada cuenta:
 *
 *   - **Editar** — abre usuario_editar.php.
 *   - **Activar / Desactivar** — retira o devuelve el acceso conservando el
 *     registro de quién entró y cuándo. Es la vía recomendada.
 *   - **Eliminar** — borrado definitivo, con confirmación y dos candados en
 *     el servidor: nadie puede borrarse a sí mismo ni dejar la tabla de
 *     usuarios vacía (eso reabriría el registro público).
 *
 * Todas las acciones van por POST con testigo anti-CSRF y responden con una
 * redirección, para que al recargar la página no se repita la operación.
 *
 * La pantalla es solo para administradores: un operador que llegue aquí
 * vuelve a su listado de altas.
 */

require_once __DIR__ . '/_plantilla.php';

$sesion = exigir_administrador();
$aviso  = '';
$error  = '';

// --- Acciones -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido(isset($_POST['csrf']) ? $_POST['csrf'] : '')) {
        $error = 'La sesión del formulario caducó. Vuelva a intentarlo.';
    } else {
        $id     = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $accion = isset($_POST['accion']) ? (string) $_POST['accion'] : '';

        if ($accion === 'eliminar') {
            $resultado = eliminar_usuario($id);
            if (!empty($resultado['ok'])) {
                header('Location: usuarios.php?aviso='
                    . rawurlencode('Usuario «' . $resultado['username'] . '» eliminado.'));
                exit;
            }
            $error = $resultado['error'];

        } elseif ($accion === 'estado') {
            $activo = !empty($_POST['activar']) ? 1 : 0;

            $destino = $id > 0 ? buscar_usuario($id) : null;

            if ($id === $sesion['id'] && $activo === 0) {
                $error = 'No puede desactivar su propia cuenta.';
            } elseif ($activo === 0 && $destino && $destino['rol'] === ROL_ADMINISTRADOR
                      && !hay_otro_administrador($id)) {
                // Desactivar es otra forma de quitar el acceso: si se lo
                // quitamos al único administrador, nadie podría devolverlo.
                $error = 'No se puede desactivar la única cuenta de administrador activa.';
            } elseif ($id > 0) {
                try {
                    db()->prepare('UPDATE usuarios SET activo = ? WHERE id = ?')->execute(array($activo, $id));
                    header('Location: usuarios.php?aviso='
                        . rawurlencode($activo ? 'Cuenta activada.' : 'Cuenta desactivada.'));
                    exit;
                } catch (Exception $e) {
                    error_log('[altas] usuarios estado: ' . $e->getMessage());
                    $error = 'No fue posible actualizar la cuenta.';
                }
            }
        }
    }
}

// Aviso traído por la redirección de una acción anterior.
if ($aviso === '' && isset($_GET['aviso'])) {
    $aviso = limpiar_texto($_GET['aviso'], 200);
}

// --- Listado -----------------------------------------------------------
$usuarios = array();
try {
    $usuarios = db()->query(
        'SELECT id, username, nombre_completo, ci, telefono, correo, rol,
                red_salud, nombre_establecimiento, activo, ultimo_acceso, created_at
         FROM usuarios ORDER BY rol, nombre_completo'
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
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap" style="gap:10px">
    <h3 class="card-title mb-0">Usuarios del panel</h3>
    <a href="<?= h(ruta_base()) ?>registro.php" class="btn btn-sm btn-primary">Registrar usuario</a>
  </div>

  <div class="card-body table-responsive p-0">
    <table class="table table-sm table-hover mb-0 tabla-usuarios">
      <thead>
        <tr>
          <th>Usuario</th>
          <th>Nombre completo</th>
          <th>CI</th>
          <th>Teléfono</th>
          <th>Correo electrónico</th>
          <th>Rol</th>
          <th>Establecimiento</th>
          <th>Estado</th>
          <th>Último acceso</th>
          <th>Alta</th>
          <th class="text-right">Opciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$usuarios): ?>
          <tr><td colspan="11" class="text-center text-muted py-4">No hay usuarios registrados.</td></tr>
        <?php endif; ?>

        <?php foreach ($usuarios as $u): ?>
          <?php $propia = ((int) $u['id'] === $sesion['id']); ?>
          <tr>
            <td>
              <strong><?= h($u['username']) ?></strong>
              <?php if ($propia): ?>
                <span class="badge badge-secondary ml-1">Usted</span>
              <?php endif; ?>
            </td>
            <td><?= h($u['nombre_completo']) ?></td>
            <td><?= h($u['ci']) ?></td>
            <td class="text-nowrap"><?= h($u['telefono']) ?></td>
            <td><?= h($u['correo']) ?></td>
            <td>
              <?php if ($u['rol'] === ROL_ADMINISTRADOR): ?>
                <span class="badge badge-rol badge-rol--admin">Administrador</span>
              <?php else: ?>
                <span class="badge badge-rol badge-rol--operador">Operador</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($u['nombre_establecimiento']): ?>
                <?= h($u['nombre_establecimiento']) ?>
                <br><small class="text-muted"><?= h($u['red_salud']) ?></small>
              <?php else: ?>
                <span class="text-muted">Todos</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ((int) $u['activo'] === 1): ?>
                <span class="badge badge-success">Activa</span>
              <?php else: ?>
                <span class="badge badge-secondary">Desactivada</span>
              <?php endif; ?>
            </td>
            <td class="text-nowrap"><?= h(fecha_legible($u['ultimo_acceso'])) ?></td>
            <td class="text-nowrap"><?= h(fecha_legible($u['created_at'])) ?></td>

            <td class="text-right text-nowrap celda-acciones">
              <a href="usuario_editar.php?id=<?= (int) $u['id'] ?>" class="btn btn-xs btn-primary">Editar</a>

              <?php if (!$propia): ?>
                <form method="post" action="" class="d-inline">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <input type="hidden" name="accion" value="estado">
                  <?php if ((int) $u['activo'] === 1): ?>
                    <button type="submit" class="btn btn-xs btn-default">Desactivar</button>
                  <?php else: ?>
                    <input type="hidden" name="activar" value="1">
                    <button type="submit" class="btn btn-xs btn-success">Activar</button>
                  <?php endif; ?>
                </form>

                <!-- Confirmación antes de borrar: es la única acción de esta
                     pantalla que no se puede deshacer. -->
                <form method="post" action="" class="d-inline"
                      onsubmit="return confirm('¿Eliminar definitivamente al usuario «<?= h($u['username']) ?>»?\n\nEsta acción no se puede deshacer. Si solo quiere retirarle el acceso, use Desactivar.');">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <input type="hidden" name="accion" value="eliminar">
                  <button type="submit" class="btn btn-xs btn-danger">Eliminar</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card-footer text-muted small">
    <strong>Desactivar</strong> retira el acceso y conserva el registro de entradas;
    <strong>Eliminar</strong> borra la cuenta de forma definitiva. No se puede eliminar la
    propia cuenta ni la última que quede en el sistema.
    El <strong>administrador</strong> ve y administra todo; el <strong>operador</strong>
    registra altas y consulta el listado <strong>de su establecimiento</strong>. Siempre debe quedar al menos un administrador
    activo.
  </div>
</div>

<?php admin_pie(); ?>
