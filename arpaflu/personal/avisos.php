<?php
// SS5 · Avisos y comunicaciones: registro de envíos, resultado y confirmación «Notado»
require_once __DIR__ . '/../partes/layout.php';
$u = requerir_empleado('ver_operaciones');

if (es_post()) {
    csrf_verificar();
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';
    $cid = (int) (isset($_POST['comunicacion_id']) ? $_POST['comunicacion_id'] : 0);
    $c = fila('SELECT c.*, o.cliente_id FROM comunicaciones c LEFT JOIN operaciones o ON o.id = c.operacion_id WHERE c.id = ?', [$cid]);
    if (!$c) {
        flash('error', 'No se encontró el aviso.');
    } elseif (!puede('registrar_cambios') || ($c['cliente_id'] && !puede_actuar_cliente($c['cliente_id']))) {
        flash('error', 'No tenés autorización para esta acción sobre este cliente.');
    } elseif ($accion === 'reenviar') {
        $r = reenviar_aviso($cid);
        flash($r['ok'] ? 'ok' : 'error', $r['ok'] ? 'Aviso reenviado.' : 'No se pudo reenviar: ' . $r['error']);
    } elseif ($accion === 'notado_manual') {
        flash(confirmar_notado($cid, $u['id']) ? 'ok' : 'error', 'Se registró el «Notado».');
    }
    redirigir('avisos.php');
}

$q = trim(isset($_GET['q']) ? $_GET['q'] : '');
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$tipos = ['cambio' => 'Cambio de buque, fecha o cut-off', 'booking' => 'Booking confirmado', 'seleccion' => 'Selección de alternativa',
    'recordatorio' => 'Recordatorio de vencimiento', 'documento' => 'Documento', 'zarpe' => 'Zarpe', 'cancelacion' => 'Cancelación', 'otro' => 'Otros'];

$donde = [];
$p = [];
if ($q !== '') {
    $donde[] = '(o.referencia LIKE ? OR c.evento LIKE ? OR c.destinatario_nombre LIKE ? OR c.destinatario_correo LIKE ?)';
    $l = '%' . $q . '%';
    array_push($p, $l, $l, $l, $l);
}
if (isset($tipos[$tipo])) { $donde[] = 'c.tipo = ?'; $p[] = $tipo; }

$todos = consulta(
    'SELECT c.*, o.referencia FROM comunicaciones c LEFT JOIN operaciones o ON o.id = c.operacion_id'
    . ($donde ? ' WHERE ' . implode(' AND ', $donde) : '') . ' ORDER BY c.creado DESC, c.id DESC LIMIT 300',
    $p
);
$pendientes = consulta(
    'SELECT c.*, o.referencia FROM comunicaciones c LEFT JOIN operaciones o ON o.id = c.operacion_id
     WHERE c.requiere_notado = 1 AND c.notado_en IS NULL AND c.estado_envio = "enviado" ORDER BY c.plazo_notado'
);
$fallidos = consulta(
    'SELECT c.*, o.referencia FROM comunicaciones c LEFT JOIN operaciones o ON o.id = c.operacion_id
     WHERE c.estado_envio = "fallido" AND NOT EXISTS (SELECT 1 FROM comunicaciones r WHERE r.reenvio_de = c.id)
     ORDER BY c.creado DESC'
);
$puede_actuar = puede('registrar_cambios');

$boton = function ($c, $accion, $texto, $clase = 'boton-secundario') use ($puede_actuar) {
    if (!$puede_actuar) { return ''; }
    return '<form action="avisos.php" method="post" style="display:inline">' . csrf_campo()
        . '<input type="hidden" name="comunicacion_id" value="' . (int) $c['id'] . '">'
        . '<button type="submit" name="accion" value="' . e($accion) . '" class="boton ' . $clase . ' boton-chico">' . e($texto) . '</button></form> ';
};
$celda_op = function ($c) {
    return $c['operacion_id'] ? '<a href="operacion.php?id=' . (int) $c['operacion_id'] . '#comunicaciones" class="ref">' . e($c['referencia']) . '</a>' : '<span class="suave">—</span>';
};

cabecera_personal('Avisos', 'avisos');
?>
      <div class="encabezado-pagina">
        <div>
          <h1>Avisos y comunicaciones</h1>
          <p>Registro de cada correo enviado por el sistema, su resultado y la confirmación «Notado».
            <?= MAIL_MODO === 'smtp' ? '' : '<span class="chico suave">(Modo de prueba: los correos no salen, solo quedan registrados.)</span>' ?></p>
        </div>
      </div>

      <?php if ($fallidos) { $f = $fallidos[0]; ?>
      <div class="aviso aviso-peligro" id="fallidos-aviso">
        <div>
          <strong><?= count($fallidos) ?> aviso<?= count($fallidos) == 1 ? '' : 's' ?> no se pudo enviar.</strong>
          <p><?= e($f['error'] ?: 'Error de envío') ?>: <?= e($f['destinatario_correo']) ?><?= $f['referencia'] ? ' (' . e($f['referencia']) . ', ' . e(mb_strtolower($f['evento'])) . ')' : '' ?>. Revisá el correo del contacto y reenviá.</p>
        </div>
        <div class="acciones">
          <a href="clientes.php" class="boton boton-secundario boton-chico">Revisar contacto</a>
          <?= $boton($f, 'reenviar', 'Reenviar', 'boton-primario') ?>
        </div>
      </div>
      <?php } ?>

      <nav class="pestanas" role="tablist">
        <button class="pestana activa" data-pestana="todos" role="tab" aria-selected="true">Todos</button>
        <button class="pestana" data-pestana="pendientes" role="tab" aria-selected="false">«Notado» pendientes <span class="contador"><?= count($pendientes) ?></span></button>
        <button class="pestana" data-pestana="fallidos" role="tab" aria-selected="false">No enviados <span class="contador"><?= count($fallidos) ?></span></button>
      </nav>

      <section id="todos" class="panel-pestana tarjeta" role="tabpanel">
        <form class="filtros" action="avisos.php" method="get">
          <input type="search" class="filtro-ancho" name="q" value="<?= e($q) ?>" placeholder="Buscar operación, destinatario o evento…" aria-label="Buscar">
          <select name="tipo" aria-label="Tipo de evento">
            <option value="">Todos los eventos</option>
            <?php foreach ($tipos as $k => $t) { ?><option value="<?= e($k) ?>"<?= $tipo === $k ? ' selected' : '' ?>><?= e($t) ?></option><?php } ?>
          </select>
          <button type="submit" class="boton boton-secundario">Filtrar</button>
        </form>
        <div class="tabla-contenedor">
          <table>
            <thead><tr><th>Fecha</th><th>Operación</th><th>Evento</th><th>Destinatario</th><th>Envío</th><th>«Notado»</th></tr></thead>
            <tbody>
            <?php foreach ($todos as $c) { ?>
              <tr>
                <td class="num"><?= e(f_hora($c['creado'])) ?></td>
                <td><?= $celda_op($c) ?></td>
                <td><?= e($c['evento']) ?><?= $c['reenvio_de'] ? ' <span class="chico suave">(reenvío)</span>' : '' ?></td>
                <td><?= e($c['destinatario_nombre']) ?><?= $c['destinatario_tipo'] === 'interno' ? ' <span class="chico suave">(interno)</span>' : '' ?></td>
                <td><?= $c['estado_envio'] === 'enviado' ? badge('Enviado', 'estado-ok') : '<span title="' . e($c['error']) . '">' . badge('Falló', 'estado-peligro') . '</span>' ?></td>
                <td><?php
                    if ($c['notado_en'] !== null) { echo badge(f_hora($c['notado_en']), 'estado-ok'); }
                    elseif ($c['requiere_notado']) { echo strtotime($c['plazo_notado']) < time() ? badge('Vencido', 'estado-peligro') : badge('Esperando', 'estado-alerta'); }
                    else { echo '<span class="suave">' . ($c['estado_envio'] === 'enviado' ? 'No requiere' : '—') . '</span>'; } ?></td>
              </tr>
            <?php } if (!$todos) { ?>
              <tr><td colspan="6" class="suave">No hay avisos que coincidan.</td></tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
      </section>

      <section id="pendientes" class="panel-pestana tarjeta" role="tabpanel" hidden>
        <div class="tabla-contenedor">
          <table>
            <thead><tr><th>Operación</th><th>Evento</th><th>Destinatario</th><th>Enviado</th><th>Plazo</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($pendientes as $c) { $vencido = strtotime($c['plazo_notado']) < time(); ?>
              <tr>
                <td><?= $celda_op($c) ?></td>
                <td><?= e($c['evento']) ?></td>
                <td><?= e($c['destinatario_nombre']) ?></td>
                <td class="num"><?= e(f_hora($c['creado'])) ?></td>
                <td><?= $vencido ? badge('Vencido', 'estado-peligro') : badge('Vence ' . f_hora($c['plazo_notado']), 'estado-alerta') ?></td>
                <td><?= $boton($c, 'reenviar', 'Reenviar') ?><?= $boton($c, 'notado_manual', 'Marcar notado') ?></td>
              </tr>
            <?php } if (!$pendientes) { ?>
              <tr><td colspan="6" class="suave">No hay confirmaciones pendientes.</td></tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
      </section>

      <section id="fallidos" class="panel-pestana tarjeta" role="tabpanel" hidden>
        <div class="tabla-contenedor">
          <table>
            <thead><tr><th>Fecha</th><th>Operación</th><th>Evento</th><th>Dirección</th><th>Motivo</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($fallidos as $c) { ?>
              <tr>
                <td class="num"><?= e(f_hora($c['creado'])) ?></td>
                <td><?= $celda_op($c) ?></td>
                <td><?= e($c['evento']) ?></td>
                <td><?= e($c['destinatario_correo']) ?></td>
                <td class="envolver"><?= e($c['error']) ?></td>
                <td><?= $boton($c, 'reenviar', 'Reenviar') ?></td>
              </tr>
            <?php } if (!$fallidos) { ?>
              <tr><td colspan="6" class="suave">No hay avisos sin enviar.</td></tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
      </section>
<?php pie_personal(); ?>
