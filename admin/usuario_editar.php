<?php
/**
 * Panel — edición de un usuario.
 *
 * Uso:  usuario_editar.php?id=3
 *
 * Los mismos campos del registro (usuario, nombre completo, CI, teléfono y
 * correo) más un cambio de contraseña opcional: dejar esos dos campos
 * vacíos conserva la contraseña actual, que es lo que se espera cuando lo
 * que se viene a corregir es un teléfono mal escrito.
 *
 * La validación es la misma que usa el registro (`validar_datos_usuario`),
 * de modo que las dos pantallas no pueden divergir.
 */

require_once __DIR__ . '/_plantilla.php';
require_once __DIR__ . '/../lib/campos_establecimiento.php';

$sesion = exigir_administrador();
$base   = ruta_base();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$usuario = $id > 0 ? buscar_usuario($id) : null;
if (!$usuario) {
    admin_cabecera('Usuario no encontrado', 'usuarios');
    echo '<div class="alert alert-warning">El usuario solicitado no existe.</div>'
       . '<a href="usuarios.php" class="btn btn-primary">Volver a usuarios</a>';
    admin_pie();
    exit;
}

$errores = array();
$exito   = '';

// Lo que se muestra en el formulario: lo guardado, o lo que se acaba de
// enviar si hubo un error, para no obligar a teclearlo otra vez.
$datos = array(
    'username'        => $usuario['username'],
    'nombre_completo' => $usuario['nombre_completo'],
    'ci'              => $usuario['ci'],
    'telefono'        => $usuario['telefono'],
    'correo'          => $usuario['correo'],
    'rol'             => $usuario['rol'],
    'red_salud'              => $usuario['red_salud'],
    'nombre_establecimiento' => $usuario['nombre_establecimiento'],
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido(isset($_POST['csrf']) ? $_POST['csrf'] : '')) {
        $errores['general'] = 'La sesión del formulario caducó. Vuelva a intentarlo.';
    } else {
        foreach ($datos as $clave => $_) {
            $datos[$clave] = isset($_POST[$clave]) ? trim((string) $_POST[$clave]) : '';
        }

        $resultado = actualizar_usuario($id, $_POST);

        if (!empty($resultado['ok'])) {
            // Se vuelve al listado con el aviso, para no dejar un POST
            // colgado que se reenvíe al recargar.
            $aviso = 'Usuario «' . $datos['username'] . '» actualizado'
                   . (!empty($resultado['clave_cambiada']) ? ', incluida su contraseña' : '') . '.';
            header('Location: usuarios.php?aviso=' . rawurlencode($aviso));
            exit;
        }

        $errores = $resultado['errores'];
        $usuario = buscar_usuario($id) ?: $usuario;
    }
}

/** Mensaje de error de un campo, si lo hay. */
function error_campo($errores, $campo)
{
    return isset($errores[$campo])
        ? '<div class="invalid-feedback d-block">' . h($errores[$campo]) . '</div>'
        : '';
}

/** Clase de Bootstrap para marcar en rojo un campo con error. */
function clase_campo($errores, $campo)
{
    return isset($errores[$campo]) ? ' is-invalid' : '';
}

/** Fecha y hora legible, o un guion si nunca ocurrió. */
function fecha_corta($valor)
{
    if (!$valor) {
        return '—';
    }
    $ts = strtotime($valor);
    return $ts ? date('d/m/Y H:i', $ts) : '—';
}

$esPropia = ((int) $usuario['id'] === $sesion['id']);

admin_cabecera('Editar usuario', 'usuarios');
?>

<?php if (isset($errores['general'])): ?>
  <div class="alert alert-danger py-2"><?= h($errores['general']) ?></div>
<?php endif; ?>

<div class="row">
  <div class="col-lg-8">

    <div class="card">
      <div class="card-header">
        <h3 class="card-title mb-0">
          Datos de <strong><?= h($usuario['username']) ?></strong>
          <?php if ($esPropia): ?>
            <span class="badge badge-secondary ml-1">Su cuenta</span>
          <?php endif; ?>
        </h3>
      </div>

      <form method="post" action="" autocomplete="off">
        <div class="card-body">
          <?= csrf_campo() ?>

          <div class="form-row">
            <div class="form-group col-md-6">
              <label for="username">Usuario <span class="text-danger">*</span></label>
              <input type="text" class="form-control<?= clase_campo($errores, 'username') ?>" id="username"
                     name="username" maxlength="60" value="<?= h($datos['username']) ?>" required>
              <small class="form-text text-muted">Letras, números, punto, guion y guion bajo.</small>
              <?= error_campo($errores, 'username') ?>
            </div>

            <div class="form-group col-md-6">
              <label for="nombre_completo">Nombre completo <span class="text-danger">*</span></label>
              <input type="text" class="form-control<?= clase_campo($errores, 'nombre_completo') ?>" id="nombre_completo"
                     name="nombre_completo" maxlength="160" value="<?= h($datos['nombre_completo']) ?>" required>
              <?= error_campo($errores, 'nombre_completo') ?>
            </div>

            <div class="form-group col-md-6">
              <label for="ci">Cédula de identidad <span class="text-danger">*</span></label>
              <input type="text" class="form-control<?= clase_campo($errores, 'ci') ?>" id="ci"
                     name="ci" maxlength="30" value="<?= h($datos['ci']) ?>" required>
              <?= error_campo($errores, 'ci') ?>
            </div>

            <div class="form-group col-md-6">
              <label for="telefono">Teléfono <span class="text-danger">*</span></label>
              <input type="tel" class="form-control<?= clase_campo($errores, 'telefono') ?>" id="telefono"
                     name="telefono" maxlength="30" value="<?= h($datos['telefono']) ?>" required>
              <?= error_campo($errores, 'telefono') ?>
            </div>

            <div class="form-group col-md-6">
              <label for="correo">Correo electrónico <span class="text-danger">*</span></label>
              <input type="email" class="form-control<?= clase_campo($errores, 'correo') ?>" id="correo"
                     name="correo" maxlength="160" value="<?= h($datos['correo']) ?>" required>
              <?= error_campo($errores, 'correo') ?>
            </div>

            <div class="form-group col-md-6">
              <label for="rol">Rol <span class="text-danger">*</span></label>
              <select class="form-control<?= clase_campo($errores, 'rol') ?>" id="rol" name="rol" required>
                <?php foreach (roles_disponibles() as $valor => $etiqueta): ?>
                  <option value="<?= h($valor) ?>"<?= $datos['rol'] === $valor ? ' selected' : '' ?>>
                    <?= h($etiqueta) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="form-text text-muted">
                El administrador ve y administra todo; el operador registra altas y consulta el listado.
                <?php if ($esPropia): ?>
                  Cuidado: está editando su propia cuenta.
                <?php endif; ?>
              </small>
              <?= error_campo($errores, 'rol') ?>
            </div>
          </div>

          <div class="form-row">
            <?php campos_establecimiento($datos, $errores); ?>
          </div>

          <hr>

          <p class="mb-2"><strong>Cambiar contraseña</strong>
            <span class="text-muted">— opcional</span></p>
          <p class="text-muted small mt-0">
            Deje los dos campos vacíos para conservar la contraseña actual.
          </p>

          <div class="form-row">
            <div class="form-group col-md-6">
              <label for="clave">Contraseña nueva</label>
              <input type="password" class="form-control<?= clase_campo($errores, 'clave') ?>" id="clave"
                     name="clave" autocomplete="new-password">
              <small class="form-text text-muted">Mínimo 8 caracteres.</small>
              <?= error_campo($errores, 'clave') ?>
            </div>

            <div class="form-group col-md-6">
              <label for="clave_repetida">Repetir contraseña nueva</label>
              <input type="password" class="form-control<?= clase_campo($errores, 'clave_repetida') ?>"
                     id="clave_repetida" name="clave_repetida" autocomplete="new-password">
              <?= error_campo($errores, 'clave_repetida') ?>
            </div>
          </div>
        </div>

        <div class="card-footer d-flex flex-wrap" style="gap:10px">
          <button type="submit" class="btn btn-primary">Guardar cambios</button>
          <a href="usuarios.php" class="btn btn-default">Cancelar</a>
        </div>
      </form>
    </div>

  </div>

  <div class="col-lg-4">

    <div class="card">
      <div class="card-header"><h3 class="card-title mb-0">Estado de la cuenta</h3></div>
      <div class="card-body">
        <p class="mb-2">
          <?php if ((int) $usuario['activo'] === 1): ?>
            <span class="badge badge-success">Activa</span>
          <?php else: ?>
            <span class="badge badge-secondary">Desactivada</span>
          <?php endif; ?>
        </p>
        <p class="text-muted small mb-1">Último acceso: <?= h(fecha_corta($usuario['ultimo_acceso'])) ?></p>
        <p class="text-muted small mb-0">Alta: <?= h(fecha_corta($usuario['created_at'])) ?></p>
      </div>
    </div>

    <!-- Las acciones que no se pueden deshacer van aparte y avisadas: no
         deben quedar al lado de «Guardar cambios», donde se pulsan por
         inercia. -->
    <div class="card card-peligro">
      <div class="card-header"><h3 class="card-title mb-0">Eliminar usuario</h3></div>
      <div class="card-body">
        <?php if ($esPropia): ?>
          <p class="text-muted mb-0">
            No puede eliminar su propia cuenta. Pida a otro usuario del panel que lo haga.
          </p>
        <?php else: ?>
          <p class="small mb-3">
            El borrado es <strong>definitivo</strong>: no hay papelera. Si lo que quiere es
            retirarle el acceso sin perder el registro de sus entradas,
            <a href="usuarios.php">desactive la cuenta</a> en su lugar.
          </p>
          <form method="post" action="usuarios.php"
                onsubmit="return confirm('¿Eliminar definitivamente al usuario «<?= h($usuario['username']) ?>»?\n\nEsta acción no se puede deshacer.');">
            <?= csrf_campo() ?>
            <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
            <input type="hidden" name="accion" value="eliminar">
            <button type="submit" class="btn btn-danger btn-block">
              Eliminar «<?= h($usuario['username']) ?>»
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<?php script_establecimiento($base); ?>

<?php admin_pie(); ?>
