<?php
/**
 * registro.php — alta de usuarios del panel.
 *
 * Quién puede usar esta página:
 *   - Cualquiera, mientras no exista ningún usuario, para poder crear el
 *     primer administrador tras la instalación.
 *   - A partir de ahí, solo quien ya tenga sesión abierta.
 *
 * Es deliberado: el panel muestra datos clínicos identificables, así que
 * dejar el registro abierto al público equivaldría a repartir las llaves.
 * Si se prefiere un registro abierto, basta con quitar la comprobación de
 * `$registroAbierto` de más abajo.
 */

require_once __DIR__ . '/lib/auth.php';

$base            = ruta_base();
$primerArranque  = sin_usuarios();
$sesion          = usuario_actual();
$registroAbierto = $primerArranque || $sesion !== null;

$errores = array();
$exito   = '';
$datos   = array(
    'username' => '', 'nombre_completo' => '', 'ci' => '', 'telefono' => '', 'correo' => '',
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$registroAbierto) {
        $errores['general'] = 'El registro de usuarios está reservado al personal que ya tiene acceso.';
    } elseif (!csrf_valido(isset($_POST['csrf']) ? $_POST['csrf'] : '')) {
        $errores['general'] = 'La sesión del formulario caducó. Vuelva a intentarlo.';
    } else {
        limitar_intentos('registro', 10, 600);

        foreach ($datos as $clave => $_) {
            $datos[$clave] = isset($_POST[$clave]) ? trim((string) $_POST[$clave]) : '';
        }

        $resultado = registrar_usuario($_POST);

        if (!empty($resultado['ok'])) {
            if ($primerArranque) {
                // El primer administrador entra directo: no tendría sentido
                // pedirle que inicie sesión justo después de crearse.
                iniciar_sesion($datos['username'], isset($_POST['clave']) ? $_POST['clave'] : '');
                header('Location: ' . $base . 'admin/index.php');
                exit;
            }
            $exito = 'Usuario «' . $datos['username'] . '» registrado correctamente.';
            $datos = array('username' => '', 'nombre_completo' => '', 'ci' => '', 'telefono' => '', 'correo' => '');
        } else {
            $errores = $resultado['errores'];
        }
    }
}

/** Imprime el mensaje de error de un campo, si lo hay. */
function error_de($errores, $campo)
{
    return isset($errores[$campo])
        ? '<div class="invalid-feedback d-block">' . h($errores[$campo]) . '</div>'
        : '';
}

/** Clase de Bootstrap para marcar en rojo un campo con error. */
function clase_de($errores, $campo)
{
    return isset($errores[$campo]) ? ' is-invalid' : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Registro de usuario — Alta Solicitada</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= h($base) ?>assets/logo-sedes.png" type="image/png">
<link rel="stylesheet" href="<?= h($base) ?>assets/vendor/adminlte.min.css">
<link rel="stylesheet" href="<?= h($base) ?>assets/admin.css?v=13">
</head>
<body class="pagina-acceso">

<div class="caja-acceso caja-acceso--ancha">

  <div class="acceso-marca">
    <img src="<?= h($base) ?>assets/logo-sedes.png" alt="Logotipo del Servicio Departamental de Salud de Oruro">
    <h1><?= $primerArranque ? 'Primer administrador' : 'Registro de usuario' ?></h1>
    <p>SEDES Oruro</p>
  </div>

  <div class="card">
    <div class="card-body">

      <?php if ($primerArranque): ?>
        <div class="alert alert-info py-2">
          Esta es la primera cuenta del sistema. Al crearla entrará directamente al panel.
        </div>
      <?php elseif (!$registroAbierto): ?>
        <div class="alert alert-warning py-2">
          El registro de usuarios está reservado al personal autorizado.
          <a href="<?= h($base) ?>login.php">Inicie sesión</a> para crear una cuenta nueva.
        </div>
      <?php endif; ?>

      <?php if ($exito): ?>
        <div class="alert alert-success py-2"><?= h($exito) ?></div>
      <?php endif; ?>

      <?php if (isset($errores['general'])): ?>
        <div class="alert alert-danger py-2"><?= h($errores['general']) ?></div>
      <?php endif; ?>

      <?php if ($registroAbierto): ?>
      <form method="post" action="" autocomplete="off">
        <?= csrf_campo() ?>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label for="username">Usuario <span class="text-danger">*</span></label>
            <input type="text" class="form-control<?= clase_de($errores, 'username') ?>" id="username"
                   name="username" maxlength="60" value="<?= h($datos['username']) ?>" required>
            <small class="form-text text-muted">Letras, números, punto, guion y guion bajo.</small>
            <?= error_de($errores, 'username') ?>
          </div>

          <div class="form-group col-md-6">
            <label for="nombre_completo">Nombre completo <span class="text-danger">*</span></label>
            <input type="text" class="form-control<?= clase_de($errores, 'nombre_completo') ?>" id="nombre_completo"
                   name="nombre_completo" maxlength="160" value="<?= h($datos['nombre_completo']) ?>" required>
            <?= error_de($errores, 'nombre_completo') ?>
          </div>

          <div class="form-group col-md-6">
            <label for="ci">Cédula de identidad <span class="text-danger">*</span></label>
            <input type="text" class="form-control<?= clase_de($errores, 'ci') ?>" id="ci"
                   name="ci" maxlength="30" value="<?= h($datos['ci']) ?>" required>
            <?= error_de($errores, 'ci') ?>
          </div>

          <div class="form-group col-md-6">
            <label for="telefono">Teléfono <span class="text-danger">*</span></label>
            <input type="tel" class="form-control<?= clase_de($errores, 'telefono') ?>" id="telefono"
                   name="telefono" maxlength="30" value="<?= h($datos['telefono']) ?>" required>
            <?= error_de($errores, 'telefono') ?>
          </div>

          <div class="form-group col-12">
            <label for="correo">Correo electrónico <span class="text-danger">*</span></label>
            <input type="email" class="form-control<?= clase_de($errores, 'correo') ?>" id="correo"
                   name="correo" maxlength="160" value="<?= h($datos['correo']) ?>" required>
            <?= error_de($errores, 'correo') ?>
          </div>

          <div class="form-group col-md-6">
            <label for="clave">Contraseña <span class="text-danger">*</span></label>
            <input type="password" class="form-control<?= clase_de($errores, 'clave') ?>" id="clave"
                   name="clave" autocomplete="new-password" required>
            <small class="form-text text-muted">Mínimo 8 caracteres.</small>
            <?= error_de($errores, 'clave') ?>
          </div>

          <div class="form-group col-md-6">
            <label for="clave_repetida">Repetir contraseña <span class="text-danger">*</span></label>
            <input type="password" class="form-control<?= clase_de($errores, 'clave_repetida') ?>" id="clave_repetida"
                   name="clave_repetida" autocomplete="new-password" required>
            <?= error_de($errores, 'clave_repetida') ?>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block">
          <?= $primerArranque ? 'Crear administrador y entrar' : 'Registrar usuario' ?>
        </button>
      </form>
      <?php endif; ?>

    </div>
  </div>

  <p class="enlace-pie">
    <?php if ($sesion): ?>
      <a href="<?= h($base) ?>admin/usuarios.php">← Volver a usuarios</a>
    <?php else: ?>
      <a href="<?= h($base) ?>login.php">← Volver al inicio de sesión</a>
    <?php endif; ?>
  </p>

</div>

</body>
</html>
