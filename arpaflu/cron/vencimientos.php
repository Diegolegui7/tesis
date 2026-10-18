<?php
// P9 · Control periódico de vencimientos. Solo por línea de comandos (cron en Linux o Programador de tareas en Windows):
//   php cron/vencimientos.php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script se ejecuta solo desde la línea de comandos.');
}
require_once __DIR__ . '/../inc/vencimientos.php';
$r = controlar_vencimientos();
echo date('Y-m-d H:i:s') . ' · recordatorios: ' . $r['recordatorios'] . ' · notado: ' . $r['notado'] . ' · fallidos: ' . $r['fallidos'] . "\n";
