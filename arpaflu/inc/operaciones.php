<?php
// SS2 / SS3 / SS4 · Reglas de negocio sobre una operación: cambios, documentos, booking, orden de retiro, zarpe y cancelación
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/util.php';
require_once __DIR__ . '/avisos.php';

function orden_estados()
{
    return ['reserva_solicitada' => 0, 'booking_confirmado' => 1, 'orden_retiro' => 2, 'documentacion' => 3, 'zarpado' => 4];
}

// El estado solo avanza (salvo cancelación)
function avanzar_estado($op_id, $nuevo)
{
    $o = orden_estados();
    $actual = valor('SELECT estado FROM operaciones WHERE id = ?', [$op_id]);
    if (isset($o[$actual]) && isset($o[$nuevo]) && $o[$nuevo] > $o[$actual]) {
        ejecutar('UPDATE operaciones SET estado = ? WHERE id = ?', [$nuevo, $op_id]);
    }
}

function cargar_operacion($id)
{
    return fila(
        'SELECT o.*, c.empresa, c.responsable_id AS cliente_responsable, n.nombre AS naviera, t.nombre AS terminal, r.nombre AS responsable,
                q.codigo AS cot_codigo, q.version AS cot_version, q.flete AS cot_flete, b.bandera
         FROM operaciones o
         JOIN clientes c ON c.id = o.cliente_id
         LEFT JOIN navieras n ON n.id = o.naviera_id
         LEFT JOIN terminales t ON t.id = o.terminal_id
         LEFT JOIN usuarios r ON r.id = o.responsable_id
         LEFT JOIN cotizaciones q ON q.id = o.cotizacion_id
         LEFT JOIN buques b ON b.nombre = o.buque
         WHERE o.id = ?',
        [(int) $id]
    );
}

// Campos que se pueden cambiar con “Registrar cambio” (P8)
function datos_cambiables()
{
    return [
        'Buque' => ['campo' => 'buque', 'tipo' => 'texto'],
        'ETD' => ['campo' => 'etd', 'tipo' => 'fecha'],
        'ETA' => ['campo' => 'eta', 'tipo' => 'fecha'],
        'Cut-off documental' => ['campo' => 'cutoff_doc', 'tipo' => 'fechahora'],
        'Cut-off físico' => ['campo' => 'cutoff_fisico', 'tipo' => 'fechahora'],
        'Fecha límite de VGM' => ['campo' => 'vgm_limite', 'tipo' => 'fechahora'],
        'Terminal' => ['campo' => 'terminal', 'tipo' => 'terminal'],
        'Roleo (buque y fechas)' => ['campo' => 'buque', 'tipo' => 'roleo'],
        'Novedad posterior al zarpe' => ['campo' => null, 'tipo' => 'otro'],
        'Otro' => ['campo' => null, 'tipo' => 'otro'],
    ];
}

function valor_actual_texto($op, $dato)
{
    $m = datos_cambiables();
    if (!isset($m[$dato])) {
        return '';
    }
    switch ($m[$dato]['tipo']) {
        case 'fecha': return f_input_d($op[$m[$dato]['campo']]);
        case 'fechahora': return f_input_dt($op[$m[$dato]['campo']]);
        case 'terminal': return (string) $op['terminal'];
        case 'otro': return '';
        default: return (string) $op['buque'];
    }
}

// Registra un cambio, actualiza la operación y avisa. Devuelve [ok(bool), mensaje]
function registrar_cambio($op, $usuario, $dato, $nuevo, $origen, $detalle, $avisar, $etd_nuevo = '', $cutoff_nuevo = '')
{
    $m = datos_cambiables();
    if (!isset($m[$dato])) {
        return [false, 'Elegí qué dato cambia.'];
    }
    $def = $m[$dato];
    // Una operación cancelada no admite cambios; después del zarpe solo se registran la ETA y las novedades (RF-19)
    if ($op['estado'] === 'cancelada') { return [false, 'La operación está cancelada: no admite cambios.']; }
    if ($op['estado'] === 'zarpado' && !in_array($dato, ['ETA', 'Novedad posterior al zarpe'], true)) {
        return [false, 'Después del zarpe solo se registran cambios de ETA y novedades posteriores.'];
    }
    $nuevo = trim($nuevo);
    $items = []; // [dato, anterior, nuevo, campoSQL, valorSQL]

    switch ($def['tipo']) {
        case 'texto':
        case 'roleo':
            if ($nuevo === '') { return [false, 'Indicá el valor nuevo.']; }
            $items[] = [$def['tipo'] === 'roleo' ? 'Roleo: cambio de buque' : 'Buque', (string) $op['buque'], $nuevo, 'buque', $nuevo];
            if ($def['tipo'] === 'roleo') {
                if (trim($etd_nuevo) !== '') {
                    $f = parse_fecha($etd_nuevo);
                    if ($f === false) { return [false, 'El nuevo ETD no se entiende (usá dd/mm/aaaa).']; }
                    $items[] = ['ETD', f_input_d($op['etd']), f_input_d($f), 'etd', $f];
                }
                if (trim($cutoff_nuevo) !== '') {
                    $f = parse_fecha($cutoff_nuevo, true);
                    if ($f === false) { return [false, 'El nuevo cut-off no se entiende (usá dd/mm/aaaa hh:mm).']; }
                    $items[] = ['Cut-off documental', f_input_dt($op['cutoff_doc']), f_input_dt($f), 'cutoff_doc', $f];
                }
            }
            break;
        case 'fecha':
        case 'fechahora':
            $con_hora = $def['tipo'] === 'fechahora';
            $f = parse_fecha($nuevo, $con_hora);
            if ($f === null) { return [false, 'Indicá el valor nuevo.']; }
            if ($f === false) { return [false, 'La fecha no se entiende (usá ' . ($con_hora ? 'dd/mm/aaaa hh:mm' : 'dd/mm/aaaa') . ').']; }
            $ant = $con_hora ? f_input_dt($op[$def['campo']]) : f_input_d($op[$def['campo']]);
            $items[] = [$dato, $ant, $con_hora ? f_input_dt($f) : f_input_d($f), $def['campo'], $f];
            break;
        case 'terminal':
            if ($nuevo === '') { return [false, 'Indicá el valor nuevo.']; }
            $tid = valor('SELECT id FROM terminales WHERE nombre = ?', [$nuevo]);
            if (!$tid) {
                ejecutar('INSERT INTO terminales (nombre) VALUES (?)', [$nuevo]);
                $tid = db()->lastInsertId();
            }
            $items[] = ['Terminal', (string) $op['terminal'], $nuevo, 'terminal_id', (int) $tid];
            break;
        default: // 'otro': solo deja constancia
            if ($nuevo === '' && trim($detalle) === '') { return [false, 'Describí el cambio en el valor nuevo o en el detalle.']; }
            $items[] = [$dato, '', $nuevo !== '' ? $nuevo : 'Ver detalle', null, null];
    }

    // Sin cambios reales
    $hay = false;
    foreach ($items as $it) {
        if ($it[1] !== $it[2]) { $hay = true; }
    }
    if (!$hay) { return [false, 'El valor nuevo es igual al actual.']; }

    $primero = null;
    foreach ($items as $it) {
        if ($it[3] !== null) {
            ejecutar('UPDATE operaciones SET ' . $it[3] . ' = ? WHERE id = ?', [$it[4], $op['id']]);
        }
        ejecutar(
            'INSERT INTO historial_cambios (operacion_id, dato, valor_anterior, valor_nuevo, origen, detalle, usuario_id) VALUES (?,?,?,?,?,?,?)',
            [$op['id'], $it[0], $it[1] !== '' ? $it[1] : null, $it[2], $origen, $detalle !== '' ? $detalle : null, $usuario['id']]
        );
        if ($primero === null) { $primero = (int) db()->lastInsertId(); }
    }

    // Avisos
    $evento = $def['tipo'] === 'roleo' ? 'Cambio de buque y fechas' : ($dato === 'Novedad posterior al zarpe' ? $dato : 'Cambio de ' . mb_strtolower($dato));
    $frases = [
        'Roleo: cambio de buque' => 'El barco cambió', 'Buque' => 'El barco cambió', 'ETD' => 'La fecha de salida cambió', 'ETA' => 'La fecha de llegada cambió',
        'Cut-off documental' => 'El último momento para entregar los documentos cambió', 'Cut-off físico' => 'El último momento para llevar la carga al puerto cambió',
        'Fecha límite de VGM' => 'El plazo para informar el peso (VGM) cambió', 'Terminal' => 'La terminal cambió',
    ];
    $lineas = [];
    $amigables = [];
    foreach ($items as $it) {
        $lineas[] = '- ' . $it[0] . ': ' . ($it[1] !== '' ? $it[1] : '(sin dato)') . ' → ' . $it[2];
        $amigables[] = '- ' . (isset($frases[$it[0]]) ? $frases[$it[0]] : $it[0] . ' cambió') . ': antes ' . ($it[1] !== '' ? $it[1] : '(sin dato)') . ', ahora ' . $it[2] . '.';
    }
    $cuerpo = 'Hubo un cambio en tu reserva ' . $op['referencia'] . ' (' . $op['empresa'] . "):\n" . implode("\n", $amigables)
        . "\nQuién lo informó: " . $origen . ($detalle !== '' ? "\nDetalle: " . $detalle : '')
        . "\n\nPor favor confirmá que lo leíste tocando “Notado” en el portal de ARPAFLU, o respondiendo este correo.";
    $asunto = $op['referencia'] . ' · ' . $evento;

    $ids = [];
    $interno = false;
    foreach ($avisar as $a) {
        if ($a === 'responsable') { $interno = true; } else { $ids[] = (int) $a; }
    }
    list($env, $fall) = avisar_participantes($op, $ids, $evento, 'cambio', $asunto, $cuerpo, $primero);
    if ($interno) {
        $r = aviso_interno($op, $evento, 'cambio', $asunto, 'Se registró el cambio: ' . implode('; ', array_map(function ($l) { return ltrim($l, '- '); }, $lineas)), $primero);
        if ($r && $r['ok']) { $env++; } else { $fall++; }
    }
    $msg = 'Cambio registrado.' . ($env + $fall ? ' Avisos enviados: ' . $env . ($fall ? ' · no enviados: ' . $fall : '') . '.' : ' No se avisó a nadie.');
    return [true, $msg];
}

// Carga un documento (versión nueva si ya existe uno del mismo tipo)
function guardar_documento($op, $usuario, $tipo, $estado, $origen, $obs, $visible, $archivo)
{
    $estados = ['solicitado', 'recibido', 'revisado', 'presentado'];
    if ($op['estado'] === 'cancelada') { return [false, 'La operación está cancelada: no admite documentos nuevos.']; }
    if (!in_array($estado, $estados, true)) { return [false, 'Estado de documento no válido.']; }
    if (trim($tipo) === '') { return [false, 'Elegí el tipo de documento.']; }
    $nombre = null;
    $ruta = null;
    if ($archivo !== null) {
        if (is_string($archivo)) { return [false, $archivo]; }
        list($nombre, $ruta) = $archivo;
    } elseif ($estado !== 'solicitado') {
        return [false, 'Adjuntá el archivo (solo un documento “solicitado” puede cargarse sin archivo).'];
    }
    $version = 1 + (int) valor('SELECT COALESCE(MAX(version), 0) FROM documentos WHERE operacion_id = ? AND tipo = ?', [$op['id'], $tipo]);
    ejecutar(
        'INSERT INTO documentos (operacion_id, tipo, version, estado, origen, archivo_nombre, archivo_ruta, observaciones, visible_cliente, subido_por)
         VALUES (?,?,?,?,?,?,?,?,?,?)',
        [$op['id'], $tipo, $version, $estado, $origen !== '' ? $origen : null, $nombre, $ruta, $obs !== '' ? $obs : null, $visible ? 1 : 0, $usuario['id']]
    );
    if (preg_match('/^(Declaración|DRAFT|VGM|Corrección)/u', $tipo) && $estado !== 'solicitado') {
        avanzar_estado($op['id'], 'documentacion');
    }
    return [true, 'Documento guardado (' . $tipo . ' v' . $version . ').'];
}

// P4 · Confirmar booking
function confirmar_booking($op, $booking, $buque)
{
    $booking = trim($booking);
    if ($booking === '') { return [false, 'Indicá el número de booking.']; }
    ejecutar(
        'UPDATE operaciones SET booking = ?, buque = COALESCE(NULLIF(?, ""), buque), booking_confirmado_en = NOW() WHERE id = ?',
        [$booking, trim($buque), $op['id']]
    );
    avanzar_estado($op['id'], 'booking_confirmado');
    $op = cargar_operacion($op['id']);
    $ids = array_column(destinatarios_operacion($op['id']), 'id');
    list($env, $fall) = avisar_participantes($op, $ids, 'Booking confirmado', 'booking', $op['referencia'] . ' · Booking confirmado',
        'Booking ' . $booking . ' confirmado por ' . $op['naviera'] . ($op['buque'] ? ' en el buque ' . $op['buque'] : '') . '.', null, false);
    return [true, 'Booking confirmado. Avisos enviados: ' . $env . ($fall ? ' · no enviados: ' . $fall : '') . '.'];
}

// P6 · Orden de retiro
function registrar_orden_retiro($op, $codigo, $fecha)
{
    $codigo = trim($codigo);
    if ($codigo === '') { return [false, 'Indicá el número de orden de retiro.']; }
    $f = trim($fecha) === '' ? date('Y-m-d') : parse_fecha($fecha);
    if ($f === false) { return [false, 'La fecha no se entiende (usá dd/mm/aaaa).']; }
    ejecutar('UPDATE operaciones SET orden_retiro = ?, orden_retiro_fecha = ? WHERE id = ?', [$codigo, $f, $op['id']]);
    avanzar_estado($op['id'], 'orden_retiro');
    $ids = array_column(destinatarios_operacion($op['id']), 'id');
    list($env, $fall) = avisar_participantes($op, $ids, 'Orden de retiro recibida', 'documento', $op['referencia'] . ' · Orden de retiro',
        'Se recibió la orden de retiro ' . $codigo . ' para el booking ' . $op['booking'] . '.', null, false);
    return [true, 'Orden de retiro registrada. Avisos enviados: ' . $env . ($fall ? ' · no enviados: ' . $fall : '') . '.'];
}

// P12 · Zarpe confirmado
function registrar_zarpe($op, $usuario)
{
    if (in_array($op['estado'], ['zarpado', 'cancelada'], true)) { return [false, 'La operación ya está ' . ($op['estado'] === 'zarpado' ? 'zarpada' : 'cancelada') . '.']; }
    $est = estados_operacion();
    ejecutar('UPDATE operaciones SET estado = "zarpado", zarpe_confirmado = NOW() WHERE id = ?', [$op['id']]);
    ejecutar('INSERT INTO historial_cambios (operacion_id, dato, valor_anterior, valor_nuevo, origen, usuario_id) VALUES (?,?,?,?,?,?)',
        [$op['id'], 'Estado', $est[$op['estado']][0], 'Zarpado', 'Confirmación de zarpe', $usuario['id']]);
    $ids = array_column(destinatarios_operacion($op['id']), 'id');
    list($env, $fall) = avisar_participantes($op, $ids, 'Zarpe confirmado', 'zarpe', $op['referencia'] . ' · Zarpe confirmado',
        'El buque ' . $op['buque'] . ' zarpó. La operación queda en seguimiento: el arribo y la liberación se registran con información verificada.', null, false);
    return [true, 'Zarpe registrado. Avisos enviados: ' . $env . ($fall ? ' · no enviados: ' . $fall : '') . '.'];
}

// P11 · Cancelar reserva (la operación se conserva con sus antecedentes)
function cancelar_operacion($op, $usuario, $origen, $motivo, $archivo)
{
    if (in_array($op['estado'], ['zarpado', 'cancelada'], true)) { return [false, 'Esta operación ya no se puede cancelar.']; }
    if (trim($motivo) === '') { return [false, 'Indicá el motivo de la cancelación.']; }
    if (!in_array($origen, ['cliente', 'naviera', 'arpaflu'], true)) { return [false, 'Indicá quién solicita la cancelación.']; }
    $ruta = null;
    if ($archivo !== null) {
        if (is_string($archivo)) { return [false, $archivo]; }
        $ruta = $archivo[1];
    }
    ejecutar('UPDATE operaciones SET estado = "cancelada", cancelada_por = ?, cancelacion_motivo = ?, cancelada_en = NOW(), cancelacion_archivo = ? WHERE id = ?',
        [$origen, trim($motivo), $ruta, $op['id']]);
    $est = estados_operacion();
    ejecutar('INSERT INTO historial_cambios (operacion_id, dato, valor_anterior, valor_nuevo, origen, detalle, usuario_id) VALUES (?,?,?,?,?,?,?)',
        [$op['id'], 'Estado', $est[$op['estado']][0], 'Cancelada', 'Solicitada por ' . $origen, trim($motivo), $usuario['id']]);
    $ids = array_column(destinatarios_operacion($op['id']), 'id');
    avisar_participantes($op, $ids, 'Reserva cancelada', 'cancelacion', $op['referencia'] . ' · Reserva cancelada',
        'La reserva ' . $op['referencia'] . ' fue cancelada. Motivo: ' . trim($motivo), null, false);
    return [true, 'Operación cancelada. Queda disponible para consultas con todos sus antecedentes.'];
}

// Contenedores
function guardar_contenedor($op, $d)
{
    $num = trim($d['numero']);
    if ($num === '') { return [false, 'Indicá el número de contenedor.']; }
    $f = parse_fecha(isset($d['retiro_fecha']) ? $d['retiro_fecha'] : '');
    if ($f === false) { return [false, 'La fecha de retiro no se entiende (usá dd/mm/aaaa).']; }
    $vgm = in_array($d['vgm'], ['pendiente', 'declarado', 'transmitido'], true) ? $d['vgm'] : 'pendiente';
    ejecutar('INSERT INTO contenedores (operacion_id, numero, tipo, retiro_fecha, retiro_lugar, peso_bruto, vgm, precinto) VALUES (?,?,?,?,?,?,?,?)',
        [$op['id'], $num, $op['tipo_contenedor'], $f, trim($d['retiro_lugar']) ?: null, $d['peso_bruto'] !== '' ? (int) $d['peso_bruto'] : null, $vgm, trim($d['precinto']) ?: null]);
    return [true, 'Contenedor ' . $num . ' agregado.'];
}

function actualizar_contenedor($op, $id, $peso, $vgm, $precinto)
{
    $vgm = in_array($vgm, ['pendiente', 'declarado', 'transmitido'], true) ? $vgm : 'pendiente';
    ejecutar('UPDATE contenedores SET peso_bruto = ?, vgm = ?, precinto = ? WHERE id = ? AND operacion_id = ?',
        [$peso !== '' ? (int) $peso : null, $vgm, trim($precinto) ?: null, (int) $id, $op['id']]);
    return [true, 'Contenedor actualizado.'];
}
