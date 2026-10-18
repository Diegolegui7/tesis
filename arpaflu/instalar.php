<?php
// Instalador para pruebas locales: crea la base de datos, las tablas y los datos de ejemplo.
// Solo funciona desde la propia computadora (localhost). Por consola: php instalar.php [--demo]
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc/instalador.php';

$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
if (!in_array($ip, ['127.0.0.1', '::1'], true) && PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('El instalador solo se puede usar desde la computadora donde corre el sistema.');
}

function e2($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

$log = [];
$error = null;
$hecho = false;

if (PHP_SAPI === 'cli' || (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST')) {
    $con_demo = PHP_SAPI === 'cli' ? in_array('--demo', $argv, true) : !empty($_POST['demo']);
    try {
        $log = reinstalar_base($con_demo);
        $hecho = true;
    } catch (Exception $ex) {
        $error = $ex->getMessage();
    }
}

if (PHP_SAPI === 'cli') {
    echo implode("\n", $log), "\n";
    if ($error) { echo "ERROR: $error\n"; exit(1); }
    exit(0);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Instalación · ARPAFLU</title>
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<main class="portal-contenido" style="max-width:720px">
  <div class="encabezado-pagina"><div><h1>Instalar el sistema (pruebas locales)</h1>
    <p>Crea la base de datos <strong><?= e2(DB_NAME) ?></strong>, las tablas y los datos de ejemplo.</p></div></div>

  <?php if ($error) { ?>
    <div class="aviso aviso-peligro"><div><strong>No se pudo instalar.</strong><p><?= e2($error) ?></p>
      <p>Revisá que WAMP esté en verde y que el usuario y la clave de MySQL de <code>config.php</code> sean los correctos.</p></div></div>
  <?php } ?>
  <?php if ($hecho) { ?>
    <div class="aviso aviso-ok"><div><strong>Listo.</strong>
      <ul><?php foreach ($log as $l) echo '<li>' . e2($l) . '</li>'; ?></ul>
      <p><a class="boton boton-primario" href="index.php">Ir al ingreso</a></p></div></div>
  <?php } else { ?>
    <form class="tarjeta" method="post">
      <div class="tarjeta-cuerpo formulario">
        <p>Si la base ya existe, <strong>se borra y se vuelve a crear</strong> con los datos de ejemplo.</p>
        <label class="casilla"><input type="checkbox" name="demo" value="1" checked> Activar la guía de demostración paso a paso</label>
        <p class="ayuda">Conexión: <?= e2(DB_USER) ?>@<?= e2(DB_HOST) ?>:<?= e2(DB_PORT) ?></p>
        <div class="pie-formulario"><button type="submit" class="boton boton-primario">Instalar / reiniciar con datos de ejemplo</button></div>
      </div>
    </form>
  <?php } ?>
</main>
</body>
</html>
