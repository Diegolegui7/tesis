<?php
// SS5 · Comunicaciones y avisos
// Cada aviso queda registrado (destinatario, resultado del envío y confirmación “Notado”).
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/util.php';

// Envía un correo. Devuelve [true, null] o [false, 'motivo'].
function enviar_correo($para, $asunto, $cuerpo)
{
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
        return [false, 'La dirección de correo no es válida'];
    }
    if (MAIL_MODO === 'smtp') {
        return enviar_smtp($para, $asunto, $cuerpo);
    }
    // Modo simulado: no sale ningún correo. Se rechazan las direcciones de prueba ".invalid"
    // para poder mostrar cómo se registra un aviso que no se pudo enviar.
    if (preg_match('/\.invalid$/i', $para)) {
        return [false, 'El servidor de correo rechazó la dirección'];
    }
    return [true, null];
}

// Cliente SMTP mínimo: sin cifrado (Mailpit), con STARTTLS (puerto 587) o SSL (puerto 465), con o sin usuario y clave (AUTH LOGIN)
function enviar_smtp($para, $asunto, $cuerpo)
{
    $ssl = SMTP_SEGURIDAD === 'ssl';
    $verificar = !in_array(strtolower((string) SMTP_VERIFICAR_CERT), ['0', 'false', ''], true);
    $opciones = ['ssl' => ['verify_peer' => $verificar, 'verify_peer_name' => $verificar, 'allow_self_signed' => !$verificar]];
    $s = @stream_socket_client(($ssl ? 'ssl://' : 'tcp://') . SMTP_HOST . ':' . (int) SMTP_PORT, $errno, $errstr, 8, STREAM_CLIENT_CONNECT, stream_context_create($opciones));
    if (!$s) {
        return [false, 'No se pudo conectar con el servidor de correo (' . (trim($errstr) !== '' ? trim($errstr) : 'sin respuesta') . ')'];
    }
    stream_set_timeout($s, 10);
    $leer = function () use ($s) {
        $r = '';
        while (($l = fgets($s, 515)) !== false) {
            $r .= $l;
            if (strlen($l) < 4 || $l[3] === ' ') {
                break;
            }
        }
        return $r;
    };
    $cmd = function ($c, $esperado) use ($s, $leer) {
        fwrite($s, $c . "\r\n");
        $r = $leer();
        return strpos($r, (string) $esperado) === 0 ? null : trim($r);
    };
    $falla = function ($err) use ($s) {
        fclose($s);
        return [false, 'El servidor de correo respondió: ' . $err];
    };
    $leer();
    if (($err = $cmd('EHLO arpaflu', 250)) !== null) { return $falla($err); }
    if (SMTP_SEGURIDAD === 'starttls') {
        if (($err = $cmd('STARTTLS', 220)) !== null) { return $falla($err); }
        if (!@stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($s);
            return [false, 'No se pudo establecer la conexión cifrada con el servidor de correo'];
        }
        if (($err = $cmd('EHLO arpaflu', 250)) !== null) { return $falla($err); }
    }
    if (SMTP_USUARIO !== '') {
        if (($err = $cmd('AUTH LOGIN', 334)) !== null) { return $falla($err); }
        if (($err = $cmd(base64_encode(SMTP_USUARIO), 334)) !== null) { return $falla($err); }
        if (($err = $cmd(base64_encode(SMTP_CLAVE), 235)) !== null) { return $falla('usuario o contraseña rechazados (' . $err . ')'); }
    }
    foreach ([['MAIL FROM:<' . MAIL_DE . '>', 250], ['RCPT TO:<' . $para . '>', 250], ['DATA', 354]] as $p) {
        if (($err = $cmd($p[0], $p[1])) !== null) { return $falla($err); }
    }
    $msg = 'From: ARPAFLU <' . MAIL_DE . ">\r\nTo: <" . $para . ">\r\nSubject: =?UTF-8?B?" . base64_encode($asunto) . "?=\r\nDate: " . date('r') . "\r\n"
        . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($cuerpo)) . "\r\n.";
    $err = $cmd($msg, 250);
    $cmd('QUIT', 221);
    fclose($s);
    return $err === null ? [true, null] : [false, 'El servidor de correo respondió: ' . $err];
}

// Registra un aviso y lo envía. $d: operacion_id, cambio_id, evento, tipo, nombre, correo, dest_tipo, asunto, cuerpo, notado (bool)
function registrar_aviso($d)
{
    list($ok, $error) = enviar_correo($d['correo'], $d['asunto'], $d['cuerpo']);
    $notado = !empty($d['notado']) && $ok ? 1 : 0;
    ejecutar(
        'INSERT INTO comunicaciones (operacion_id, cambio_id, evento, tipo, participante_id, destinatario_nombre, destinatario_correo, destinatario_tipo,
            asunto, cuerpo, estado_envio, error, requiere_notado, plazo_notado, reenvio_de)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,' . ($notado ? 'DATE_ADD(NOW(), INTERVAL ' . (int) NOTADO_PLAZO_HORAS . ' HOUR)' : 'NULL') . ',?)',
        [
            isset($d['operacion_id']) ? $d['operacion_id'] : null, isset($d['cambio_id']) ? $d['cambio_id'] : null,
            $d['evento'], $d['tipo'], isset($d['participante_id']) ? $d['participante_id'] : null, $d['nombre'], $d['correo'], $d['dest_tipo'], $d['asunto'], $d['cuerpo'],
            $ok ? 'enviado' : 'fallido', $error, $notado, isset($d['reenvio_de']) ? $d['reenvio_de'] : null,
        ]
    );
    return ['id' => (int) db()->lastInsertId(), 'ok' => $ok, 'error' => $error];
}

// Destinatarios elegidos para una operación (contactos y despachantes del cliente)
function destinatarios_operacion($operacion_id)
{
    return consulta(
        'SELECT p.* FROM operacion_destinatarios od JOIN participantes p ON p.id = od.participante_id
         WHERE od.operacion_id = ? AND p.activo = 1 ORDER BY FIELD(p.rol, "contacto", "despachante", "otro"), p.nombre',
        [$operacion_id]
    );
}

// Aviso interno al empleado responsable de la operación (no pide “Notado”)
function aviso_interno($op, $evento, $tipo, $asunto, $cuerpo, $cambio_id = null)
{
    if (empty($op['responsable_id'])) {
        return null;
    }
    $r = fila('SELECT nombre, correo FROM usuarios WHERE id = ?', [$op['responsable_id']]);
    if (!$r) {
        return null;
    }
    return registrar_aviso([
        'operacion_id' => $op['id'], 'cambio_id' => $cambio_id, 'evento' => $evento, 'tipo' => $tipo,
        'nombre' => $r['nombre'], 'correo' => $r['correo'], 'dest_tipo' => 'interno',
        'asunto' => $asunto, 'cuerpo' => $cuerpo, 'notado' => false,
    ]);
}

// Avisa a los participantes indicados (ids de la tabla participantes). Contactos y despachantes deben confirmar “Notado”.
function avisar_participantes($op, $ids, $evento, $tipo, $asunto, $cuerpo, $cambio_id = null, $pide_notado = true)
{
    $enviados = 0;
    $fallidos = 0;
    foreach ($ids as $pid) {
        $p = fila('SELECT * FROM participantes WHERE id = ? AND activo = 1', [(int) $pid]);
        if (!$p) {
            continue;
        }
        $r = registrar_aviso([
            'operacion_id' => $op['id'], 'cambio_id' => $cambio_id, 'evento' => $evento, 'tipo' => $tipo,
            'participante_id' => $p['id'], 'nombre' => $p['nombre'], 'correo' => $p['correo'],
            'dest_tipo' => $p['rol'] === 'despachante' ? 'despachante' : ($p['rol'] === 'contacto' ? 'cliente' : 'otro'),
            'asunto' => $asunto, 'cuerpo' => $cuerpo, 'notado' => $pide_notado && $p['rol'] !== 'otro',
        ]);
        $r['ok'] ? $enviados++ : $fallidos++;
    }
    return [$enviados, $fallidos];
}

function reenviar_aviso($id)
{
    $c = fila('SELECT * FROM comunicaciones WHERE id = ?', [(int) $id]);
    if (!$c) {
        return null;
    }
    // si el contacto cambió su correo, se reenvía a la dirección vigente
    $p = $c['participante_id'] ? fila('SELECT nombre, correo FROM participantes WHERE id = ? AND activo = 1', [$c['participante_id']]) : null;
    $r = registrar_aviso([
        'operacion_id' => $c['operacion_id'], 'cambio_id' => $c['cambio_id'], 'evento' => $c['evento'], 'tipo' => $c['tipo'], 'participante_id' => $c['participante_id'],
        'nombre' => $p ? $p['nombre'] : $c['destinatario_nombre'], 'correo' => $p ? $p['correo'] : $c['destinatario_correo'], 'dest_tipo' => $c['destinatario_tipo'],
        'asunto' => $c['asunto'], 'cuerpo' => $c['cuerpo'], 'notado' => (bool) $c['requiere_notado'], 'reenvio_de' => $c['id'],
    ]);
    if ($r['ok'] && (int) $c['requiere_notado'] && $c['notado_en'] === null) {
        // el reenvío reemplaza al aviso pendiente: se cierra el anterior para que no cuente doble
        ejecutar('UPDATE comunicaciones SET requiere_notado = 0 WHERE id = ?', [$c['id']]);
    }
    return $r;
}

// Registra la confirmación “Notado” (RF-17). Acredita la intervención del usuario, no la entrega del correo.
function confirmar_notado($comunicacion_id, $usuario_id)
{
    return ejecutar(
        'UPDATE comunicaciones SET notado_en = NOW(), notado_por = ? WHERE id = ? AND requiere_notado = 1 AND notado_en IS NULL',
        [$usuario_id, (int) $comunicacion_id]
    );
}

// Resumen de avisos de un cambio (para el panel y el historial)
function resumen_avisos_cambio($cambio_id)
{
    $r = fila(
        'SELECT COUNT(*) total,
                SUM(estado_envio = "fallido") fallidos,
                SUM(requiere_notado = 1) requeridos,
                SUM(requiere_notado = 1 AND notado_en IS NOT NULL) notados
         FROM comunicaciones WHERE cambio_id = ?',
        [$cambio_id]
    );
    return ['total' => (int) $r['total'], 'fallidos' => (int) $r['fallidos'], 'requeridos' => (int) $r['requeridos'], 'notados' => (int) $r['notados']];
}

function badge_avisos_cambio($cambio_id)
{
    $r = resumen_avisos_cambio($cambio_id);
    if ($r['total'] === 0) {
        return '<span class="suave">Sin avisos</span>';
    }
    if ($r['fallidos'] > 0) {
        return badge($r['fallidos'] . ' falló', 'estado-peligro');
    }
    if ($r['requeridos'] > 0 && $r['notados'] < $r['requeridos']) {
        return badge($r['notados'] . ' de ' . $r['requeridos'] . ' notados', 'estado-alerta');
    }
    return badge($r['requeridos'] > 0 ? 'Todos notados' : 'Enviado', 'estado-ok');
}

// Cantidad de avisos que requieren atención (no enviados sin reenvío exitoso + “Notado” vencidos)
function contar_avisos_pendientes()
{
    $f = (int) valor(
        'SELECT COUNT(*) FROM comunicaciones c WHERE c.estado_envio = "fallido"
         AND NOT EXISTS (SELECT 1 FROM comunicaciones r WHERE r.reenvio_de = c.id)'
    );
    $n = (int) valor('SELECT COUNT(*) FROM comunicaciones WHERE requiere_notado = 1 AND notado_en IS NULL AND plazo_notado < NOW()');
    return $f + $n;
}
