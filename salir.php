<?php
/**
 * salir.php — cierra la sesion del panel y vuelve al inicio de sesion.
 */

require_once __DIR__ . '/lib/auth.php';

$base = ruta_base();
cerrar_sesion();

header('Location: ' . $base . 'login.php');
exit;
