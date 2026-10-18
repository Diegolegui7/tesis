<?php
// P1 · Ingreso al sistema: valida la cuenta y redirige según el tipo de usuario (empleado o cliente)
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/demo.php';

if (usuario_actual()) {
    redirigir(inicio_segun_tipo(usuario_actual()));
}

$error = null;
if (es_post()) {
    csrf_verificar();
    $correo = isset($_POST['correo']) ? $_POST['correo'] : '';
    $clave = isset($_POST['clave']) ? $_POST['clave'] : '';
    // Acceso rápido a las cuentas de prueba (solo en modo demostración)
    if (MODO_DEMO && !empty($_POST['demo'])) {
        $correo = $_POST['demo'];
        $clave = CLAVE_DEMO;
    }
    $r = iniciar_sesion($correo, $clave);
    if ($r === true) {
        redirigir(inicio_segun_tipo(usuario_actual()));
    }
    $error = $r;
}

$demo = [];
if (MODO_DEMO) {
    try {
        $demo = consulta("SELECT nombre, correo, rol FROM usuarios WHERE activo = 1 AND id IN (1,2,3,4,6,8,12) ORDER BY tipo, id");
    } catch (Exception $ex) {
        $demo = [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sistema ARPAFLU</title>
  <link rel="stylesheet" href="<?= e(base_url()) ?>css/styles.css">
</head>
<body>
  <div class="pantalla-ingreso">
    <section class="ingreso-lado">
      <div>
        <div class="marca" style="padding:0">
          <div class="marca-logo">AF</div>
          <div>ARPAFLU<small>Forwarding · Comercio exterior</small></div>
        </div>
        <h1>Seguimiento de reservas marítimas</h1>
        <p>Cotizaciones, bookings, documentos, cambios y avisos de cada operación, en un solo lugar.</p>
      </div>
      <p class="ingreso-pie chico">Acceso exclusivo para el personal de ARPAFLU y usuarios autorizados de sus clientes.</p>
    </section>
    <section class="ingreso-form">
      <div class="ingreso-caja">
        <h2>Ingresar</h2>
        <p class="suave">Usá el correo y la contraseña de tu cuenta.</p>
        <?php if ($error) { ?>
          <div class="aviso aviso-peligro"><div><p><?= e($error) ?></p></div></div>
        <?php } ?>
        <form class="formulario" action="index.php" method="post">
          <?= csrf_campo() ?>
          <div class="campo">
            <label for="correo">Correo electrónico</label>
            <input type="email" id="correo" name="correo" maxlength="160" autocomplete="username" placeholder="nombre@empresa.com" value="<?= e(isset($_POST['correo']) ? $_POST['correo'] : '') ?>" required>
          </div>
          <div class="campo">
            <label for="clave">Contraseña</label>
            <input type="password" id="clave" name="clave" autocomplete="current-password" required>
          </div>
          <button type="submit" class="boton boton-primario boton-bloque">Ingresar</button>
          <p class="chico" style="margin:0"><a href="recuperar.php">¿Olvidaste tu clave?</a></p>
        </form>
        <?php if ($demo) { ?>
        <form action="index.php" method="post" style="margin-top:28px">
          <?= csrf_campo() ?>
          <p class="chico suave" style="margin-bottom:8px">Cuentas de prueba (demostración):</p>
          <div style="display:flex;flex-wrap:wrap;gap:6px">
            <?php foreach ($demo as $d) { ?>
              <button type="submit" name="demo" value="<?= e($d['correo']) ?>" class="boton boton-secundario boton-chico" title="<?= e($d['correo']) ?>"><?= e($d['nombre']) ?> · <?= e(etiqueta_rol($d['rol'])) ?></button>
            <?php } ?>
          </div>
        </form>
        <?php } ?>
      </div>
    </section>
  </div>
  <?php demo_panel(); ?>
</body>
</html>
