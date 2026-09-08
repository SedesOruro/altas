<?php
/**
 * Plantilla de configuracion. Copiar como "config.local.php" y completar
 * con las credenciales reales del servidor MySQL.
 *
 *   cp config.example.php config.local.php
 *
 * config.local.php NO debe subirse al control de versiones (ver .gitignore).
 * Si el hosting permite variables de entorno (DB_HOST, DB_PORT, DB_NAME,
 * DB_USER, DB_PASS), estas tienen prioridad y este archivo no es necesario.
 */

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'sedes_altas');
define('DB_USER', 'usuario_mysql');
define('DB_PASS', 'clave_mysql');

// Poner en false en produccion para no mostrar detalles de errores al cliente.
define('APP_DEBUG', false);
