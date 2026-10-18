<?php
// SS1 · «¿Olvidaste tu clave?»: avisa a los administradores para que generen una clave nueva. La respuesta es la misma exista o no la cuenta.
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/avisos.php';
require_once __DIR__ . '/inc/demo.php';

$enviado = false;
if (es_post()) {
    csrf_verificar();
    $correo = trim(isset($_POST['correo']) ? $_POST['correo'] : '');
    if (filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $cta = fila('SELECT nombre, correo FROM usuarios WHERE correo = ? AND activo = 1', [$correo]);
        if ($cta) {
            foreach (consulta("SELECT nombre, correo FROM usuarios WHERE rol = 'administrador' AND activo = 1") as $adm) {
                registrar_aviso([
                    'operacion_id' => null, 'cambio_id' => null, 'evento' => 'Pedido de clave nueva', 'tipo' => 'otro',
                    'nombre' => $adm['nombre'], 'correo' => $adm['correo'], 'dest_tipo' => 'interno',
                    'asunto' => $cta['nombre'] . ' pidió una clave nueva',
                    'cuerpo' => $cta['nombre'] . ' (' . $cta['correo'] . ') dijo que olvidó su clave. Generá una nueva en Usuarios y permisos → «Restablecer clave» y avisale.',
                    'notado' => false,
                ]);
            }
        }
    }
    $enviado = true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Recuperar clave · ARPAFLU</title>
  <link rel="stylesheet" href="<?= e(base_url()) ?>css/styles.css">
</head>
<body class="portal">
  <main class="portal-contenido" style="max-width:600px">
    <div class="encabezado-pagina"><div><h1>¿Olvidaste tu clave?</h1><p>No pasa nada. Le avisamos a ARPAFLU para que te genere una nueva.</p></div></div>
    <?php if ($enviado) { ?>
      <div class="aviso aviso-ok"><div><strong>Listo.</strong><p>Si el correo corresponde a una cuenta, ARPAFLU ya recibió el aviso y se va a comunicar con vos para darte una clave nueva.</p></div></div>
      <a class="boton boton-primario" href="index.php">Volver al ingreso</a>
    <?php } else { ?>
      <form class="tarjeta" action="recuperar.php" method="post">
        <?= csrf_campo() ?>
        <div class="tarjeta-cuerpo formulario">
          <div class="campo"><label for="correo">¿Con qué correo ingresás?</label><input type="email" id="correo" name="correo" maxlength="160" placeholder="nombre@empresa.com" required></div>
          <div class="pie-formulario"><a class="boton boton-secundario" href="index.php">Volver</a><button type="submit" class="boton boton-primario">Avisar a ARPAFLU</button></div>
        </div>
      </form>
    <?php } ?>
  </main>
  <?php demo_panel(); ?>
</body>
</html>
