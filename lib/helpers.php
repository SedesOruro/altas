<?php
/**
 * Utilidades compartidas por los scripts PHP: respuestas JSON, validacion
 * de entrada y un limitador basico de intentos.
 */

/** Envia una respuesta JSON y termina la ejecucion. */
function responder($datos, $codigoHttp = 200)
{
    if (!headers_sent()) {
        http_response_code($codigoHttp);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Respuesta de error uniforme. */
function error_json($mensaje, $codigoHttp = 400, $extra = array())
{
    responder(array_merge(array('ok' => false, 'error' => $mensaje), $extra), $codigoHttp);
}

/** Exige un metodo HTTP concreto. */
function exigir_metodo($metodo)
{
    if (strtoupper($_SERVER['REQUEST_METHOD']) !== strtoupper($metodo)) {
        error_json('Método no permitido.', 405);
    }
}

/**
 * Lee el cuerpo de la peticion tanto si llega como JSON
 * (application/json) como si llega en formato formulario.
 */
function entrada_post()
{
    $tipo = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    if (stripos($tipo, 'application/json') !== false) {
        $crudo = file_get_contents('php://input');
        $datos = json_decode($crudo, true);
        return is_array($datos) ? $datos : array();
    }
    return $_POST;
}

/** Normaliza un valor de texto recibido del cliente. */
function limpiar_texto($valor, $maxLongitud = 255)
{
    if (!is_scalar($valor)) {
        return '';
    }
    $valor = (string) $valor;
    // Elimina caracteres de control salvo saltos de linea y tabulaciones.
    // Con UTF-8 invalido preg_replace devuelve null; en PHP 8 pasar null a
    // trim() esta obsoleto, asi que en ese caso se descarta la entrada.
    $limpio = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $valor);
    $valor  = trim($limpio === null ? '' : $limpio);
    if (function_exists('mb_substr')) {
        return mb_substr($valor, 0, $maxLongitud, 'UTF-8');
    }
    return substr($valor, 0, $maxLongitud);
}

/** Valida una fecha en formato YYYY-MM-DD. */
function fecha_valida($valor)
{
    if (!is_string($valor) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
        return false;
    }
    list($a, $m, $d) = explode('-', $valor);
    return checkdate((int) $m, (int) $d, (int) $a);
}

/** Valida una hora HH:MM (o HH:MM:SS) y la devuelve normalizada a HH:MM:SS. */
function hora_normalizada($valor)
{
    if (!is_string($valor) || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:([0-5]\d))?$/', $valor, $m)) {
        return null;
    }
    return sprintf('%s:%s:%s', $m[1], $m[2], isset($m[4]) && $m[4] !== '' ? $m[4] : '00');
}

/** Valida el formato del codigo de alta: ALTA-000001 */
function codigo_valido($codigo)
{
    return is_string($codigo) && preg_match('/^' . preg_quote(PREFIJO_CODIGO, '/') . '\d{6,}$/', strtoupper(trim($codigo))) === 1;
}

/** Normaliza el codigo tal como lo escribio el usuario (mayusculas, sin espacios). */
function normalizar_codigo($codigo)
{
    $codigo = strtoupper(trim((string) $codigo));
    $codigo = preg_replace('/\s+/', '', $codigo);
    // Permite que el usuario escriba solo el numero: "000012" o "12"
    if (preg_match('/^\d+$/', $codigo)) {
        $codigo = PREFIJO_CODIGO . str_pad($codigo, 6, '0', STR_PAD_LEFT);
    }
    return $codigo;
}

/** Busca un alta por su codigo. Devuelve el registro o null. */
function buscar_alta_por_codigo($codigo)
{
    $sql = 'SELECT * FROM altas WHERE codigo_alta = :codigo LIMIT 1';
    $st  = db()->prepare($sql);
    $st->execute(array(':codigo' => $codigo));
    $fila = $st->fetch();
    return $fila ? $fila : null;
}

/**
 * Limitador de intentos muy simple basado en archivos, pensado para frenar
 * la fuerza bruta sobre los codigos de alta en hostings compartidos donde
 * no hay Redis ni memcached disponibles.
 *
 * @param string $bucket  Nombre logico de la operacion (ej. "verificar").
 * @param int    $maximo  Intentos permitidos dentro de la ventana.
 * @param int    $ventana Duracion de la ventana en segundos.
 */
function limitar_intentos($bucket, $maximo = 30, $ventana = 300)
{
    $dir = __DIR__ . '/../data/rate';
    if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
        return; // Si no se puede escribir, no bloqueamos el servicio.
    }

    $ip     = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'cli';
    $ruta   = $dir . '/' . sha1($bucket . '|' . $ip) . '.json';
    $ahora  = time();
    $estado = array('inicio' => $ahora, 'contador' => 0);

    if (is_file($ruta)) {
        $previo = json_decode((string) @file_get_contents($ruta), true);
        if (is_array($previo) && isset($previo['inicio'], $previo['contador'])
            && ($ahora - (int) $previo['inicio']) < $ventana) {
            $estado = $previo;
        }
    }

    $estado['contador'] = (int) $estado['contador'] + 1;
    @file_put_contents($ruta, json_encode($estado), LOCK_EX);

    if ($estado['contador'] > $maximo) {
        $espera = $ventana - ($ahora - (int) $estado['inicio']);
        error_json(
            'Demasiados intentos seguidos. Espere ' . max(1, (int) ceil($espera / 60)) . ' minuto(s) e intente nuevamente.',
            429
        );
    }
}
