<?php
// Instalación de la base de datos de prueba: crea la base, las tablas y los datos de ejemplo.
// Lo usan instalar.php, demo/preparar.php (el .bat de la demostración) y el botón «Reiniciar demo».
require_once __DIR__ . '/../config.php';

function sentencias_sql($archivo)
{
    $sql = file_get_contents($archivo);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);          // saca los comentarios
    $partes = preg_split('/;\s*[\r\n]+/', $sql);
    return array_values(array_filter(array_map('trim', $partes), function ($s) { return $s !== ''; }));
}

// Borra y vuelve a crear todo. Con $demo activa la guía de demostración. Devuelve la lista de pasos hechos; lanza una excepción si falla.
function reinstalar_base($demo = false)
{
    $log = [];
    $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $nombre = str_replace('`', '', DB_NAME);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $nombre . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . $nombre . '`');
    $log[] = 'Base de datos "' . DB_NAME . '" lista.';

    foreach (sentencias_sql(__DIR__ . '/../database/schema.sql') as $s) {
        $pdo->exec($s);
    }
    $log[] = 'Tablas creadas.';

    $hash = password_hash(CLAVE_DEMO, PASSWORD_DEFAULT);
    $n = 0;
    foreach (sentencias_sql(__DIR__ . '/../database/seed.sql') as $s) {
        $pdo->exec(str_replace('{{HASH}}', $hash, $s));
        $n++;
    }
    $log[] = 'Datos de ejemplo cargados (' . $n . ' sentencias).';

    if (!is_dir(DIR_ALMACEN)) {
        mkdir(DIR_ALMACEN, 0775, true);
    }
    file_put_contents(DIR_ALMACEN . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    $log[] = 'Depósito de documentos listo (almacen/).';

    if ($demo) {
        $pdo->exec("INSERT INTO demo (clave, valor) VALUES ('activo','1'),('omitidos',''),('inicio', NOW())");
        $log[] = 'Guía de demostración activada.';
    }
    return $log;
}
