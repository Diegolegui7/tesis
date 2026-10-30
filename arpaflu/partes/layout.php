<?php
// Estructura común de las pantallas (menú lateral, barra superior, portal de clientes)
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/avisos.php';
require_once __DIR__ . '/../inc/solicitudes.php';
require_once __DIR__ . '/../inc/portal.php';
require_once __DIR__ . '/../inc/demo.php';

function cabecera_personal($titulo, $activo)
{
    $u = usuario_actual();
    $b = base_url();
    $pend = contar_avisos_pendientes();
    $ped = puede('crear_operaciones') ? contar_pedidos_pendientes() : 0;
    $item = function ($clave, $url, $texto, $extra = '') use ($activo, $b) {
        return '<li><a href="' . e($b . 'personal/' . $url) . '"' . ($activo === $clave ? ' class="activo"' : '') . '>' . $texto . $extra . '</a></li>';
    };
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo) ?> · ARPAFLU</title>
  <link rel="stylesheet" href="<?= e($b) ?>css/styles.css">
</head>
<body>
<div class="app">
  <aside class="lateral">
    <div class="marca">
      <div class="marca-logo">AF</div>
      <div>ARPAFLU<small>Seguimiento de reservas</small></div>
    </div>
    <ul class="menu">
      <?php if (vista_simple()) { ?>
      <li class="menu-titulo">Mi trabajo</li>
      <?= $item('inicio', 'inicio.php', 'Inicio') ?>
      <?php if (puede('crear_operaciones')) echo $item('pedidos', 'pedidos.php', 'Pedidos de clientes', $ped > 0 ? ' <span class="contador">' . $ped . '</span>' : ''); ?>
      <?= $item('operaciones', 'operaciones.php', 'Reservas') ?>
      <?php if (puede('crear_operaciones')) echo $item('disponibilidad', 'disponibilidad.php', 'Lugar disponible'); ?>
      <?= $item('clientes', 'clientes.php', 'Clientes') ?>
      <?= $item('avisos', 'avisos.php', 'Avisos enviados', $pend > 0 ? ' <span class="contador">' . $pend . '</span>' : '') ?>
      <?php if (puede('administrar_usuarios')) echo $item('usuarios', 'usuarios.php', 'Personas y permisos'); ?>
      <?= $item('ayuda', 'ayuda.php', 'Ayuda') ?>
      <?php } else { ?>
      <li class="menu-titulo">Operación</li>
      <?= $item('inicio', 'inicio.php', 'Inicio') ?>
      <?= $item('panel', 'panel.php', 'Panel') ?>
      <?= $item('operaciones', 'operaciones.php', 'Reservas') ?>
      <?php if (puede('crear_operaciones')) echo $item('pedidos', 'pedidos.php', 'Pedidos de clientes', $ped > 0 ? ' <span class="contador">' . $ped . '</span>' : ''); ?>
      <?php if (puede('crear_operaciones')) echo $item('disponibilidad', 'disponibilidad.php', 'Lugar disponible'); ?>
      <?php if (puede('cotizaciones')) echo $item('cotizaciones', 'cotizaciones.php', 'Cotizaciones'); ?>
      <?php if (puede('crear_operaciones')) echo $item('alternativas', 'alternativas.php', 'Alternativas'); ?>
      <?= $item('clientes', 'clientes.php', 'Clientes') ?>
      <?= $item('avisos', 'avisos.php', 'Avisos enviados', $pend > 0 ? ' <span class="contador">' . $pend . '</span>' : '') ?>
      <?php if (puede('administrar_usuarios')) { ?>
      <li class="menu-titulo">Administración</li>
      <?= $item('usuarios', 'usuarios.php', 'Personas y permisos') ?>
      <?php } ?>
      <?= $item('ayuda', 'ayuda.php', 'Ayuda') ?>
      <?php } ?>
    </ul>
    <div class="lateral-pie">
      <strong><?= e($u['nombre']) ?></strong>
      <?= e(etiqueta_rol($u['rol'])) ?> · <a href="<?= e($b) ?>cambiar-clave.php">Mi clave</a> · <a href="<?= e($b) ?>salir.php">Salir</a>
      <a class="cambiar-vista" href="<?= e($b) ?>personal/vista.php?modo=<?= vista_simple() ? 'completa' : 'simple' ?>"><?= vista_simple() ? 'Ver todas las opciones' : 'Volver a la vista simple' ?></a>
    </div>
  </aside>
  <div class="velo"></div>

  <div class="principal">
    <header class="barra-superior">
      <button class="boton-menu" type="button" aria-label="Abrir menú">☰</button>
      <form class="buscador" action="<?= e($b) ?>personal/operaciones.php" method="get" role="search">
        <input type="search" name="q" value="<?= e(isset($_GET['q']) ? $_GET['q'] : '') ?>" placeholder="Buscar por referencia, booking, buque, destino o cliente" aria-label="Buscar">
      </form>
      <div class="usuario"><span><?= e($u['nombre']) ?></span><div class="avatar"><?= e(iniciales($u['nombre'])) ?></div></div>
    </header>

    <main class="contenido">
      <?php mostrar_flash(); ?>
<?php
}

function pie_personal()
{
    ?>
    </main>

    <footer class="pie-pagina">ARPAFLU · Sistema de seguimiento de reservas · Desarrollado por Core Digital Enterprise</footer>
  </div>
</div>
<?php demo_panel(); ?>
<script src="<?= e(base_url()) ?>js/app.js"></script>
</body>
</html>
<?php
}

function cabecera_cliente($titulo, $activo)
{
    $u = usuario_actual();
    $b = base_url();
    $c = fila('SELECT empresa, selecciona_alternativas FROM clientes WHERE id = ?', [$u['cliente_id']]);
    $resp = responsable_de($u['cliente_id']);
    $pide = puede_pedir($u);
    $elige = $u['puede_seleccionar'] && $c && (int) $c['selecciona_alternativas'] === 1;
    $item = function ($clave, $url, $texto) use ($activo, $b) {
        return '<a href="' . e($b . 'cliente/' . $url) . '"' . ($activo === $clave ? ' class="activo"' : '') . '>' . $texto . '</a>';
    };
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo) ?> · ARPAFLU</title>
  <link rel="stylesheet" href="<?= e($b) ?>css/styles.css">
</head>
<body class="portal">
  <a class="saltar" href="#contenido">Ir al contenido</a>
  <header class="portal-cabecera">
    <div class="interno">
      <div class="marca">
        <div class="marca-logo">AF</div>
        <div>ARPAFLU<small>Portal de clientes</small></div>
      </div>
      <nav class="portal-menu" aria-label="Menú principal">
        <?= $item('reservas', 'reservas.php', 'Inicio') ?>
        <?php if ($pide) echo $item('pedir', 'pedir.php', 'Pedir una reserva'); ?>
        <?php if ($elige) echo $item('alternativas', 'alternativas.php', 'Elegir opciones'); ?>
        <?= $item('ayuda', 'ayuda.php', 'Ayuda') ?>
      </nav>
      <div class="usuario">
        <span><strong><?= e($u['nombre']) ?></strong> · <?= e($c['empresa']) ?></span>
        <a href="<?= e($b) ?>cambiar-clave.php">Mi clave</a>
        <a href="<?= e($b) ?>salir.php">Salir</a>
      </div>
    </div>
  </header>

  <main class="portal-contenido" id="contenido">
    <?php mostrar_flash(); ?>
<?php
    $GLOBALS['tour_datos'] = ['nombre' => $u['nombre'], 'resp' => $resp, 'pide' => $pide];
}

function pie_cliente()
{
    $t = isset($GLOBALS['tour_datos']) ? $GLOBALS['tour_datos'] : null;
    ?>
  </main>

  <a href="#" class="boton-ayuda" data-tour-abrir title="Ver cómo funciona">?<span> Cómo funciona</span></a>

  <?php if ($t) { ?>
  <div class="tour" id="tour" hidden role="dialog" aria-modal="true" aria-labelledby="tour-titulo">
    <div class="tour-caja">
      <div class="tour-paso" data-paso="1">
        <h2 id="tour-titulo">¡Hola, <?= e(explode(' ', $t['nombre'])[0]) ?>!</h2>
        <p>Este portal sirve para <strong>pedir lugar para tu carga</strong> y <strong>seguirla</strong> hasta que el barco sale. Te mostramos cómo funciona en 4 pasos cortos.</p>
      </div>
      <div class="tour-paso" data-paso="2" hidden>
        <h2>1. Pedí tu lugar</h2>
        <p>Tocá <strong>“Pedir una reserva”</strong>. Si hay lugar, elegís el barco, escribís cuántos contenedores necesitás y enviás el pedido. Si no hay lugar, te lo decimos y no hace falta llenar nada.</p>
      </div>
      <div class="tour-paso" data-paso="3" hidden>
        <h2>2. Seguí tu carga</h2>
        <p>En <strong>“Inicio”</strong> ves cada reserva y en qué paso está. Dentro de cada una, un cuadro de color te dice <strong>qué tenés que hacer ahora</strong>. Si no dice nada, no tenés que hacer nada.</p>
      </div>
      <div class="tour-paso" data-paso="4" hidden>
        <h2>3. Si tenés dudas</h2>
        <p>Las palabras con un <span class="termino-i" style="position:static">?</span> se explican al tocarlas. <?php if ($t['resp']) { ?>También podés escribirle a tu responsable en ARPAFLU: <strong><?= e($t['resp']['nombre']) ?></strong> (<?= e($t['resp']['correo']) ?>).<?php } else { ?>También podés consultar a ARPAFLU.<?php } ?></p>
      </div>
      <div class="tour-pie">
        <span class="tour-puntos" aria-hidden="true"></span>
        <div class="acciones">
          <button type="button" class="boton boton-secundario" data-tour-cerrar>Cerrar</button>
          <button type="button" class="boton boton-primario" data-tour-siguiente>Siguiente</button>
        </div>
      </div>
    </div>
  </div>
  <?php } ?>

<?php demo_panel(); ?>
<script src="<?= e(base_url()) ?>js/app.js"></script>
</body>
</html>
<?php
}
