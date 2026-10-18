<?php
// Conexión a la base de datos (PDO)
require_once __DIR__ . '/../config.php';

// Ante un error inesperado de la base (por ejemplo, un dato demasiado largo) se muestra un mensaje claro y no el detalle técnico
set_exception_handler(function ($e) {
    $largo = $e instanceof PDOException && (string) $e->getCode() === '22001';
    http_response_code($largo ? 400 : 500);
    error_log('ARPAFLU: ' . get_class($e) . ': ' . $e->getMessage());
    exit('<p style="font-family:sans-serif;padding:24px">' . ($largo
        ? 'No se pudo guardar: alguno de los datos supera el largo permitido. Volvé a la página anterior y acortalo.'
        : 'Ocurrió un error inesperado y no se guardó nada. Volvé a la página anterior y reintentá; si se repite, avisá al administrador.')
        . ' <a href="javascript:history.back()">Volver</a></p>');
});

function db()
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            exit('<p style="font-family:sans-serif;padding:24px">No se pudo conectar a la base de datos. '
                . 'Si es la primera vez, abrí <a href="' . htmlspecialchars(base_url() . 'instalar.php') . '">instalar.php</a>.</p>');
        }
    }
    return $pdo;
}

// Dirección base del sistema (por ejemplo /arpaflu/), para armar enlaces absolutos
function base_url()
{
    static $base = null;
    if ($base === null) {
        $raiz = str_replace('\\', '/', realpath(__DIR__ . '/..'));
        $doc = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
        $base = ($doc !== '' && strpos($raiz, $doc) === 0) ? substr($raiz, strlen($doc)) : '';
        $base = rtrim($base, '/') . '/';
    }
    return $base;
}

// Atajos de consulta
function consulta($sql, $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function fila($sql, $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $r = $st->fetch();
    return $r === false ? null : $r;
}

function valor($sql, $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $v = $st->fetchColumn();
    return $v === false ? null : $v;
}

function ejecutar($sql, $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}
