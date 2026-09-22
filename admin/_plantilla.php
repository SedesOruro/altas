<?php
/**
 * Armazón común de las páginas del panel (AdminLTE 3).
 *
 * AdminLTE y sus dependencias (jQuery y Bootstrap) están incluidos en
 * `assets/vendor/`, no se cargan desde una CDN: el panel funciona igual en
 * una intranet sin salida a internet. Los iconos son SVG en línea, así se
 * evita traer toda una tipografía de iconos para media docena de dibujos.
 */

require_once __DIR__ . '/../lib/auth.php';

/** Pequeño juego de iconos SVG usado en el menú y en los botones. */
function icono($nombre, $tamano = 18)
{
    $trazos = array(
        'panel'     => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/>'
                     . '<rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'usuarios'  => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>'
                     . '<path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'salir'     => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'documento' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
        'subido'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/>',
        'inicio'    => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V10"/>',
        'buscar'    => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
    );

    $d = isset($trazos[$nombre]) ? $trazos[$nombre] : $trazos['documento'];

    return '<svg class="icono" width="' . (int) $tamano . '" height="' . (int) $tamano . '" viewBox="0 0 24 24" '
         . 'fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" '
         . 'aria-hidden="true">' . $d . '</svg>';
}

/**
 * Abre la página: cabecera HTML, barra superior, menú lateral y el inicio
 * del contenido.
 *
 * @param string $titulo   Título de la página.
 * @param string $seccion  Clave del menú que queda marcada como activa.
 */
function admin_cabecera($titulo, $seccion = 'panel')
{
    $usuario = usuario_actual();
    $base    = ruta_base();
    $menu    = array(
        'panel'    => array('Altas registradas', 'index.php',    'panel'),
        'usuarios' => array('Usuarios',          'usuarios.php', 'usuarios'),
    );
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($titulo) ?> — Panel Alta Solicitada</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?= h($base) ?>assets/logo-sedes.png" type="image/png">
<link rel="stylesheet" href="<?= h($base) ?>assets/vendor/adminlte.min.css">
<link rel="stylesheet" href="<?= h($base) ?>assets/admin.css?v=13">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

  <!-- Barra superior -->
  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button" aria-label="Mostrar u ocultar el menú">
          <span class="hamburguesa"></span>
        </a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="<?= h($base) ?>index.html" class="nav-link"><?= icono('inicio', 16) ?> Ir al sitio</a>
      </li>
    </ul>
    <ul class="navbar-nav ml-auto">
      <li class="nav-item">
        <span class="navbar-text mr-2 d-none d-sm-inline">
          <?= h($usuario ? $usuario['nombre_completo'] : '') ?>
        </span>
      </li>
      <li class="nav-item">
        <a href="<?= h($base) ?>salir.php" class="nav-link" title="Cerrar sesión">
          <?= icono('salir', 16) ?> <span class="d-none d-sm-inline">Salir</span>
        </a>
      </li>
    </ul>
  </nav>

  <!-- Menú lateral -->
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="index.php" class="brand-link">
      <img src="<?= h($base) ?>assets/logo-sedes.png" alt="" class="brand-image">
      <span class="brand-text">Alta Solicitada</span>
    </a>
    <div class="sidebar">
      <nav class="mt-3">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
          <?php foreach ($menu as $clave => $entrada): ?>
            <li class="nav-item">
              <a href="<?= h($entrada[1]) ?>" class="nav-link<?= $clave === $seccion ? ' active' : '' ?>">
                <?= icono($entrada[2]) ?>
                <p><?= h($entrada[0]) ?></p>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </nav>
    </div>
  </aside>

  <div class="content-wrapper">
    <div class="content-header">
      <div class="container-fluid">
        <h1 class="titulo-pagina"><?= h($titulo) ?></h1>
      </div>
    </div>
    <section class="content">
      <div class="container-fluid">
<?php
}

/** Cierra la página y carga los scripts. */
function admin_pie($scripts = array())
{
    $base = ruta_base();
    ?>
      </div>
    </section>
  </div>

  <footer class="main-footer">
    <small>SEDES Oruro — Sistema de Notificación de Alta Solicitada</small>
  </footer>
</div>

<script src="<?= h($base) ?>assets/vendor/jquery.min.js"></script>
<script src="<?= h($base) ?>assets/vendor/bootstrap.bundle.min.js"></script>
<script src="<?= h($base) ?>assets/vendor/adminlte.min.js"></script>
<?php foreach ($scripts as $src): ?>
<script src="<?= h($base . $src) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}
