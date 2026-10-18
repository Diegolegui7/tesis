<?php
// P9 · Ejecuta a mano el control de vencimientos (el mismo proceso que corre programado)
require_once __DIR__ . '/../partes/layout.php';
require_once __DIR__ . '/../inc/vencimientos.php';
$u = requerir_empleado('registrar_cambios');

if (es_post()) {
    csrf_verificar();
    $r = controlar_vencimientos();
    flash('ok', 'Control de vencimientos ejecutado: ' . $r['recordatorios'] . ' recordatorio(s) de vencimiento, ' . $r['notado'] . ' recordatorio(s) de «Notado»'
        . ($r['fallidos'] ? ', ' . $r['fallidos'] . ' no enviado(s)' : '') . '.');
}
redirigir('panel.php');
