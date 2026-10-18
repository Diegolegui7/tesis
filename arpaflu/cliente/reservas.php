<?php
// Portal de clientes · Inicio: qué hacer, mis pedidos y mis reservas, en lenguaje simple
require_once __DIR__ . '/../partes/layout.php';
$u = requerir_cliente();
$cid = $u['cliente_id'];

if (es_post()) {
    csrf_verificar();
    if (isset($_POST['accion']) && $_POST['accion'] === 'cancelar_pedido') {
        $r = cancelar_solicitud_cliente($u, isset($_POST['id']) ? $_POST['id'] : 0);
        flash($r[0] ? 'ok' : 'error', $r[1]);
    }
    redirigir('reservas.php');
}

$cliente = fila('SELECT c.*, r.nombre AS responsable, r.correo AS responsable_correo FROM clientes c LEFT JOIN usuarios r ON r.id = c.responsable_id WHERE c.id = ?', [$cid]);
$ops = consulta(
    'SELECT o.*, n.nombre AS naviera FROM operaciones o LEFT JOIN navieras n ON n.id = o.naviera_id WHERE o.cliente_id = ?
     ORDER BY (o.estado IN ("zarpado","cancelada")), o.cutoff_doc IS NULL, o.cutoff_doc, o.id DESC',
    [$cid]
);
$pedidos = consulta(
    "SELECT s.*, d.pod, d.buque, d.etd, d.tipo_contenedor, d.flete, n.nombre AS naviera, o.referencia
     FROM solicitudes s JOIN disponibilidad d ON d.id = s.disponibilidad_id LEFT JOIN navieras n ON n.id = d.naviera_id LEFT JOIN operaciones o ON o.id = s.operacion_id
     WHERE s.cliente_id = ? ORDER BY (s.estado = 'pendiente') DESC, s.id DESC LIMIT 6", [$cid]);
$alt = $u['puede_seleccionar'] && (int) $cliente['selecciona_alternativas'] === 1
    ? fila("SELECT grupo, COUNT(*) AS n FROM alternativas WHERE cliente_id = ? AND estado = 'disponible' GROUP BY grupo ORDER BY MAX(creado) DESC LIMIT 1", [$cid]) : null;
$pide = puede_pedir($u);
$con_lugar = $pide ? count(disponibilidades(true)) : 0;
$activas = 0;
foreach ($ops as $o) { if (!in_array($o['estado'], ['zarpado', 'cancelada'], true)) { $activas++; } }
$nombre = explode(' ', $u['nombre'])[0];

// Cosas para hacer, de lo más a lo menos urgente
$hacer = [];
foreach ($ops as $o) {
    if (in_array($o['estado'], ['zarpado', 'cancelada'], true)) { continue; }
    list($nn, $docs) = pendientes_cliente($o['id']);
    if ($nn > 0) { $hacer[] = ['Confirmá que leíste el aviso de ' . $o['referencia'], 'operacion.php?id=' . $o['id'] . '#avisos', 'Ver el aviso']; }
    if ($docs) { $hacer[] = ['Falta que envíes ' . implode(', ', $docs) . ' de ' . $o['referencia'], 'operacion.php?id=' . $o['id'] . '#enviar', 'Enviar']; }
}
if ($alt) { $hacer[] = ['ARPAFLU te cargó ' . (int) $alt['n'] . ' opciones para elegir (' . $alt['grupo'] . ')', 'alternativas.php', 'Elegir']; }

cabecera_cliente('Inicio', 'reservas');
?>
    <div class="encabezado-pagina">
      <div>
        <h1>Hola, <?= e($nombre) ?></h1>
        <p><?= e($cliente['empresa']) ?> · Desde acá pedís lugar para tu carga y ves cómo avanza.</p>
      </div>
    </div>

    <?php if ($hacer) { ?>
    <section class="cuadro-hacer aviso-alerta" aria-label="Cosas para hacer" style="display:block">
      <h2>Tenés <?= count($hacer) === 1 ? 'una cosa' : count($hacer) . ' cosas' ?> para hacer</h2>
      <ul class="lista" style="margin-top:8px">
        <?php foreach ($hacer as $h) { ?>
          <li style="padding-left:0;padding-right:0"><span><?= e($h[0]) ?></span><a class="boton boton-primario boton-chico" href="<?= e($h[1]) ?>"><?= e($h[2]) ?></a></li>
        <?php } ?>
      </ul>
    </section>
    <?php } ?>

    <div class="mosaico">
      <?php if ($pide && $con_lugar > 0) { ?>
        <a class="baldosa baldosa-principal" href="pedir.php"><span class="baldosa-icono" aria-hidden="true">🚢</span><strong>Pedir una reserva</strong><span>Hay lugar en <?= $con_lugar ?> embarque<?= $con_lugar == 1 ? '' : 's' ?>. Tocá acá para pedir.</span></a>
      <?php } elseif ($pide) { ?>
        <a class="baldosa baldosa-apagada" href="pedir.php"><span class="baldosa-icono" aria-hidden="true">🚢</span><strong>Pedir una reserva</strong><span>Por ahora no hay lugar disponible. Tocá para ver por qué.</span></a>
      <?php } else { ?>
        <div class="baldosa baldosa-apagada"><span class="baldosa-icono" aria-hidden="true">🚢</span><strong>Pedir una reserva</strong><span>Tu cuenta solo puede consultar. Pedile a ARPAFLU que la habilite.</span></div>
      <?php } ?>
      <a class="baldosa" href="#reservas"><span class="baldosa-icono" aria-hidden="true">📦</span><strong>Mis reservas</strong><span><?= $activas ? $activas . ' en curso' : 'Todavía no tenés reservas en curso' ?></span></a>
      <?php if ($alt) { ?>
        <a class="baldosa" href="alternativas.php"><span class="baldosa-icono" aria-hidden="true">🗂️</span><strong>Elegir entre opciones</strong><span>ARPAFLU te cargó opciones para que elijas una.</span></a>
      <?php } else { ?>
        <a class="baldosa" href="ayuda.php"><span class="baldosa-icono" aria-hidden="true">💬</span><strong>Ayuda</strong><span>Cómo funciona, qué significa cada palabra y con quién hablar.</span></a>
      <?php } ?>
    </div>

    <?php if ($pedidos) { ?>
    <h2 class="titulo-seccion" id="pedidos">Mis pedidos de reserva</h2>
    <?php foreach ($pedidos as $s) { $t = texto_estado_pedido($s['estado']); ?>
    <div class="reserva-tarjeta">
      <div class="reserva-tarjeta-cab">
        <div>
          <div class="reserva-destino"><?= (int) $s['cantidad'] ?> × <?= termino($s['tipo_contenedor']) ?> a <?= e($s['pod']) ?></div>
          <div class="reserva-sub"><?= e($s['buque']) ?> (<?= e($s['naviera']) ?>) · sale el <?= e(f_fecha($s['etd'])) ?> · pedido el <?= e(f_corta($s['creado'])) ?></div>
        </div>
        <span class="estado <?= e($t[1]) ?>"><?= e($t[0]) ?></span>
      </div>
      <?php if ($s['estado'] === 'pendiente') { ?>
        <div class="reserva-hacer aviso-info" style="margin-top:14px"><span>No tenés que hacer nada. ARPAFLU te avisa por mail cuando responda. Hasta entonces, el lugar te queda guardado.</span>
          <form action="reservas.php" method="post" data-confirmar="¿Cancelás este pedido? El lugar queda libre para otros clientes.">
            <?= csrf_campo() ?><input type="hidden" name="accion" value="cancelar_pedido"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
            <button type="submit" class="boton boton-peligro boton-chico">Cancelar pedido</button>
          </form></div>
      <?php } elseif ($s['estado'] === 'aceptada') { ?>
        <div class="reserva-hacer aviso-ok" style="margin-top:14px"><span>Aceptado. Tu reserva es la <strong><?= e($s['referencia']) ?></strong>: ARPAFLU está confirmando el lugar con la naviera.</span>
          <a class="boton boton-primario boton-chico" href="operacion.php?id=<?= (int) $s['operacion_id'] ?>">Ver mi reserva</a></div>
      <?php } elseif ($s['estado'] === 'rechazada') { ?>
        <div class="reserva-hacer aviso-alerta" style="margin-top:14px"><span>No pudimos aceptarlo. <strong>Motivo:</strong> <?= e($s['motivo_rechazo']) ?></span>
          <?php if ($pide && $con_lugar > 0) { ?><a class="boton boton-secundario boton-chico" href="pedir.php">Pedir en otro barco</a><?php } ?></div>
      <?php } ?>
    </div>
    <?php } } ?>

    <h2 class="titulo-seccion" id="reservas">Mis reservas</h2>
    <?php foreach ($ops as $o) {
        $est = estado_cliente($o['estado']);
        list($nn, $docs) = pendientes_cliente($o['id']);
        $q = que_hacer_ahora($o, $nn, $docs); ?>
    <div class="reserva-tarjeta">
      <div class="reserva-tarjeta-cab">
        <div>
          <div class="reserva-destino"><?= e($o['pod']) ?> · <?= (int) $o['cantidad'] ?> × <?= termino($o['tipo_contenedor']) ?></div>
          <div class="reserva-sub"><?= e($o['referencia']) ?><?= $o['referencia_cliente'] ? ' · tu referencia ' . e($o['referencia_cliente']) : '' ?> · <?= e($o['buque'] ?: 'barco a confirmar') ?> · sale el <?= e(f_fecha($o['etd'])) ?></div>
        </div>
        <?= badge_cliente($o['estado']) ?>
      </div>
      <?= barra_progreso($o['estado']) ?>
      <div class="reserva-hacer <?= e($q[3]) ?>">
        <span><strong><?= e($q[0]) ?>.</strong> <?= e($q[1]) ?></span>
        <a class="boton <?= $q[2] ? 'boton-primario' : 'boton-secundario' ?> boton-chico" href="operacion.php?id=<?= (int) $o['id'] ?><?= $q[2] ? e($q[2][1]) : '' ?>"><?= $q[2] ? e($q[2][0]) : 'Ver detalle' ?></a>
      </div>
    </div>
    <?php } if (!$ops) { ?>
    <div class="sin-disponibilidad" style="padding:28px 20px">
      <div class="grande" aria-hidden="true">📦</div>
      <h2>Todavía no tenés reservas</h2>
      <p class="suave">Cuando pidas lugar para tu carga y ARPAFLU lo acepte, la vas a ver acá con todos sus pasos.</p>
      <?php if ($pide && $con_lugar > 0) { ?><a class="boton boton-primario" href="pedir.php">Pedir una reserva</a><?php } ?>
    </div>
    <?php } ?>

    <?php if ($cliente['responsable']) { ?>
    <p class="suave" style="margin-top:24px">¿Dudas? Tu responsable en ARPAFLU es <strong><?= e($cliente['responsable']) ?></strong> (<?= e($cliente['responsable_correo']) ?>).</p>
    <?php } ?>
<?php
marcar_pedidos_vistos($cid);
pie_cliente();
