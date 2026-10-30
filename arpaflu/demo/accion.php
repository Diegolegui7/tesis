<?php
// Acciones de la guía de demostración: entrar como otro usuario, saltar un paso, reiniciar u ocultar la guía (solo en modo demostración)
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/demo.php';
require_once __DIR__ . '/../inc/instalador.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$fin = function ($d) { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; };

if (!MODO_DEMO || !es_post()) { http_response_code(403); $fin(['ok' => false, 'error' => 'No disponible']); }
if (!isset($_POST['csrf']) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    http_response_code(400);
    $fin(['ok' => false, 'error' => 'Solicitud no válida. Recargá la página.']);
}
$accion = isset($_POST['accion']) ? $_POST['accion'] : '';
try {
    if ($accion === 'entrar') {
        $correo = isset($_POST['correo']) ? $_POST['correo'] : '';
        $r = iniciar_sesion($correo, CLAVE_DEMO);
        if ($r !== true) { $fin(['ok' => false, 'error' => $r]); }
        $fin(['ok' => true, 'redirect' => inicio_segun_tipo(usuario_actual())]);
    }
    if ($accion === 'reiniciar') {
        reinstalar_base(true);
        unset($_SESSION['usuario']);
        $fin(['ok' => true, 'redirect' => base_url() . 'index.php']);
    }
    if (!demo_activa()) { $fin(['ok' => false, 'error' => 'La guía no está activa.']); }
    if ($accion === 'saltar') {
        $e = demo_estado();
        if ($e && $e['actual'] !== null) {
            $om = array_filter(explode(',', (string) demo_get('omitidos')));
            $om[] = (string) $e['pasos'][$e['actual']]['id'];
            demo_set('omitidos', implode(',', $om));
        }
        $fin(['ok' => true]);
    }
    if ($accion === 'ocultar') {
        demo_set('activo', '0');
        $fin(['ok' => true]);
    }
    $fin(['ok' => false, 'error' => 'Acción desconocida']);
} catch (Exception $ex) {
    http_response_code(500);
    $fin(['ok' => false, 'error' => 'No se pudo completar la acción.']);
}
