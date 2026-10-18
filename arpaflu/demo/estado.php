<?php
// Estado de la guía de demostración (lo consulta el panel cada dos segundos)
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/demo.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    if (!MODO_DEMO || !demo_activa()) {
        echo json_encode(['activo' => false]);
        exit;
    }
    $e = demo_estado();
    $u = usuario_actual();
    $e['activo'] = true;
    $e['usuario'] = $u ? $u['correo'] : null;
    echo json_encode($e, JSON_UNESCAPED_UNICODE);
} catch (Exception $ex) {
    echo json_encode(['activo' => false]);
}
