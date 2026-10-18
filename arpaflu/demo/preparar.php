<?php
// Prepara la demostración: espera a MySQL (abre WAMP si hace falta) y carga los datos de ejemplo con la guía activada. Se ejecuta desde INICIAR_DEMO.bat
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo por consola.'); }
require_once __DIR__ . '/../inc/instalador.php';

function mysql_responde()
{
    try {
        new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

echo "Buscando MySQL...\n";
if (!mysql_responde()) {
    $wamp = 'C:\wamp64\wampmanager.exe';
    if (file_exists($wamp)) {
        echo "MySQL no responde. Abriendo WAMP (espere a que el icono se ponga verde)...\n";
        pclose(popen('start "" "' . $wamp . '"', 'r'));
    } else {
        echo "MySQL no responde. Abra WAMP y espere a que el icono se ponga verde...\n";
    }
    for ($i = 0; $i < 60 && !mysql_responde(); $i++) {
        sleep(2);
        echo '.';
    }
    echo "\n";
    if (!mysql_responde()) {
        echo "No se pudo conectar a MySQL. Abra WAMP, espere el icono verde y vuelva a ejecutar este archivo.\n";
        exit(2);
    }
}
echo "MySQL listo. Cargando los datos de la demostracion...\n";
try {
    foreach (reinstalar_base(true) as $l) { echo '  - ' . $l . "\n"; }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
exit(0);
