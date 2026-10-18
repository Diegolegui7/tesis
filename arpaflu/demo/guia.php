<?php
// Guía de la demostración en la consola: muestra el paso actual y avisa en tiempo real cuando se completa. Se ejecuta desde INICIAR_DEMO.bat
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo por consola.'); }
require_once __DIR__ . '/../inc/demo.php';

$url = 'http://127.0.0.1:' . (isset($argv[1]) ? $argv[1] : '8088') . '/';
$raya = str_repeat('=', 70);
echo "\n$raya\n  DEMO GUIADA PASO A PASO\n$raya\n";
echo "  El sistema esta abierto en el navegador: $url\n";
echo "  Tambien vas a ver un panel naranja abajo a la izquierda en cada pantalla.\n";
echo "  Esta ventana avisa, en tiempo real, cuando completas cada paso.\n";
echo "  Para terminar, cerra esta ventana o presiona Ctrl+C.\n$raya\n";

$vistos = -1;
while (true) {
    try { $e = demo_estado(); } catch (Exception $ex) { $e = null; }
    if (!$e) { echo "\nLa guia no esta activa. Ejecuta INICIAR_DEMO.bat de nuevo.\n"; exit(1); }
    $hechos = 0;
    foreach ($e['pasos'] as $p) { if ($p['hecho']) { $hechos++; } }
    if ($hechos < $vistos) { echo "\n--- La demo se reinicio ---\n"; $vistos = -1; }
    if ($hechos !== $vistos) {
        for ($i = max(0, $vistos); $i < $hechos; $i++) {
            echo "\n  [OK] Paso " . ($i + 1) . " completado: " . $e['pasos'][$i]['titulo'] . "\n";
        }
        if ($e['terminado']) {
            echo "\n$raya\n  LISTO! Recorriste todo el circuito de una reserva.\n  Podes reiniciar la demo desde el panel naranja y probar otro camino.\n$raya\n";
            break;
        }
        $p = $e['pasos'][$e['actual']];
        echo "\n  >>> PASO " . ($e['actual'] + 1) . " DE " . $e['total'] . ": " . $p['titulo'] . "\n";
        echo '      Lo hace: ' . $p['quien_nombre'] . "\n";
        echo '      ' . str_replace("\n", "\n      ", wordwrap($p['texto'], 64, "\n")) . "\n";
        echo "      (esperando que lo hagas...)\n";
        $vistos = $hechos;
    }
    sleep(1);
}
exit(0);
