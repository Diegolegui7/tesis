<?php
// SS2 · Pedidos de reserva de los clientes: aceptar (crea la operación) o rechazar con un motivo (RF-22)
require_once __DIR__ . '/../partes/layout.php';
$u = requerir_empleado('crear_operaciones');

if (es_post()) {
    csrf_verificar();
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';
    $id = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
    if ($accion === 'aceptar') {
        $r = aceptar_solicitud($u, $id);
        flash($r[0] ? 'ok' : 'error', $r[1]);
        if ($r[0]) { redirigir('operacion.php?id=' . (int) $r[2]); }
    } elseif ($accion === 'rechazar') {
        $r = rechazar_solicitud($u, $id, isset($_POST['motivo']) ? $_POST['motivo'] : '');
        flash($r[0] ? 'ok' : 'error', $r[1]);
    }
    redirigir('pedidos.php');
}

$sql = "SELECT s.*, c.empresa, r.nombre AS responsable, us.nombre AS solicitante, d.pod, d.buque, d.etd, d.tipo_contenedor, d.flete, n.nombre AS naviera,
               o.referencia, resp.nombre AS respondio
        FROM solicitudes s JOIN clientes c ON c.id = s.cliente_id LEFT JOIN usuarios r ON r.id = c.responsable_id JOIN usuarios us ON us.id = s.usuario_id
        JOIN disponibilidad d ON d.id = s.disponibilidad_id LEFT JOIN navieras n ON n.id = d.naviera_id LEFT JOIN operaciones o ON o.id = s.operacion_id
        LEFT JOIN usuarios resp ON resp.id = s.respondido_por";
$pend = consulta($sql . " WHERE s.estado = 'pendiente' ORDER BY s.creado");
$resp = consulta($sql . " WHERE s.estado <> 'pendiente' ORDER BY COALESCE(s.respondido_en, s.creado) DESC LIMIT 30");
$txt = ['aceptada' => ['Aceptado', 'estado-ok'], 'rechazada' => ['Rechazado', 'estado-peligro'], 'cancelada' => ['Cancelado por el cliente', 'estado-neutro']];

cabecera_personal('Pedidos de clientes', 'pedidos');
?>
      <div class="encabezado-pagina">
        <div>
          <h1>Pedidos de clientes</h1>
          <p>Los clientes piden lugar en los embarques con disponibilidad. Al aceptar un pedido se crea la reserva y se avisa al cliente.</p>
        </div>
        <div class="acciones"><a href="disponibilidad.php" class="boton boton-secundario">Ver disponibilidad</a></div>
      </div>

      <nav class="pestanas" role="tablist">
        <button class="pestana activa" data-pestana="pendientes" role="tab" aria-selected="true">Por responder <span class="contador"><?= count($pend) ?></span></button>
        <button class="pestana" data-pestana="respondidos" role="tab" aria-selected="false">Respondidos <span class="contador"><?= count($resp) ?></span></button>
      </nav>

      <section id="pendientes" class="panel-pestana tarjeta" role="tabpanel">
        <div class="tabla-contenedor">
          <table>
            <thead><tr><th>Pedido</th><th>Cliente</th><th>Embarque</th><th>Qué pide</th><th>Respuesta</th></tr></thead>
            <tbody>
            <?php foreach ($pend as $s) { $puede = puede_actuar_cliente($s['cliente_id']); ?>
              <tr>
                <td class="num"><?= e(f_hora($s['creado'])) ?><div class="chico suave"><?= e($s['solicitante']) ?></div></td>
                <td><strong><?= e($s['empresa']) ?></strong><div class="chico suave">Responsable: <?= e($s['responsable'] ?: '—') ?></div></td>
                <td><?= e($s['pod']) ?> · <?= e($s['buque']) ?><div class="chico suave"><?= e($s['naviera']) ?> · sale <?= e(f_fecha($s['etd'])) ?></div></td>
                <td class="envolver" style="min-width:240px"><strong><?= (int) $s['cantidad'] ?> × <?= e($s['tipo_contenedor']) ?></strong> · <?= e(dinero($s['flete'] * $s['cantidad'])) ?>
                  <?php if ($s['carga']) { ?><div class="chico suave">Carga: <?= e($s['carga']) ?></div><?php } ?>
                  <?php if ($s['comentario']) { ?><div class="chico suave">«<?= e($s['comentario']) ?>»</div><?php } ?></td>
                <td class="envolver" style="min-width:200px">
                  <?php if ($puede) { ?>
                  <form action="pedidos.php" method="post" style="display:inline"><?= csrf_campo() ?><input type="hidden" name="accion" value="aceptar"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                    <button class="boton boton-primario boton-chico">Aceptar</button></form>
                  <form action="pedidos.php" method="post" style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap"><?= csrf_campo() ?><input type="hidden" name="accion" value="rechazar"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                    <input type="text" name="motivo" maxlength="250" placeholder="Motivo si lo rechazás" style="flex:1 1 140px;width:auto;padding:4px 8px" required>
                    <button class="boton boton-peligro boton-chico">Rechazar</button></form>
                  <?php } else { ?>
                    <span class="chico suave">Solo consulta: lo responde <?= e($s['responsable'] ?: 'el responsable') ?>.</span>
                  <?php } ?>
                </td>
              </tr>
            <?php } if (!$pend) { ?>
              <tr><td colspan="5" class="suave">No hay pedidos por responder.</td></tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
      </section>

      <section id="respondidos" class="panel-pestana tarjeta" role="tabpanel" hidden>
        <div class="tabla-contenedor">
          <table>
            <thead><tr><th>Pedido</th><th>Cliente</th><th>Embarque</th><th>Qué pidió</th><th>Estado</th><th>Respuesta</th></tr></thead>
            <tbody>
            <?php foreach ($resp as $s) { $t = $txt[$s['estado']]; ?>
              <tr>
                <td class="num"><?= e(f_hora($s['creado'])) ?></td>
                <td><?= e($s['empresa']) ?></td>
                <td><?= e($s['pod']) ?> · <?= e($s['buque']) ?></td>
                <td><?= (int) $s['cantidad'] ?> × <?= e($s['tipo_contenedor']) ?></td>
                <td><?= badge($t[0], $t[1]) ?></td>
                <td class="envolver"><?php
                    if ($s['estado'] === 'aceptada' && $s['operacion_id']) { echo '<a class="ref" href="operacion.php?id=' . (int) $s['operacion_id'] . '">' . e($s['referencia']) . '</a>'; }
                    elseif ($s['estado'] === 'rechazada') { echo e($s['motivo_rechazo']); }
                    echo $s['respondio'] ? '<div class="chico suave">' . e($s['respondio']) . ' · ' . e(f_hora($s['respondido_en'])) . '</div>' : ''; ?></td>
              </tr>
            <?php } if (!$resp) { ?>
              <tr><td colspan="6" class="suave">Todavía no hay pedidos respondidos.</td></tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
      </section>
<?php pie_personal(); ?>
