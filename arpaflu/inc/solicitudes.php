<?php
// SS2 · Disponibilidad de espacio y pedidos de reserva de los clientes (RF-21 y RF-22)
// Un cliente solo puede pedir lugar si hay cupo libre. Un pedido pendiente o aceptado ocupa cupo; uno rechazado o cancelado lo libera.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/util.php';
require_once __DIR__ . '/avisos.php';

// Embarques con espacio. Cada fila trae “libres” (cupo menos pedidos pendientes y aceptados) y “pedible” (abierto, antes del cut-off y con lugar).
function disponibilidades($solo_pedibles = false)
{
    $filas = consulta(
        "SELECT d.*, n.nombre AS naviera,
                d.cupo - COALESCE((SELECT SUM(s.cantidad) FROM solicitudes s WHERE s.disponibilidad_id = d.id AND s.estado IN ('pendiente','aceptada')), 0) AS libres,
                (SELECT COUNT(*) FROM solicitudes s WHERE s.disponibilidad_id = d.id AND s.estado = 'pendiente') AS pendientes
         FROM disponibilidad d LEFT JOIN navieras n ON n.id = d.naviera_id
         ORDER BY d.etd IS NULL, d.etd, d.id"
    );
    $out = [];
    foreach ($filas as $f) {
        $f['libres'] = max(0, (int) $f['libres']);
        $vigente = $f['cutoff_doc'] === null || strtotime($f['cutoff_doc']) > time();
        $f['pedible'] = $f['estado'] === 'abierta' && $vigente && $f['libres'] > 0;
        $f['motivo'] = $f['estado'] !== 'abierta' ? 'Cerrado' : (!$vigente ? 'Venció el plazo de documentos' : ($f['libres'] <= 0 ? 'Completo' : ''));
        if (!$solo_pedibles || $f['pedible']) { $out[] = $f; }
    }
    return $out;
}

// Un usuario cliente puede pedir reservas si su cuenta está habilitada (la misma habilitación que para elegir alternativas)
function puede_pedir($u)
{
    return $u && $u['tipo'] === 'cliente' && !empty($u['puede_seleccionar']);
}

// Registra el pedido de un cliente. Devuelve [ok, mensaje, id]
function crear_solicitud($u, $disp_id, $cantidad, $carga, $comentario)
{
    if (!puede_pedir($u)) { return [false, 'Tu cuenta no está habilitada para pedir reservas.', null]; }
    $cantidad = (int) $cantidad;
    if ($cantidad < 1) { return [false, 'Indicá cuántos contenedores necesitás.', null]; }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $d = fila('SELECT d.*, n.nombre AS naviera FROM disponibilidad d LEFT JOIN navieras n ON n.id = d.naviera_id WHERE d.id = ? FOR UPDATE', [(int) $disp_id]);
        if (!$d) { $pdo->rollBack(); return [false, 'Ese embarque no existe.', null]; }
        $ocupados = (int) valor("SELECT COALESCE(SUM(cantidad), 0) FROM solicitudes WHERE disponibilidad_id = ? AND estado IN ('pendiente','aceptada')", [$d['id']]);
        $libres = max(0, (int) $d['cupo'] - $ocupados);
        if ($d['estado'] !== 'abierta') { $pdo->rollBack(); return [false, 'Este embarque ya no está disponible.', null]; }
        if ($d['cutoff_doc'] !== null && strtotime($d['cutoff_doc']) <= time()) { $pdo->rollBack(); return [false, 'Ya pasó el plazo para pedir lugar en este embarque.', null]; }
        if ($libres <= 0) { $pdo->rollBack(); return [false, 'Este embarque se completó.', null]; }
        if ($cantidad > $libres) { $pdo->rollBack(); return [false, 'Solo quedan ' . $libres . ' lugar' . ($libres == 1 ? '' : 'es') . ' en este embarque. Pedí ' . $libres . ' o menos.', null]; }
        ejecutar('INSERT INTO solicitudes (cliente_id, usuario_id, disponibilidad_id, cantidad, carga, comentario) VALUES (?,?,?,?,?,?)',
            [$u['cliente_id'], $u['id'], $d['id'], $cantidad, trim($carga) !== '' ? trim($carga) : null, trim($comentario) !== '' ? trim($comentario) : null]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $ex;
    }
    // Aviso interno al responsable de la cuenta
    $c = fila('SELECT c.empresa, r.nombre, r.correo FROM clientes c LEFT JOIN usuarios r ON r.id = c.responsable_id WHERE c.id = ?', [$u['cliente_id']]);
    if ($c && $c['correo']) {
        registrar_aviso([
            'operacion_id' => null, 'cambio_id' => null, 'evento' => 'Pedido de reserva', 'tipo' => 'solicitud',
            'nombre' => $c['nombre'], 'correo' => $c['correo'], 'dest_tipo' => 'interno',
            'asunto' => $c['empresa'] . ' pidió ' . $cantidad . ' × ' . $d['tipo_contenedor'] . ' a ' . $d['pod'],
            'cuerpo' => $u['nombre'] . ' (' . $c['empresa'] . ') pidió ' . $cantidad . ' × ' . $d['tipo_contenedor'] . ' en ' . $d['buque'] . ' (' . $d['naviera'] . '), '
                . $d['pol'] . ' → ' . $d['pod'] . ', zarpe ' . f_fecha($d['etd']) . '.' . (trim($carga) !== '' ? ' Carga: ' . trim($carga) . '.' : '')
                . (trim($comentario) !== '' ? ' Comentario: ' . trim($comentario) : '') . ' Falta responder el pedido en la pantalla Pedidos.',
            'notado' => false,
        ]);
    }
    return [true, 'Recibimos tu pedido.', $id];
}

// El cliente cancela un pedido que todavía no fue respondido
function cancelar_solicitud_cliente($u, $id)
{
    $s = fila("SELECT * FROM solicitudes WHERE id = ? AND cliente_id = ? AND estado = 'pendiente'", [(int) $id, $u['cliente_id']]);
    if (!$s) { return [false, 'Ese pedido ya no se puede cancelar.']; }
    $n = ejecutar("UPDATE solicitudes SET estado = 'cancelada', respondido_en = NOW() WHERE id = ? AND estado = 'pendiente'", [$s['id']]);
    return $n ? [true, 'Cancelamos tu pedido. El lugar quedó libre para otros clientes.'] : [false, 'Ese pedido ya no se puede cancelar.'];
}

function _aviso_a_solicitante($s, $op, $evento, $asunto, $cuerpo)
{
    $usr = fila('SELECT nombre, correo FROM usuarios WHERE id = ?', [$s['usuario_id']]);
    if (!$usr) { return; }
    registrar_aviso([
        'operacion_id' => $op ? $op['id'] : null, 'cambio_id' => null, 'evento' => $evento, 'tipo' => 'solicitud',
        'nombre' => $usr['nombre'], 'correo' => $usr['correo'], 'dest_tipo' => 'cliente',
        'asunto' => $asunto, 'cuerpo' => $cuerpo, 'notado' => false,
    ]);
}

// ARPAFLU acepta el pedido: crea la operación (estado “reserva solicitada”) y avisa al cliente. Devuelve [ok, mensaje, operacion_id]
function aceptar_solicitud($emp, $id)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $s = fila("SELECT s.*, c.empresa, c.responsable_id FROM solicitudes s JOIN clientes c ON c.id = s.cliente_id WHERE s.id = ? FOR UPDATE", [(int) $id]);
        if (!$s) { $pdo->rollBack(); return [false, 'No existe ese pedido.', null]; }
        if (!puede_actuar_cliente($s['cliente_id'])) { $pdo->rollBack(); return [false, 'No tenés autorización para responder pedidos de ' . $s['empresa'] . '.', null]; }
        if ($s['estado'] !== 'pendiente') { $pdo->rollBack(); return [false, 'Ese pedido ya fue respondido.', null]; }
        $d = fila('SELECT * FROM disponibilidad WHERE id = ?', [$s['disponibilidad_id']]);
        $n = (int) valor("SELECT COALESCE(MAX(CAST(SUBSTRING(referencia, 5) AS UNSIGNED)), 0) + 1 FROM operaciones FOR UPDATE");
        $ref = 'ARP-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
        $resp = (int) $s['responsable_id'] ?: (int) $emp['id'];
        $semana = $d['etd'] ? (int) date('W', strtotime($d['etd'])) : null;
        ejecutar(
            'INSERT INTO operaciones (referencia, cliente_id, responsable_id, naviera_id, buque, semana, pol, pod, via, cantidad, tipo_contenedor, dias_libres,
                etd, eta, cutoff_doc, estado, observaciones, creado_por)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"reserva_solicitada",?,?)',
            [$ref, $s['cliente_id'], $resp, $d['naviera_id'], $d['buque'], $semana, $d['pol'], $d['pod'], $d['via'], $s['cantidad'], $d['tipo_contenedor'], $d['dias_libres'],
             $d['etd'], $d['eta'], $d['cutoff_doc'], 'Pedido del cliente' . ($s['carga'] ? '. Carga: ' . $s['carga'] : '') . ($s['comentario'] ? '. ' . $s['comentario'] : ''), $emp['id']]
        );
        $op_id = (int) $pdo->lastInsertId();
        foreach (consulta("SELECT id FROM participantes WHERE cliente_id = ? AND activo = 1 AND rol IN ('contacto','despachante')", [$s['cliente_id']]) as $p) {
            ejecutar('INSERT IGNORE INTO operacion_destinatarios (operacion_id, participante_id) VALUES (?,?)', [$op_id, $p['id']]);
        }
        ejecutar("UPDATE solicitudes SET estado = 'aceptada', operacion_id = ?, respondido_por = ?, respondido_en = NOW() WHERE id = ?", [$op_id, $emp['id'], $s['id']]);
        $pdo->commit();
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $ex;
    }
    $op = fila('SELECT * FROM operaciones WHERE id = ?', [$op_id]);
    _aviso_a_solicitante($s, $op, 'Pedido aceptado', $ref . ' · Aceptamos tu pedido',
        'Aceptamos tu pedido de ' . $s['cantidad'] . ' × ' . $d['tipo_contenedor'] . ' a ' . $d['pod'] . ' (' . $d['buque'] . ', zarpe ' . f_fecha($d['etd']) . "). "
        . 'Tu reserva es la ' . $ref . '. Ahora ARPAFLU confirma el lugar con la naviera y te avisa. Podés seguirla desde el portal.');
    if ((int) $resp !== (int) $emp['id']) {
        aviso_interno($op, 'Nueva operación asignada', 'otro', $ref . ' · Nueva operación asignada',
            $emp['nombre'] . ' aceptó un pedido de ' . $s['empresa'] . ' y te asignó la operación ' . $ref . '.');
    }
    return [true, 'Pedido aceptado. Se creó la reserva ' . $ref . '.', $op_id];
}

// ARPAFLU rechaza el pedido con un motivo; el cupo vuelve a quedar libre
function rechazar_solicitud($emp, $id, $motivo)
{
    $motivo = trim($motivo);
    if (mb_strlen($motivo) < 3) { return [false, 'Escribí el motivo para que el cliente sepa qué pasó.']; }
    $s = fila('SELECT s.*, c.empresa FROM solicitudes s JOIN clientes c ON c.id = s.cliente_id WHERE s.id = ?', [(int) $id]);
    if (!$s) { return [false, 'No existe ese pedido.']; }
    if (!puede_actuar_cliente($s['cliente_id'])) { return [false, 'No tenés autorización para responder pedidos de ' . $s['empresa'] . '.']; }
    $n = ejecutar("UPDATE solicitudes SET estado = 'rechazada', motivo_rechazo = ?, respondido_por = ?, respondido_en = NOW() WHERE id = ? AND estado = 'pendiente'", [$motivo, $emp['id'], $s['id']]);
    if (!$n) { return [false, 'Ese pedido ya fue respondido.']; }
    $d = fila('SELECT * FROM disponibilidad WHERE id = ?', [$s['disponibilidad_id']]);
    _aviso_a_solicitante($s, null, 'Pedido no aceptado', 'No pudimos aceptar tu pedido a ' . $d['pod'],
        'No pudimos aceptar tu pedido de ' . $s['cantidad'] . ' × ' . $d['tipo_contenedor'] . ' a ' . $d['pod'] . '. Motivo: ' . $motivo . '. Si querés, podés pedir lugar en otro embarque desde el portal o consultar a tu responsable.');
    return [true, 'Pedido rechazado. El cliente fue avisado.'];
}

// Marca como vistos los pedidos ya respondidos cuando el cliente entra a su inicio (lo usa la guía de demostración)
function marcar_pedidos_vistos($cliente_id)
{
    ejecutar("UPDATE solicitudes SET visto_cliente = NOW() WHERE cliente_id = ? AND estado IN ('aceptada','rechazada') AND visto_cliente IS NULL", [$cliente_id]);
}

function contar_pedidos_pendientes()
{
    return (int) valor("SELECT COUNT(*) FROM solicitudes WHERE estado = 'pendiente'");
}

function texto_estado_pedido($estado)
{
    $m = ['pendiente' => ['Pedido enviado: ARPAFLU lo está revisando', 'estado-alerta'],
          'aceptada' => ['Aceptado: ya es una reserva', 'estado-ok'],
          'rechazada' => ['No se pudo aceptar', 'estado-peligro'],
          'cancelada' => ['Cancelado por vos', 'estado-neutro']];
    return isset($m[$estado]) ? $m[$estado] : [$estado, 'estado-neutro'];
}
