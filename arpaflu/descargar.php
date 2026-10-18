<?php
// SS4 · Descarga de documentos del depósito (almacen/), con control de permisos
require_once __DIR__ . '/inc/auth.php';

$u = usuario_actual();
if (!$u) {
    redirigir(base_url() . 'index.php');
}

$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'doc';
$id = (int) (isset($_GET['id']) ? $_GET['id'] : 0);
$ruta = null;
$nombre = null;

if ($tipo === 'cot') {                       // archivo de una cotización: solo personal con permiso
    if (!puede('cotizaciones')) {
        http_response_code(403);
        exit('Sin permiso.');
    }
    $r = fila('SELECT archivo_ruta, archivo_nombre FROM cotizaciones WHERE id = ?', [$id]);
    if ($r) { $ruta = $r['archivo_ruta']; $nombre = $r['archivo_nombre']; }
} else {                                     // documento de una operación
    $r = fila('SELECT d.archivo_ruta, d.archivo_nombre, d.visible_cliente, o.cliente_id FROM documentos d JOIN operaciones o ON o.id = d.operacion_id WHERE d.id = ?', [$id]);
    if ($r) {
        $permitido = $u['tipo'] === 'empleado'
            ? puede('ver_operaciones')
            : ((int) $r['cliente_id'] === (int) $u['cliente_id'] && (int) $r['visible_cliente'] === 1);
        if (!$permitido) {
            http_response_code(403);
            exit('Sin permiso.');
        }
        $ruta = $r['archivo_ruta'];
        $nombre = $r['archivo_nombre'];
    }
}

if (!$ruta) {
    http_response_code(404);
    exit('El documento no existe o no tiene archivo adjunto.');
}
$base = realpath(DIR_ALMACEN);
$archivo = realpath(DIR_ALMACEN . '/' . $ruta);
if ($base === false || $archivo === false || strpos($archivo, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($archivo)) {
    http_response_code(404);
    exit('No se encontró el archivo en el depósito.');
}

$ext = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
$tipos = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'txt' => 'text/plain; charset=utf-8'];
$inline = isset($tipos[$ext]);
header('Content-Type: ' . ($inline ? $tipos[$ext] : 'application/octet-stream'));
header('Content-Length: ' . filesize($archivo));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '', $nombre ?: basename($archivo)) . '"');
readfile($archivo);
