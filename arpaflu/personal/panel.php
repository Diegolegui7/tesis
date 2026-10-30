<?php
// SS6 · Panel de pendientes y vencimientos (P9 / P13)
require_once __DIR__ . '/../partes/layout.php';
$u = requerir_empleado('ver_operaciones');

$activas = "o.estado NOT IN ('zarpado','cancelada')";

$n_activas = (int) valor("SELECT COUNT(*) FROM operaciones o WHERE $activas");
$n_cutoffs = (int) valor(
    "SELECT (SELECT COUNT(*) FROM operaciones o WHERE $activas AND o.cutoff_doc BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY))
          + (SELECT COUNT(*) FROM operaciones o WHERE $activas AND o.cutoff_fisico BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY))
          + (SELECT COUNT(*) FROM operaciones o WHERE $activas AND o.vgm_limite BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY))"
);
$n_docs = (int) valor("SELECT COUNT(*) FROM documentos d JOIN operaciones o ON o.id = d.operacion_id WHERE d.estado = 'solicitado' AND $activas AND NOT EXISTS (SELECT 1 FROM documentos d2 WHERE d2.operacion_id = d.operacion_id AND d2.tipo = d.tipo AND d2.version > d.version AND d2.estado <> 'solicitado')");
$n_notado = (int) valor('SELECT COUNT(*) FROM comunicaciones WHERE requiere_notado = 1 AND notado_en IS NULL AND plazo_notado < NOW()');
$n_fallidos = (int) valor(
    'SELECT COUNT(*) FROM comunicaciones c WHERE c.estado_envio = "fallido"
     AND NOT EXISTS (SELECT 1 FROM comunicaciones r WHERE r.reenvio_de = c.id)'
);

$n_pedidos = puede('crear_operaciones') ? contar_pedidos_pendientes() : 0;

$venc = consulta(
    "SELECT * FROM (
        SELECT o.id, o.referencia, c.empresa, 'Cut-off documental' AS tipo, o.cutoff_doc AS fecha FROM operaciones o JOIN clientes c ON c.id = o.cliente_id WHERE $activas AND o.cutoff_doc >= NOW()
        UNION ALL
        SELECT o.id, o.referencia, c.empresa, 'Cut-off físico', o.cutoff_fisico FROM operaciones o JOIN clientes c ON c.id = o.cliente_id WHERE $activas AND o.cutoff_fisico >= NOW()
        UNION ALL
        SELECT o.id, o.referencia, c.empresa, 'VGM', o.vgm_limite FROM operaciones o JOIN clientes c ON c.id = o.cliente_id WHERE $activas AND o.vgm_limite >= NOW()
     ) v ORDER BY fecha LIMIT 8"
);

// Requieren atención
$atencion = [];
if (puede('crear_operaciones')) {
    foreach (consulta("SELECT s.cantidad, c.empresa, d.pod, d.tipo_contenedor FROM solicitudes s JOIN clientes c ON c.id = s.cliente_id JOIN disponibilidad d ON d.id = s.disponibilidad_id WHERE s.estado = 'pendiente' ORDER BY s.creado LIMIT 3") as $r) {
        $atencion[] = ['Pedido de reserva', $r['empresa'] . ' pidió ' . $r['cantidad'] . ' × ' . $r['tipo_contenedor'] . ' a ' . $r['pod'], 'pedidos.php'];
    }
}
foreach (consulta(
    'SELECT a.etiqueta, a.elegida_en, a.cliente_id, c.empresa, o.id AS op_id, o.referencia FROM alternativas a
     JOIN clientes c ON c.id = a.cliente_id LEFT JOIN operaciones o ON o.id = a.operacion_id
     WHERE a.estado = "elegida" AND a.elegida_en >= DATE_SUB(NOW(), INTERVAL 2 DAY) ORDER BY a.elegida_en DESC LIMIT 3'
) as $r) {
    $atencion[] = ['Selección de ' . $r['empresa'], 'Eligió la alternativa ' . $r['etiqueta'] . ($r['referencia'] ? ' en ' . $r['referencia'] : '') . ' · ' . f_hora($r['elegida_en']),
        $r['op_id'] ? 'operacion.php?id=' . $r['op_id'] : 'nueva-operacion.php?cliente_id=' . $r['cliente_id']];
}
foreach (consulta(
    'SELECT c.destinatario_nombre, c.evento, o.id AS op_id, o.referencia FROM comunicaciones c JOIN operaciones o ON o.id = c.operacion_id
     WHERE c.requiere_notado = 1 AND c.notado_en IS NULL AND c.plazo_notado < NOW() ORDER BY c.plazo_notado LIMIT 3'
) as $r) {
    $atencion[] = ['“Notado” vencido', $r['destinatario_nombre'] . ' (' . $r['referencia'] . ') no confirmó: ' . mb_strtolower($r['evento']), 'operacion.php?id=' . $r['op_id'] . '#comunicaciones'];
}
foreach (consulta(
    'SELECT c.destinatario_correo, o.referencia FROM comunicaciones c LEFT JOIN operaciones o ON o.id = c.operacion_id
     WHERE c.estado_envio = "fallido" AND NOT EXISTS (SELECT 1 FROM comunicaciones r WHERE r.reenvio_de = c.id)
     ORDER BY c.creado DESC LIMIT 3'
) as $r) {
    $atencion[] = ['Aviso no enviado', 'Correo rechazado para ' . $r['destinatario_correo'] . ($r['referencia'] ? ' (' . $r['referencia'] . ')' : ''), 'avisos.php#fallidos'];
}
foreach (consulta(
    'SELECT d.observaciones, o.id AS op_id, o.referencia FROM documentos d JOIN operaciones o ON o.id = d.operacion_id
     WHERE d.tipo = "DRAFT" AND d.observaciones IS NOT NULL AND d.observaciones <> "" AND d.creado >= DATE_SUB(NOW(), INTERVAL 2 DAY) ORDER BY d.creado DESC LIMIT 2'
) as $r) {
    $atencion[] = ['DRAFT con observaciones', $r['referencia'] . ' · ' . $r['observaciones'], 'operacion.php?id=' . $r['op_id'] . '#documentos'];
}

$cambios = consulta(
    'SELECT h.*, o.referencia, u.nombre AS usuario FROM historial_cambios h
     JOIN operaciones o ON o.id = h.operacion_id LEFT JOIN usuarios u ON u.id = h.usuario_id
     ORDER BY h.creado DESC, h.id DESC LIMIT 6'
);

cabecera_personal('Panel', 'panel');
?>
      <div class="encabezado-pagina">
        <div>
          <h1>Panel de pendientes</h1>
          <p><?= e(fecha_larga_hoy()) ?></p>
        </div>
        <div class="acciones">
          <?php if (puede('registrar_cambios')) { ?>
          <form action="vencimientos.php" method="post" style="display:inline">
            <?= csrf_campo() ?>
            <button type="submit" class="boton boton-secundario" title="Envía los recordatorios de vencimientos próximos (proceso automático P9)">Ejecutar control de vencimientos</button>
          </form>
          <?php } ?>
          <?php if (puede('crear_operaciones')) { ?>
          <a href="nueva-operacion.php" class="boton boton-primario">+ Nueva reserva</a>
          <?php } ?>
        </div>
      </div>

      <section class="indicadores" aria-label="Resumen">
        <?php if (puede('crear_operaciones')) { ?>
        <a class="indicador <?= $n_pedidos ? 'alerta' : '' ?>" href="pedidos.php" style="color:inherit;text-decoration:none">
          <div class="indicador-valor"><?= $n_pedidos ?></div>
          <div class="indicador-texto">Pedidos de clientes por responder</div>
        </a>
        <?php } ?>
        <div class="indicador">
          <div class="indicador-valor"><?= $n_activas ?></div>
          <div class="indicador-texto">Reservas activas</div>
        </div>
        <div class="indicador <?= $n_cutoffs ? 'alerta' : '' ?>">
          <div class="indicador-valor"><?= $n_cutoffs ?></div>
          <div class="indicador-texto">Vencimientos en los próximos 3 días</div>
        </div>
        <div class="indicador <?= $n_docs ? 'alerta' : '' ?>">
          <div class="indicador-valor"><?= $n_docs ?></div>
          <div class="indicador-texto">Documentos pendientes</div>
        </div>
        <div class="indicador <?= $n_notado ? 'peligro' : '' ?>">
          <div class="indicador-valor"><?= $n_notado ?></div>
          <div class="indicador-texto">“Notado” sin confirmar en plazo</div>
        </div>
        <div class="indicador <?= $n_fallidos ? 'peligro' : '' ?>">
          <div class="indicador-valor"><?= $n_fallidos ?></div>
          <div class="indicador-texto">Aviso no enviado</div>
        </div>
      </section>

      <div class="grilla grilla-2-1">
        <section class="tarjeta">
          <div class="tarjeta-cabecera">
            <h2>Próximos vencimientos</h2>
            <a href="operaciones.php" class="chico">Ver todas las reservas</a>
          </div>
          <div class="tabla-contenedor">
            <table>
              <thead>
                <tr><th>Operación</th><th>Cliente</th><th>Vencimiento</th><th>Fecha</th><th>Faltan</th></tr>
              </thead>
              <tbody>
              <?php foreach ($venc as $v) { $f = faltan($v['fecha']); ?>
                <tr>
                  <td><a href="operacion.php?id=<?= (int) $v['id'] ?>" class="ref"><?= e($v['referencia']) ?></a></td>
                  <td><?= e($v['empresa']) ?></td>
                  <td><?= e($v['tipo']) ?></td>
                  <td class="num"><?= e(f_hora($v['fecha'])) ?></td>
                  <td><?= badge($f['texto'], $f['clase']) ?></td>
                </tr>
              <?php } if (!$venc) { ?>
                <tr><td colspan="5" class="suave">No hay vencimientos próximos.</td></tr>
              <?php } ?>
              </tbody>
            </table>
          </div>
        </section>

        <section class="tarjeta">
          <div class="tarjeta-cabecera"><h2>Requieren atención</h2></div>
          <ul class="lista">
            <?php foreach ($atencion as $a) { ?>
            <li>
              <div>
                <div class="titulo"><?= e($a[0]) ?></div>
                <div class="chico suave"><?= e($a[1]) ?></div>
              </div>
              <a href="<?= e($a[2]) ?>" class="boton boton-secundario boton-chico">Ver</a>
            </li>
            <?php } if (!$atencion) { ?>
            <li><div class="chico suave">Todo al día.</div></li>
            <?php } ?>
          </ul>
        </section>
      </div>

      <?php if (puede('ver_historial')) { ?>
      <section class="tarjeta">
        <div class="tarjeta-cabecera"><h2>Últimos cambios registrados</h2></div>
        <div class="tabla-contenedor">
          <table>
            <thead>
              <tr><th>Fecha</th><th>Operación</th><th>Dato</th><th>Cambio</th><th>Usuario</th><th>Avisos</th></tr>
            </thead>
            <tbody>
            <?php foreach ($cambios as $c) { ?>
              <tr>
                <td class="num"><?= e(f_hora($c['creado'])) ?></td>
                <td><a href="operacion.php?id=<?= (int) $c['operacion_id'] ?>" class="ref"><?= e($c['referencia']) ?></a></td>
                <td><?= e($c['dato']) ?></td>
                <td><span class="cambio-valor"><span class="valor-anterior"><?= e($c['valor_anterior']) ?></span> → <span class="valor-nuevo"><?= e($c['valor_nuevo']) ?></span></span></td>
                <td><?= e($c['usuario']) ?></td>
                <td><?= badge_avisos_cambio($c['id']) ?></td>
              </tr>
            <?php } if (!$cambios) { ?>
              <tr><td colspan="6" class="suave">Todavía no hay cambios registrados.</td></tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php } ?>
<?php pie_personal(); ?>
