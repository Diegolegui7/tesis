<?php
// Inicio del personal (vista simple): qué hay para hacer hoy, en lenguaje común, con un botón por tarea
require_once __DIR__ . '/../partes/layout.php';
$u = requerir_empleado('ver_operaciones');
$gestiona = puede('crear_operaciones');
$admin = puede('administrar_usuarios');
$nombre = explode(' ', $u['nombre'])[0];

$tareas = [];   // [prioridad, título, detalle, enlace, texto del botón]

// 1. Pedidos de clientes por responder
if ($gestiona) {
    foreach (consulta("SELECT s.id, s.cantidad, s.creado, c.empresa, d.pod, d.tipo_contenedor FROM solicitudes s JOIN clientes c ON c.id = s.cliente_id JOIN disponibilidad d ON d.id = s.disponibilidad_id WHERE s.estado = 'pendiente' ORDER BY s.creado LIMIT 5") as $r) {
        $tareas[] = [1, $r['empresa'] . ' pidió lugar', $r['cantidad'] . ' × ' . $r['tipo_contenedor'] . ' a ' . $r['pod'] . ' · pedido el ' . f_hora($r['creado']) . '. Hay que aceptarlo o rechazarlo.', 'pedidos.php', 'Responder'];
    }
}

// 2. Reservas que esperan un paso de ARPAFLU
$activas = consulta(
    "SELECT o.id, o.referencia, o.estado, o.booking, o.orden_retiro, o.cutoff_doc, o.cliente_id, c.empresa, o.pod FROM operaciones o JOIN clientes c ON c.id = o.cliente_id
     WHERE o.estado NOT IN ('zarpado','cancelada') ORDER BY o.cutoff_doc IS NULL, o.cutoff_doc, o.id"
);
$n_activas = count($activas);
foreach ($activas as $o) {
    if (!$gestiona || !puede_actuar_cliente($o['cliente_id'])) { continue; }
    $que = $o['empresa'] . ' · ' . $o['pod'];
    if (!$o['booking']) { $tareas[] = [2, $o['referencia'] . ': falta el booking', $que . '. Cuando la naviera confirme el lugar, cargá el número de booking.', 'operacion.php?id=' . $o['id'], 'Cargar booking']; }
    elseif (!$o['orden_retiro']) { $tareas[] = [2, $o['referencia'] . ': falta la orden de retiro', $que . '. Registrala cuando llegue de la naviera.', 'operacion.php?id=' . $o['id'], 'Cargar orden']; }
    else { $tareas[] = [2, $o['referencia'] . ': esperando que salga el barco', $que . '. Cuando zarpe, registralo para avisar al cliente.', 'operacion.php?id=' . $o['id'], 'Registrar zarpe']; }
}

// 3. Cosas que requieren atención
foreach (consulta('SELECT c.destinatario_nombre, c.evento, o.id AS op_id, o.referencia FROM comunicaciones c JOIN operaciones o ON o.id = c.operacion_id WHERE c.requiere_notado = 1 AND c.notado_en IS NULL AND c.plazo_notado < NOW() ORDER BY c.plazo_notado LIMIT 4') as $r) {
    $tareas[] = [3, $r['destinatario_nombre'] . ' todavía no confirmó un aviso', $r['referencia'] . ': ' . $r['evento'] . '. Pasó el plazo; conviene reenviarlo o llamarlo.', 'operacion.php?id=' . $r['op_id'] . '#comunicaciones', 'Ver'];
}
$fallidos = (int) valor('SELECT COUNT(*) FROM comunicaciones c WHERE c.estado_envio = "fallido" AND NOT EXISTS (SELECT 1 FROM comunicaciones r WHERE r.reenvio_de = c.id)');
if ($fallidos) { $tareas[] = [3, $fallidos == 1 ? 'Un aviso no llegó a destino' : $fallidos . ' avisos no llegaron a destino', 'El correo fue rechazado. Revisá la dirección y reenviá el aviso.', 'avisos.php#fallidos', 'Ver avisos']; }
foreach (consulta("SELECT o.id, o.referencia, c.empresa, o.cutoff_doc FROM operaciones o JOIN clientes c ON c.id = o.cliente_id WHERE o.estado NOT IN ('zarpado','cancelada') AND o.cutoff_doc BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY) ORDER BY o.cutoff_doc LIMIT 4") as $r) {
    $f = faltan($r['cutoff_doc']);
    $tareas[] = [2, $r['referencia'] . ': se acerca el plazo de los documentos', $r['empresa'] . ' · vence el ' . f_hora($r['cutoff_doc']) . ' (faltan ' . $f['texto'] . ').', 'operacion.php?id=' . $r['id'] . '#documentos', 'Ver'];
}
foreach ($tareas as $i => &$t) { $t[] = $i; }
unset($t);
usort($tareas, function ($a, $b) { return $a[0] <=> $b[0] ?: $a[5] <=> $b[5]; });
$mas = max(0, count($tareas) - 6);
$tareas = array_slice($tareas, 0, 6);

$n_pedidos = $gestiona ? contar_pedidos_pendientes() : 0;
$n_avisos = contar_avisos_pendientes();
$con_lugar = $gestiona ? count(disponibilidades(true)) : 0;

// Resumen para quien administra
$resumen = null;
if ($admin) {
    $resumen = [
        'clientes' => (int) valor('SELECT COUNT(*) FROM clientes'),
        'estados' => consulta("SELECT estado, COUNT(*) AS n FROM operaciones GROUP BY estado"),
        'aceptados' => (int) valor("SELECT COUNT(*) FROM solicitudes WHERE estado = 'aceptada'"),
        'rechazados' => (int) valor("SELECT COUNT(*) FROM solicitudes WHERE estado = 'rechazada'"),
        'personal' => (int) valor("SELECT COUNT(*) FROM usuarios WHERE tipo = 'empleado' AND activo = 1"),
    ];
}

cabecera_personal('Inicio', 'inicio');
?>
      <div class="encabezado-pagina">
        <div>
          <h1>Hola, <?= e($nombre) ?></h1>
          <p><?= e(fecha_larga_hoy()) ?></p>
        </div>
        <div class="acciones">
          <?php if ($gestiona) { ?><a href="nueva-operacion.php" class="boton boton-secundario">+ Nueva reserva</a><?php } ?>
        </div>
      </div>

      <section class="cuadro-hacer <?= $tareas ? 'aviso-alerta' : 'aviso-ok' ?>" style="display:block" aria-label="Para hacer hoy">
        <?php if ($tareas) { ?>
        <h2>Para hacer ahora<?= count($tareas) > 1 ? ' (' . count($tareas) . ')' : '' ?></h2>
        <ul class="lista tareas" style="margin-top:8px">
          <?php foreach ($tareas as $t) { ?>
          <li style="padding-left:0;padding-right:0">
            <div><div class="titulo"><?= e($t[1]) ?></div><div class="chico suave"><?= e($t[2]) ?></div></div>
            <a class="boton boton-primario" href="<?= e($t[3]) ?>"><?= e($t[4]) ?></a>
          </li>
          <?php } ?>
        </ul>
        <?php if ($mas) { ?><p class="chico suave" style="margin:10px 0 0">Hay <?= $mas ?> tarea<?= $mas == 1 ? '' : 's' ?> más. Resolvé estas y van apareciendo.</p><?php } ?>
        <?php } else { ?>
        <h2>Todo al día</h2>
        <p style="margin:6px 0 0">No hay nada pendiente por ahora. Cuando un cliente pida lugar o una reserva necesite algo, va a aparecer acá.</p>
        <?php } ?>
      </section>

      <div class="mosaico">
        <?php if ($gestiona) { ?>
        <a class="baldosa <?= $n_pedidos ? 'baldosa-principal' : '' ?>" href="pedidos.php"><span class="baldosa-icono" aria-hidden="true">📥</span><strong>Pedidos de clientes</strong><span><?= $n_pedidos ? $n_pedidos . ' por responder' : 'No hay pedidos por responder' ?></span></a>
        <?php } ?>
        <a class="baldosa" href="operaciones.php"><span class="baldosa-icono" aria-hidden="true">📦</span><strong>Reservas</strong><span><?= $n_activas ?> en curso. Tocá para verlas o buscar una.</span></a>
        <?php if ($gestiona) { ?>
        <a class="baldosa <?= $con_lugar ? '' : 'baldosa-apagada' ?>" href="disponibilidad.php"><span class="baldosa-icono" aria-hidden="true">🚢</span><strong>Lugar disponible</strong><span><?= $con_lugar ? 'Hay ' . $con_lugar . ' embarque' . ($con_lugar == 1 ? '' : 's') . ' abierto' . ($con_lugar == 1 ? '' : 's') . ' para que pidan los clientes.' : 'No hay embarques abiertos: los clientes no pueden pedir.' ?></span></a>
        <?php } ?>
        <a class="baldosa" href="clientes.php"><span class="baldosa-icono" aria-hidden="true">🏢</span><strong>Clientes</strong><span>Empresas y las personas que reciben los avisos.</span></a>
        <a class="baldosa <?= $n_avisos ? 'baldosa-alerta' : '' ?>" href="avisos.php"><span class="baldosa-icono" aria-hidden="true">✉️</span><strong>Avisos enviados</strong><span><?= $n_avisos ? $n_avisos . ' necesitan que los mires' : 'Todo lo que se le mandó a los clientes.' ?></span></a>
        <?php if ($admin) { ?>
        <a class="baldosa" href="usuarios.php"><span class="baldosa-icono" aria-hidden="true">👥</span><strong>Personas y permisos</strong><span>Quién puede entrar y qué puede hacer cada uno.</span></a>
        <?php } ?>
      </div>

      <?php if ($resumen) { $est = estados_operacion(); ?>
      <section class="tarjeta" style="margin-top:18px">
        <div class="tarjeta-cabecera"><h2>Cómo va el negocio</h2></div>
        <div class="tarjeta-cuerpo">
          <div class="fechas">
            <?php foreach ($resumen['estados'] as $r) { ?>
              <div class="fecha"><span class="etiqueta"><?= e($est[$r['estado']][0]) ?></span><strong><?= (int) $r['n'] ?></strong><span class="chico suave">reserva<?= $r['n'] == 1 ? '' : 's' ?></span></div>
            <?php } ?>
            <div class="fecha"><span class="etiqueta">Clientes</span><strong><?= $resumen['clientes'] ?></strong><span class="chico suave">empresas</span></div>
            <div class="fecha"><span class="etiqueta">Pedidos</span><strong><?= $resumen['aceptados'] ?> / <?= $resumen['rechazados'] ?></strong><span class="chico suave">aceptados / rechazados</span></div>
            <div class="fecha"><span class="etiqueta">Equipo</span><strong><?= $resumen['personal'] ?></strong><span class="chico suave">personas con acceso</span></div>
          </div>
        </div>
      </section>
      <?php } ?>

      <p class="ayuda" style="margin-top:16px">¿Necesitás más datos (vencimientos, historial, cotizaciones)? <a href="vista.php?modo=completa">Ver todas las opciones</a>. ¿Dudas? Mirá la <a href="ayuda.php">Ayuda</a>.</p>
<?php pie_personal(); ?>
