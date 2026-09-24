<?php
/**
 * login.php — acceso al panel de administración.
 *
 * Formulario clásico (POST + redirección), no AJAX: así el navegador puede
 * ofrecer guardar la contraseña y la sesión queda escrita antes de pasar al
 * panel. Incluye testigo anti-CSRF y límite de intentos por IP.
 */

require_once __DIR__ . '/lib/auth.php';

$error   = '';
$usuario = '';

// A dónde ir después de entrar. Solo se aceptan rutas internas, para que
// nadie pueda usar ?volver= como trampolín hacia otro sitio.
$volver = isset($_GET['volver']) ? (string) $_GET['volver'] : '';
if ($volver === '' || $volver[0] !== '/' || strpos($volver, '//') === 0) {
    $volver = ruta_base() . 'admin/index.php';
}

// Quien ya tiene sesión no necesita ver esta página.
if (hay_sesion()) {
    header('Location: ' . $volver);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = isset($_POST['usuario']) ? trim((string) $_POST['usuario']) : '';

    if (!csrf_valido(isset($_POST['csrf']) ? $_POST['csrf'] : '')) {
        $error = 'La sesión del formulario caducó. Vuelva a intentarlo.';
    } else {
        // Freno a la fuerza bruta: 10 intentos cada 10 minutos por IP.
        limitar_intentos('login', 10, 600);

        $resultado = iniciar_sesion($usuario, isset($_POST['clave']) ? $_POST['clave'] : '');

        if (is_array($resultado)) {
            header('Location: ' . $volver);
            exit;
        }
        $error = $resultado;
    }
}

$base           = ruta_base();
$primerArranque = sin_usuarios();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Inicio de sesión — Alta Solicitada</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= h($base) ?>assets/logo-sedes.png" type="image/png">
<link rel="stylesheet" href="<?= h($base) ?>assets/vendor/adminlte.min.css">
<link rel="stylesheet" href="<?= h($base) ?>assets/admin.css?v=15">
</head>
<body class="pagina-acceso">

<div class="caja-acceso">

  <div class="acceso-marca">
    <img src="<?= h($base) ?>assets/logo-sedes.png" alt="Logotipo del Servicio Departamental de Salud de Oruro">
    <h1>Alta Solicitada</h1>
    <p>SEDES Oruro</p>
  </div>

  <div class="card">
    <div class="card-body">
      <h2 class="h6 mb-3">Inicie sesión para entrar al panel</h2>

      <?php if (isset($_GET['motivo']) && $_GET['motivo'] === 'cuenta'): ?>
        <div class="alert alert-warning py-2 mb-3">
          Su sesión se cerró porque la cuenta ya no está disponible.
          Consulte con el administrador del sistema.
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-danger py-2 mb-3"><?= h($error) ?></div>
      <?php endif; ?>

      <?php if ($primerArranque): ?>
        <div class="alert alert-info py-2 mb-3">
          Todavía no hay ningún usuario registrado.
          <a href="<?= h($base) ?>registro.php">Cree el primer administrador</a>.
        </div>
      <?php endif; ?>

      <form method="post" action="">
        <?= csrf_campo() ?>

        <div class="form-group">
          <label for="usuario">Usuario o correo electrónico</label>
          <input type="text" class="form-control" id="usuario" name="usuario"
                 value="<?= h($usuario) ?>" autocomplete="username" required autofocus>
        </div>

        <div class="form-group">
          <label for="clave">Contraseña</label>
          <input type="password" class="form-control" id="clave" name="clave"
                 autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Ingresar</button>
      </form>
    </div>
  </div>

  <p class="enlace-pie">
    <a href="<?= h($base) ?>index.html">← Volver al inicio</a>
  </p>

</div>

</body>
</html>
