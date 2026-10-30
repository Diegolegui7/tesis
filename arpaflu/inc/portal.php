<?php
// Portal de clientes · lenguaje simple: glosario, estados en palabras de todos los días, barra de progreso y “qué tenés que hacer ahora”
require_once __DIR__ . '/util.php';

function glosario()
{
    return [
        'Cut-off documental' => 'Fecha y hora límite para entregarle los documentos a ARPAFLU. Después de ese momento la naviera ya no los acepta.',
        'Cut-off físico' => 'Fecha y hora límite para llevar la carga (el contenedor) al puerto.',
        'ETD' => 'Fecha estimada en que el barco sale del puerto.',
        'ETA' => 'Fecha estimada en que el barco llega al destino.',
        'VGM' => 'Peso verificado del contenedor ya cargado. Es un dato obligatorio para poder embarcar.',
        'Booking' => 'Número que la naviera le da a tu reserva. Confirma que el lugar en el barco está asegurado.',
        'DRAFT' => 'Borrador del documento de embarque (Bill of Lading) para que lo revises antes de que sea el definitivo.',
        'Declaración' => 'Papel con los datos de tu carga que ARPAFLU necesita para preparar el embarque.',
        'Días libres' => 'Días en los que podés tener el contenedor en destino sin pagar recargo.',
        'Notado' => 'Botón con el que avisás que leíste una novedad.',
        'Flete' => 'Precio del transporte por cada contenedor.',
        'Terminal' => 'Lugar del puerto donde se entrega o se retira el contenedor.',
        'Transbordo' => 'El contenedor cambia de barco en un puerto intermedio. Puede demorar un poco más.',
        "40' HC" => 'Contenedor de 40 pies (unos 12 metros), más alto que el común.',
        "20' DV" => 'Contenedor común de 20 pies (unos 6 metros).',
        "40' RF" => 'Contenedor refrigerado de 40 pies, para carga que necesita frío.',
    ];
}

// Palabra técnica con una explicación que aparece al tocarla o pasar el mouse
function termino($clave, $texto = null)
{
    $g = glosario();
    $t = $texto !== null ? $texto : $clave;
    if (!isset($g[$clave])) { return e($t); }
    return '<span class="termino" tabindex="0" data-ayuda="' . e($g[$clave]) . '">' . e($t) . '<span class="termino-i" aria-hidden="true">?</span></span>';
}

function pasos_cliente()
{
    return ['Pedido', 'Lugar confirmado', 'Preparación', 'Documentos', 'Embarcado'];
}

// Estado de una operación explicado para el cliente: [nombre, explicación, paso 0-5, clase]
function estado_cliente($estado)
{
    $m = [
        'reserva_solicitada' => ['Pidiendo tu lugar', 'ARPAFLU está pidiendo el lugar a la naviera.', 1, 'estado-alerta'],
        'booking_confirmado' => ['Lugar confirmado', 'La naviera confirmó tu lugar en el barco.', 2, 'estado-ok'],
        'orden_retiro' => ['Preparando la carga', 'Ya está autorizado el retiro del contenedor vacío para cargarlo.', 3, 'estado-info'],
        'documentacion' => ['Documentos', 'Se están preparando los documentos del embarque.', 4, 'estado-alerta'],
        'zarpado' => ['Embarcado', 'Tu carga ya viajó.', 5, 'estado-ok'],
        'cancelada' => ['Cancelada', 'Esta reserva se canceló.', 0, 'estado-peligro'],
    ];
    return isset($m[$estado]) ? $m[$estado] : [$estado, '', 0, 'estado-neutro'];
}

function badge_cliente($estado)
{
    $x = estado_cliente($estado);
    return badge($x[0], $x[3]);
}

function barra_progreso($estado)
{
    $paso = estado_cliente($estado)[2];
    if ($estado === 'cancelada') { return ''; }
    $html = '<ol class="progreso" aria-label="Pasos de tu reserva">';
    foreach (pasos_cliente() as $i => $n) {
        $k = $i + 1;
        $clase = $k < $paso || $paso === 5 ? 'hecho' : ($k === $paso ? 'actual' : '');
        $html .= '<li class="' . $clase . '"><span class="progreso-punto">' . ($clase === 'hecho' ? '✓' : $k) . '</span><span class="progreso-texto">' . e($n) . '</span></li>';
    }
    return $html . '</ol>';
}

// Qué tiene que hacer el cliente ahora con una reserva. Devuelve [titulo, texto, enlace|null, clase]
function que_hacer_ahora($op, $notado_pendientes, $docs_faltan)
{
    $abierta = !in_array($op['estado'], ['zarpado', 'cancelada'], true);
    if ($op['estado'] === 'cancelada') { return ['Esta reserva está cancelada', 'No tenés nada para hacer.', null, 'aviso-info']; }
    if ($notado_pendientes > 0) {
        return ['Confirmá que leíste el aviso de ARPAFLU', 'Hay una novedad en tu reserva. Leela y tocá el botón “Notado” para avisarnos que la viste.', ['Ir al aviso', '#avisos'], 'aviso-alerta'];
    }
    if ($abierta && $docs_faltan) {
        $limite = $op['cutoff_doc'] ? ' antes del ' . f_hora($op['cutoff_doc']) : '';
        return ['Falta que nos envíes: ' . implode(', ', $docs_faltan), 'Subí el archivo' . $limite . ' para que ARPAFLU pueda presentarlo a la naviera.', ['Enviar documento', '#enviar'], 'aviso-alerta'];
    }
    if ($abierta && $op['cutoff_doc'] && strtotime($op['cutoff_doc']) > time() && strtotime($op['cutoff_doc']) < time() + 3 * 86400) {
        return ['Se acerca la fecha límite de los documentos', 'El ' . f_hora($op['cutoff_doc']) . ' es el último momento para entregar los documentos. Si todavía te falta algo, enviálo desde acá.', ['Enviar documento', '#enviar'], 'aviso-alerta'];
    }
    if ($op['estado'] === 'zarpado') { return ['No tenés nada para hacer', 'Tu carga ya viajó. Si hay novedades, ARPAFLU te avisa por mail.', null, 'aviso-ok']; }
    return ['No tenés nada para hacer por ahora', 'ARPAFLU se ocupa de todo y te avisa por mail si hay novedades.', null, 'aviso-ok'];
}

function responsable_de($cliente_id)
{
    return fila('SELECT r.nombre, r.correo FROM clientes c JOIN usuarios r ON r.id = c.responsable_id WHERE c.id = ?', [$cliente_id]);
}

// Cosas que el cliente tiene pendientes en una reserva: cantidad de avisos por confirmar y documentos que ARPAFLU le pidió
function pendientes_cliente($op_id)
{
    $n = (int) valor("SELECT COUNT(*) FROM comunicaciones WHERE operacion_id = ? AND destinatario_tipo = 'cliente' AND requiere_notado = 1 AND notado_en IS NULL", [$op_id]);
    $docs = array_column(consulta(
        "SELECT d.tipo FROM documentos d WHERE d.operacion_id = ? AND d.estado = 'solicitado' AND d.visible_cliente = 1
         AND NOT EXISTS (SELECT 1 FROM documentos d2 WHERE d2.operacion_id = d.operacion_id AND d2.tipo = d.tipo AND d2.version > d.version AND d2.estado <> 'solicitado')", [$op_id]), 'tipo');
    return [$n, $docs];
}

// Vista del personal: “simple” (por defecto: menú corto y tareas del día) o “completa” (todas las secciones)
function vista_simple()
{
    return !isset($_COOKIE['vista']) || $_COOKIE['vista'] !== 'completa';
}

// Glosario para el personal: las mismas palabras técnicas, explicadas para quien recién empieza
function glosario_personal()
{
    return [
        'Booking' => 'Número con el que la naviera confirma que el lugar en el barco está asegurado.',
        'Orden de retiro' => 'Autorización de la naviera para retirar el contenedor vacío y cargarlo.',
        'Cut-off documental' => 'Fecha y hora límite para entregarle los documentos a la naviera.',
        'Cut-off físico' => 'Fecha y hora límite para llevar el contenedor cargado al puerto.',
        'ETD' => 'Fecha estimada en que el barco sale del puerto.',
        'ETA' => 'Fecha estimada en que el barco llega al destino.',
        'VGM' => 'Peso verificado del contenedor cargado. Sin él no se puede embarcar.',
        'DRAFT' => 'Borrador del documento de embarque, para revisarlo antes del definitivo.',
        'DECLA' => 'Declaración con los datos de la carga, necesaria para preparar el embarque.',
        'Roleo' => 'La naviera pasa la carga a otro barco o a otra fecha.',
        'POL' => 'Puerto de carga: de donde sale la mercadería.',
        'POD' => 'Puerto de destino: adonde llega la mercadería.',
        'Notado' => 'Confirmación del cliente de que leyó un aviso importante.',
        'Días libres' => 'Días que el cliente puede tener el contenedor en destino sin pagar recargo.',
        'Terminal' => 'Lugar del puerto donde se entrega o se retira el contenedor.',
        'Despachante' => 'Persona o empresa que hace los trámites de aduana por el cliente.',
        'Flete' => 'Precio del transporte por cada contenedor.',
        'Transbordo' => 'El contenedor cambia de barco en un puerto intermedio.',
    ];
}

function termino_p($clave, $texto = null)
{
    $g = glosario_personal();
    $t = $texto !== null ? $texto : $clave;
    if (!isset($g[$clave])) { return e($t); }
    return '<span class="termino" tabindex="0" data-ayuda="' . e($g[$clave]) . '">' . e($t) . '<span class="termino-i" aria-hidden="true">?</span></span>';
}

// Qué le falta a una reserva, en pocas palabras (listados del personal)
function que_falta($o)
{
    if ($o['estado'] === 'cancelada') { return 'Cancelada'; }
    if ($o['estado'] === 'zarpado') { return 'Nada: el barco ya salió'; }
    if (!$o['booking']) { return 'El número de booking'; }
    if (!$o['orden_retiro']) { return 'La orden de retiro'; }
    return 'Que salga el barco';
}
