<?php
// P9 · Controlar vencimientos (proceso automático): recordatorios de cut-offs y de «Notado» vencidos.
// Se ejecuta desde el panel (botón) o programado: php cron/vencimientos.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/util.php';
require_once __DIR__ . '/avisos.php';

function controlar_vencimientos()
{
    $res = ['recordatorios' => 0, 'notado' => 0, 'fallidos' => 0];
    $horas = (int) RECORDATORIO_HORAS;

    $ops = consulta(
        "SELECT o.*, c.empresa FROM operaciones o JOIN clientes c ON c.id = o.cliente_id
         WHERE o.estado NOT IN ('zarpado','cancelada')"
    );
    $campos = [['cutoff_doc', 'cut-off documental'], ['cutoff_fisico', 'cut-off físico'], ['vgm_limite', 'VGM']];

    foreach ($ops as $op) {
        foreach ($campos as $cf) {
            list($campo, $etiqueta) = $cf;
            if (!$op[$campo]) { continue; }
            $ts = strtotime($op[$campo]);
            if ($ts < time() || $ts > time() + $horas * 3600) { continue; }
            if ($campo === 'vgm_limite') {   // solo si hay VGM pendiente
                $pend = (int) valor("SELECT COUNT(*) FROM contenedores WHERE operacion_id = ? AND vgm = 'pendiente'", [$op['id']])
                      + (int) valor("SELECT COUNT(*) FROM documentos d WHERE d.operacion_id = ? AND d.tipo = 'VGM' AND d.estado = 'solicitado' " . "AND NOT EXISTS (SELECT 1 FROM documentos d2 WHERE d2.operacion_id = d.operacion_id AND d2.tipo = d.tipo AND d2.version > d.version AND d2.estado <> 'solicitado')", [$op['id']]);
                if (!$pend) { continue; }
            }
            $asunto = $op['referencia'] . ' · Vence el ' . $etiqueta . ' (' . date('d/m H:i', $ts) . ')';
            if (valor("SELECT id FROM comunicaciones WHERE operacion_id = ? AND tipo = 'recordatorio' AND asunto = ? LIMIT 1", [$op['id'], $asunto])) {
                continue; // ya se avisó
            }
            $cuerpo = 'Recordatorio: el ' . $etiqueta . ' de la operación ' . $op['referencia'] . ' (' . $op['empresa'] . ') vence el ' . date('d/m/Y H:i', $ts)
                . '. Si falta documentación o algún dato, enviala antes de ese momento.';
            $ids = array_column(destinatarios_operacion($op['id']), 'id');
            list($env, $fall) = avisar_participantes($op, $ids, 'Recordatorio: ' . $etiqueta, 'recordatorio', $asunto, $cuerpo, null, false);
            $r = aviso_interno($op, 'Recordatorio: ' . $etiqueta, 'recordatorio', $asunto, $cuerpo);
            if ($r && $r['ok']) { $env++; } elseif ($r) { $fall++; }
            $res['recordatorios'] += $env;
            $res['fallidos'] += $fall;
        }
    }

    // «Notado» vencidos: recordatorio al destinatario y aviso interno al responsable (RF-17)
    $vencidos = consulta(
        "SELECT c.*, o.referencia, o.responsable_id, o.id AS op_id FROM comunicaciones c JOIN operaciones o ON o.id = c.operacion_id
         WHERE c.requiere_notado = 1 AND c.notado_en IS NULL AND c.plazo_notado < NOW() AND c.recordatorio_enviado = 0 AND c.estado_envio = 'enviado'"
    );
    foreach ($vencidos as $c) {
        $r = registrar_aviso([
            'operacion_id' => $c['operacion_id'], 'cambio_id' => $c['cambio_id'], 'evento' => 'Recordatorio: confirmá «Notado»', 'tipo' => 'recordatorio',
            'participante_id' => $c['participante_id'],
            'nombre' => $c['destinatario_nombre'], 'correo' => $c['destinatario_correo'], 'dest_tipo' => $c['destinatario_tipo'],
            'asunto' => $c['asunto'] . ' (recordatorio)', 'cuerpo' => "Todavía no confirmaste que tomaste conocimiento de este aviso:\n\n" . $c['cuerpo'], 'notado' => false,
        ]);
        $r['ok'] ? $res['notado']++ : $res['fallidos']++;
        aviso_interno(['id' => $c['operacion_id'], 'responsable_id' => $c['responsable_id']], '«Notado» vencido', 'recordatorio',
            $c['referencia'] . ' · «Notado» vencido', $c['destinatario_nombre'] . ' no confirmó «' . $c['evento'] . '» dentro del plazo.');
        ejecutar('UPDATE comunicaciones SET recordatorio_enviado = 1 WHERE id = ?', [$c['id']]);
    }
    return $res;
}
