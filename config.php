<?php
/**
 * Conexion a MySQL (PDO) y constantes globales de la aplicacion.
 *
 * Prioridad de configuracion:
 *   1. Variables de entorno del servidor (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS)
 *   2. Archivo config.local.php (no versionado)
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// ---------------------------------------------------------------------
// 1) Carga de credenciales
// ---------------------------------------------------------------------
$__localConfig = __DIR__ . '/config.local.php';
if (is_readable($__localConfig)) {
    require_once $__localConfig;
}

function cfg($clave, $porDefecto = null)
{
    // getenv() cubre las variables del entorno del contenedor; $_SERVER cubre
    // el caso de Apache con PassEnv, donde algunas SAPI no las exponen en getenv().
    $env = getenv($clave);
    if (($env === false || $env === '') && isset($_SERVER[$clave])) {
        $env = $_SERVER[$clave];
    }
    if ($env !== false && $env !== '') {
        return $env;
    }
    if (defined($clave)) {
        return constant($clave);
    }
    return $porDefecto;
}

/**
 * Lee un valor booleano de configuración. Necesario porque una variable de
 * entorno siempre llega como texto y (bool) "false" sería true, lo que
 * dejaría el modo depuración encendido en producción.
 */
function cfg_bool($clave, $porDefecto = false)
{
    $valor = cfg($clave, $porDefecto);
    if (is_bool($valor)) {
        return $valor;
    }
    return !in_array(strtolower(trim((string) $valor)), array('', '0', 'false', 'off', 'no'), true);
}

// ---------------------------------------------------------------------
// 2) Constantes de la aplicacion
// ---------------------------------------------------------------------
define('APP_DEBUG_MODE', cfg_bool('APP_DEBUG', false));

/** Carpeta fisica donde se guardan los documentos firmados escaneados. */
define('UPLOAD_DIR', __DIR__ . '/uploads/altas');

/** Tamano maximo permitido por archivo adjunto: 10 MB. */
define('MAX_UPLOAD_BYTES', 10 * 1024 * 1024);

/** Tipos MIME reales aceptados en la seccion "Subir Alta". */
$GLOBALS['MIME_PERMITIDOS'] = array(
    'application/pdf' => 'pdf',
    'image/jpeg'      => 'jpg',
);

/** Prefijo del codigo correlativo de alta. */
define('PREFIJO_CODIGO', 'ALTA-');

// ---------------------------------------------------------------------
// 3) Manejo de errores: nunca mostrarlos al cliente en produccion
// ---------------------------------------------------------------------
if (APP_DEBUG_MODE) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}
ini_set('log_errors', '1');

date_default_timezone_set(cfg('APP_TIMEZONE', 'America/La_Paz'));

// ---------------------------------------------------------------------
// 4) Conexion PDO (perezosa: se abre solo cuando se necesita)
// ---------------------------------------------------------------------
function db()
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        cfg('DB_HOST', 'localhost'),
        cfg('DB_PORT', '3306'),
        cfg('DB_NAME', '')
    );

    try {
        $pdo = new PDO($dsn, cfg('DB_USER', ''), cfg('DB_PASS', ''), array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ));
    } catch (PDOException $e) {
        error_log('[altas] Error de conexion a MySQL: ' . $e->getMessage());
        throw new RuntimeException('No fue posible conectar con la base de datos.');
    }

    return $pdo;
}

require_once __DIR__ . '/lib/helpers.php';
