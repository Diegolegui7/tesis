<?php
// SS2 / SS3 / SS4 / SS5 · Ficha de la operación (datos, contenedores, documentos, cambios y comunicaciones)
require_once __DIR__ . '/../partes/layout.php';
require_once __DIR__ . '/../inc/operaciones.php';
$u = requerir_empleado('ver_operaciones');

$id = (int) (isset($_GET['id']) ? $_GET['id'] : 0);
$op = cargar_operacion($id);
if (!$op) {
    http_response_code(404);
    exit('<p style="font-family:sans-serif;padding:24px">No existe esa operación. <a href="operaciones.php">Volver al listado</a></p>');
}
$actuar = puede_actuar_cliente($op['cliente_id']);
$abierta = !in_array($op['estado'], ['zarpado', 'cancelada'], true);

// ---------- Acciones (POST) ----------
if (es_post()) {
    csrf_verificar();
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';
    $tab = 'datos';
    $resultado = null;
    $denegado = false;

    $necesita = function ($funcion) use (&$denegado, $actuar) {
        if (!puede($funcion) || !$actuar) {
            flash('error', 'No tenés autorización para esta acción sobre este cliente.');
            $denegado = true;
            return false;
        }
        return true;
    };

    switch ($accion) {
        case 'cambio':
            $tab = 'cambios';
            if ($necesita('registrar_cambios')) {
                $avisar = isset($_POST['avisar']) && is_array($_POST['avisar']) ? $_POST['avisar'] : [];
                $resultado = registrar_cambio($op, $u, isset($_POST['dato']) ? $_POST['dato'] : '', isset($_POST['valor_nuevo']) ? $_POST['valor_nuevo'] : '',
                    isset($_POST['origen']) ? $_POST['origen'] : '', trim(isset($_POST['detalle']) ? $_POST['detalle'] : ''), $avisar,
                    isset($_POST['etd_nuevo']) ? $_POST['etd_nuevo'] : '', isset($_POST['cutoff_nuevo']) ? $_POST['cutoff_nuevo'] : '');
            }
            break;
        case 'documento':
            $tab = 'documentos';
            if ($necesita('cargar_documentos')) {
                $archivo = subir_archivo('archivo', 'operaciones/' . $op['id']);
                $resultado = guardar_documento($op, $u, isset($_POST['tipo']) ? $_POST['tipo'] : '', isset($_POST['estado']) ? $_POST['estado'] : 'recibido',
                    trim(isset($_POST['origen']) ? $_POST['origen'] : ''), trim(isset($_POST['observaciones']) ? $_POST['observaciones'] : ''), !empty($_POST['visible_cliente']), $archivo);
            }
            break;
        case 'booking':
            if ($necesita('crear_operaciones')) {
                $resultado = confirmar_booking($op, isset($_POST['booking']) ? $_POST['booking'] : '', isset($_POST['buque']) ? $_POST['buque'] : '');
            }
            break;
        case 'orden':
            if ($necesita('crear_operaciones')) {
                $resultado = registrar_orden_retiro($op, isset($_POST['orden_retiro']) ? $_POST['orden_retiro'] : '', isset($_POST['fecha']) ? $_POST['fecha'] : '');
            }
            break;
        case 'zarpe':
            if ($necesita('crear_operaciones')) {
                $resultado = registrar_zarpe($op, $u);
            }
            break;
        case 'cancelar':
            if ($necesita('crear_operaciones')) {
                $archivo = subir_archivo('respuesta_naviera', 'operaciones/' . $op['id']);
                $resultado = cancelar_operacion($op, $u, isset($_POST['origen']) ? $_POST['origen'] : '', isset($_POST['motivo']) ? $_POST['motivo'] : '', $archivo);
            }
            break;
        case 'contenedor':
            $tab = 'contenedores';
            if ($necesita('crear_operaciones')) {
                $resultado = guardar_contenedor($op, [
                    'numero' => isset($_POST['numero']) ? $_POST['numero'] : '', 'retiro_fecha' => isset($_POST['retiro_fecha']) ? $_POST['retiro_fecha'] : '',
                    'retiro_lugar' => isset($_POST['retiro_lugar']) ? $_POST['retiro_lugar'] : '', 'peso_bruto' => isset($_POST['peso_bruto']) ? $_POST['peso_bruto'] : '',
                    'vgm' => isset($_POST['vgm']) ? $_POST['vgm'] : 'pendiente', 'precinto' => isset($_POST['precinto']) ? $_POST['precinto'] : '',
                ]);
            }
            break;
        case 'contenedor_act':
            $tab = 'contenedores';
            if ($necesita('crear_operaciones')) {
                $resultado = actualizar_contenedor($op, isset($_POST['contenedor_id']) ? $_POST['contenedor_id'] : 0, isset($_POST['peso_bruto']) ? $_POST['peso_bruto'] : '',
                    isset($_POST['vgm']) ? $_POST['vgm'] : 'pendiente', isset($_POST['precinto']) ? $_POST['precinto'] : '');
            }
            break;
        case 'reenviar':
            $tab = 'comunicaciones';
            if ($necesita('registrar_cambios')) {
                $r = reenviar_aviso(isset($_POST['comunicacion_id']) ? $_POST['comunicacion_id'] : 0);
                $resultado = $r ? [$r['ok'], $r['ok'] ? 'Aviso reenviado.' : 'No se pudo reenviar: ' . $r['error']] : [false, 'No se encontró el aviso.'];
            }
            break;
        case 'notado_manual':
            $tab = 'comunicaciones';
            if ($necesita('registrar_cambios')) {
                $ok = confirmar_notado(isset($_POST['comunicacion_id']) ? $_POST['comunicacion_id'] : 0, $u['id']);
                $resultado = [(bool) $ok, $ok ? 'Se registró el “Notado” (confirmado por ' . $u['nombre'] . ').' : 'Ese aviso ya estaba confirmado.'];
            }
            break;
        default:
            flash('error', 'Acción no reconocida.');
    }
    if ($resultado !== null) {
        flash($resultado[0] ? 'ok' : 'error', $resultado[1]);
    }
    redirigir('operacion.php?id=' . $op['id'] . '#' . $tab);
}

// ---------- Datos para mostrar ----------
$contenedores = consulta('SELECT * FROM contenedores WHERE operacion_id = ? ORDER BY id', [$id]);
$documentos = consulta('SELECT d.*, u.nombre AS subio FROM documentos d LEFT JOIN usuarios u ON u.id = d.subido_por WHERE d.operacion_id = ? ORDER BY d.tipo, d.version', [$id]);
$historial = consulta('SELECT h.*, u.nombre AS usuario FROM historial_cambios h LEFT JOIN usuarios u ON u.id = h.usuario_id WHERE h.operacion_id = ? ORDER BY h.creado DESC, h.id DESC', [$id]);
$comunicaciones = consulta('SELECT * FROM comunicaciones WHERE operacion_id = ? ORDER BY creado DESC, id DESC', [$id]);
$destinatarios = destinatarios_operacion($id);
$vencidos = consulta('SELECT * FROM comunicaciones WHERE operacion_id = ? AND requiere_notado = 1 AND notado_en IS NULL AND plazo_notado < NOW() ORDER BY plazo_notado', [$id]);
$participantes_cliente = consulta('SELECT * FROM participantes WHERE cliente_id = ? AND activo = 1 ORDER BY FIELD(rol,"contacto","despachante","otro"), nombre', [$op['cliente_id']]);
$dest_ids = array_column($destinatarios, 'id');

$estado_part = function ($p) use ($id) {
    $c = fila('SELECT * FROM comunicaciones WHERE operacion_id = ? AND destinatario_correo = ? AND requiere_notado = 1 ORDER BY creado DESC, id DESC LIMIT 1', [$id, $p['correo']]);
    if (!$c) { return badge('Sin avisos', 'estado-neutro'); }
    if ($c['notado_en'] !== null) { return badge('Notado', 'estado-info'); }
    return $c['plazo_notado'] !== null && strtotime($c['plazo_notado']) < time() ? badge('Vencido', 'estado-peligro') : badge('Esperando', 'estado-alerta');
};

$valores_actuales = [];
foreach (array_keys(datos_cambiables()) as $d) { $valores_actuales[$d] = valor_actual_texto($op, $d); }

$fecha_card = function ($etiqueta, $dt, $conHora = true, $cumplida = false) use ($abierta) {
    if (!$dt) { return ''; }
    $f = faltan($dt);
    $clase = $cumplida ? 'cumplida' : (!$abierta ? '' : ($f['dias'] === -1 ? 'vencida' : ($f['dias'] !== null && $f['dias'] <= 3 ? 'proxima' : '')));
    $sub = $cumplida ? '<span class="chico suave">Cumplido</span>' : ($abierta && $conHora ? '<span class="chico">' . ($f['dias'] === -1 ? 'Vencido' : 'Faltan ' . e($f['texto'])) . '</span>' : '');
    return '<div class="fecha ' . $clase . '"><span class="etiqueta">' . e($etiqueta) . '</span><strong>' . e($conHora ? f_hora($dt) : f_fecha($dt)) . '</strong>' . $sub . '</div>';
};

$etiquetas_cambio = ['Buque' => 'El barco (cambió de barco)', 'ETD' => 'La fecha de salida (ETD)', 'ETA' => 'La fecha de llegada (ETA)', 'Cut-off documental' => 'El límite para los documentos', 'Cut-off físico' => 'El límite para llevar la carga', 'Fecha límite de VGM' => 'El límite para el peso (VGM)', 'Terminal' => 'La terminal', 'Roleo (buque y fechas)' => 'El barco se corre (roleo): nuevo barco y fechas', 'Novedad posterior al zarpe' => 'Una novedad después de la salida', 'Otro' => 'Otra cosa'];
$orden_label = ['solicitado' => 'estado-alerta', 'recibido' => 'estado-ok', 'revisado' => 'estado-info', 'presentado' => 'estado-ok'];

// “Qué sigue” y línea de pasos, en palabras comunes
$cancelada = $op['estado'] === 'cancelada';
$zarpada = $op['estado'] === 'zarpado';
$paso = $cancelada ? 'cancelada' : (!$op['booking'] ? 'booking' : (!$op['orden_retiro'] ? 'orden' : ($zarpada ? 'fin' : 'zarpe')));
$sigue = [
    'booking' => ['Cargar el número de booking', 'La naviera confirma el lugar en el barco con un número: el booking. Cuando lo tengas, anotalo. Se le avisa al cliente.'],
    'orden' => ['Registrar la orden de retiro', 'La naviera manda una orden para retirar el contenedor vacío. Anotala cuando llegue.'],
    'zarpe' => ['Avisar cuando el barco salga', 'Cuando la naviera confirme que el barco zarpó, tocá el botón. Se le avisa al cliente y la reserva pasa a “Zarpada”.'],
    'fin' => ['No hay nada para hacer por ahora', 'El barco ya salió. Si llega una novedad, por ejemplo que llegó o que se demora, anotala.'],
    'cancelada' => ['Esta reserva se canceló', 'No se puede cambiar nada. Queda guardada para consultarla.'],
][$paso];
$puede_gestionar = $actuar && puede('crear_operaciones');
$solicitud_origen = valor('SELECT id FROM solicitudes WHERE operacion_id = ? LIMIT 1', [$id]);
$pasos_reserva = [
    [$solicitud_origen ? 'Pedido aceptado' : 'Reserva creada', f_corta($op['creado']), 'ok'],
    ['Lugar confirmado por la naviera', $op['booking'] ? 'Booking ' . $op['booking'] . ($op['booking_confirmado_en'] ? ' · ' . f_corta($op['booking_confirmado_en']) : '') : 'Falta el número de booking', $op['booking'] ? 'ok' : ($cancelada ? '' : 'ahora')],
    ['Orden de retiro', $op['orden_retiro'] ? 'N.º ' . $op['orden_retiro'] . ' · ' . f_corta($op['orden_retiro_fecha']) : 'Se anota cuando llega de la naviera', $op['orden_retiro'] ? 'ok' : ($op['booking'] && !$cancelada ? 'ahora' : '')],
    ['Documentos del cliente', ($op['cutoff_doc'] ? 'Hasta el ' . f_hora($op['cutoff_doc']) . ' · ' : '') . count($documentos) . ' cargado' . (count($documentos) == 1 ? '' : 's'), $zarpada ? 'ok' : ($op['orden_retiro'] && !$cancelada ? 'ahora' : '')],
    ['El barco salió', $zarpada ? f_hora($op['zarpe_confirmado']) : 'Se anota cuando la naviera lo confirma', $zarpada ? 'ok' : ''],
];

cabecera_personal($op['referencia'], 'operaciones');
?>
      <div class="encabezado-pagina">
        <div>
          <div class="migas"><a href="operaciones.php">Reservas</a> / <?= e($op['referencia']) ?></div>
          <h1><?= e($op['empresa']) ?> a <?= e($op['pod']) ?> <span style="vertical-align:middle"><?= badge_estado_op($op['estado']) ?></span></h1>
          <p>
            <?= e($op['referencia']) ?> · <?= (int) $op['cantidad'] ?> × <?= e($op['tipo_contenedor']) ?> ·
            <?= $op['buque'] ? e($op['buque']) : 'Barco a confirmar' ?> · Responsable: <?= e($op['responsable']) ?>
          </p>
        </div>
      </div>

      <?php if (!$actuar) { ?>
        <div class="aviso aviso-info"><div><p>Tu cuenta puede consultar esta operación, pero no modificarla: el cliente <?= e($op['empresa']) ?> no está entre los que tenés autorizados.</p></div></div>
      <?php } ?>

      <?php if ($op['estado'] === 'cancelada') { ?>
        <div class="aviso aviso-peligro"><div>
          <strong>Reserva cancelada</strong>
          <p>Solicitada por <?= e($op['cancelada_por']) ?> el <?= e(f_completa($op['cancelada_en'])) ?>. Motivo: <?= e($op['cancelacion_motivo']) ?></p>
        </div></div>
      <?php } ?>

      <?php if ($abierta && $actuar && puede('crear_operaciones')) { ?>
      <form id="form-cancelar" class="tarjeta" action="operacion.php?id=<?= $id ?>" method="post" enctype="multipart/form-data" hidden style="margin-bottom:18px">
        <?= csrf_campo() ?><input type="hidden" name="accion" value="cancelar">
        <div class="tarjeta-cabecera"><h2>Cancelar reserva</h2></div>
        <div class="tarjeta-cuerpo formulario">
          <div class="campos">
            <div class="campo">
              <label for="cancel-origen">Solicitada por</label>
              <select id="cancel-origen" name="origen">
                <option value="cliente">Cliente</option><option value="naviera">Naviera</option><option value="arpaflu">ARPAFLU</option>
              </select>
            </div>
            <div class="campo">
              <label for="cancel-archivo">Respuesta de la naviera</label>
              <input type="file" id="cancel-archivo" name="respuesta_naviera">
            </div>
            <div class="campo campo-ancho">
              <label for="cancel-motivo">Motivo</label>
              <textarea id="cancel-motivo" name="motivo" required></textarea>
            </div>
          </div>
          <p class="ayuda">La operación queda cancelada con todos sus antecedentes; no se borra.</p>
          <div class="pie-formulario">
            <button type="button" class="boton boton-secundario" data-alternar="form-cancelar">Volver</button>
            <button type="submit" class="boton boton-peligro">Confirmar cancelación</button>
          </div>
        </div>
      </form>
      <?php } ?>

      <?php foreach ($vencidos as $vc) { ?>
      <div class="aviso aviso-alerta">
        <div>
          <strong>Falta un “Notado”.</strong>
          <p><?= e($vc['destinatario_nombre']) ?> no confirmó “<?= e($vc['evento']) ?>” del <?= e(f_hora($vc['creado'])) ?>. Plazo vencido el <?= e(f_hora($vc['plazo_notado'])) ?>.</p>
        </div>
        <?php if ($actuar && puede('registrar_cambios')) { ?>
        <form class="acciones" action="operacion.php?id=<?= $id ?>" method="post">
          <?= csrf_campo() ?><input type="hidden" name="accion" value="reenviar"><input type="hidden" name="comunicacion_id" value="<?= (int) $vc['id'] ?>">
          <button type="submit" class="boton boton-secundario boton-chico">Reenviar aviso</button>
        </form>
        <?php } ?>
      </div>
      <?php } ?>

      <?php if ($zarpada) { ?>
      <div class="aviso aviso-ok"><div>
        <strong>Esta reserva ya viajó el <?= e(f_fecha($op['zarpe_confirmado'])) ?>.</strong>
        <p>Ya no se puede cambiar el barco ni las fechas. Todavía se pueden anotar novedades (que llegó, que se demora) y cargar documentos que falten.</p>
      </div></div>
      <?php } ?>

      <div class="tarjeta que-sigue">
        <div class="tarjeta-cuerpo">
          <p class="etiqueta-sigue">Qué sigue</p>
          <h2 class="titulo-sigue"><?= e($sigue[0]) ?></h2>
          <p class="suave" style="margin:4px 0 0"><?= e($sigue[1]) ?></p>
          <?php if ($puede_gestionar && $paso === 'booking') { ?>
            <form action="operacion.php?id=<?= $id ?>" method="post" class="formulario" style="margin-top:12px">
              <?= csrf_campo() ?><input type="hidden" name="accion" value="booking">
              <div class="campos">
                <div class="campo"><label for="bk-num">Número de booking</label><input type="text" id="bk-num" name="booking" maxlength="40" required placeholder="Por ejemplo: 254AR0099001"></div>
                <div class="campo"><label for="bk-buque">Barco y viaje (solo si cambió)</label><input type="text" id="bk-buque" name="buque" maxlength="120" value="<?= e($op['buque']) ?>"></div>
              </div>
              <div class="pie-formulario"><button type="submit" class="boton boton-primario boton-grande">Guardar el booking</button></div>
            </form>
          <?php } elseif ($puede_gestionar && $paso === 'orden') { ?>
            <form action="operacion.php?id=<?= $id ?>" method="post" class="formulario" style="margin-top:12px">
              <?= csrf_campo() ?><input type="hidden" name="accion" value="orden">
              <div class="campos">
                <div class="campo"><label for="or-num">Número de la orden</label><input type="text" id="or-num" name="orden_retiro" maxlength="40" required placeholder="Por ejemplo: OR-5521"></div>
                <div class="campo"><label for="or-fecha">Fecha en que llegó</label><input type="text" id="or-fecha" name="fecha" placeholder="<?= date('d/m/Y') ?>"></div>
              </div>
              <div class="pie-formulario"><button type="submit" class="boton boton-primario boton-grande">Guardar la orden de retiro</button></div>
            </form>
          <?php } elseif ($puede_gestionar && $paso === 'zarpe') { ?>
            <form action="operacion.php?id=<?= $id ?>" method="post" class="formulario" style="margin-top:12px" onsubmit="return confirm('¿El barco ya salió? Se le avisa al cliente y la reserva pasa a “Zarpada”.');">
              <?= csrf_campo() ?><input type="hidden" name="accion" value="zarpe">
              <div class="pie-formulario" style="justify-content:flex-start"><button type="submit" class="boton boton-primario boton-grande">El barco ya salió</button></div>
            </form>
          <?php } elseif ($paso === 'fin' && $actuar && puede('registrar_cambios')) { ?>
            <p style="margin:12px 0 0"><a href="#cambios" class="boton boton-primario boton-grande abrir">Anotar una novedad</a></p>
          <?php } elseif ($paso !== 'fin' && $paso !== 'cancelada') { ?>
            <p class="chico suave" style="margin:12px 0 0">Esto lo hace <?= $op['responsable'] ? e($op['responsable']) . ', responsable de esta reserva' : 'el responsable de la reserva' ?>.</p>
          <?php } ?>
        </div>
      </div>

      <div class="tarjeta">
        <div class="tarjeta-cuerpo">
          <p class="etiqueta-sigue" style="color:var(--texto-suave)">Cómo va la reserva</p>
          <ol class="pasos-reserva">
            <?php foreach ($pasos_reserva as $i => $pr) { ?>
            <li class="<?= e($pr[2]) ?>">
              <span class="punto"><?= $pr[2] === 'ok' ? '✓' : $i + 1 ?></span>
              <span><strong><?= e($pr[0]) ?></strong><span class="chico suave" style="display:block"><?= e($pr[1]) ?></span></span>
            </li>
            <?php } ?>
          </ol>
        </div>
      </div>

      <?php $otras_cambio = $actuar && puede('registrar_cambios') && ($abierta || $zarpada); $otras_doc = $actuar && puede('cargar_documentos') && ($abierta || $zarpada); $otras_cancelar = $abierta && $puede_gestionar; ?>
      <?php if ($otras_cambio || $otras_doc || $otras_cancelar) { ?>
      <div class="tarjeta">
        <div class="tarjeta-cuerpo">
          <p class="etiqueta-sigue" style="color:var(--texto-suave)">Otras cosas que se pueden hacer</p>
          <div class="acciones" style="justify-content:flex-start">
            <?php if ($otras_cambio) { ?><a href="#cambios" class="boton boton-secundario abrir"><?= $zarpada ? 'Anotar una novedad' : 'Avisar un cambio de barco, fecha o plazo' ?></a><?php } ?>
            <?php if ($otras_doc) { ?><a href="#documentos" class="boton boton-secundario abrir">Subir un documento</a><?php } ?>
            <?php if ($otras_cancelar) { ?><button type="button" class="boton boton-peligro" data-alternar="form-cancelar">Cancelar la reserva</button><?php } ?>
          </div>
        </div>
      </div>
      <?php } ?>

      <p class="suave" style="margin:20px 0 8px;font-weight:600">Más detalles</p>
      <!-- ===== Datos ===== -->
      <details id="fechas" class="acordeon"><summary><span class="ac-t">Fechas y plazos</span><span class="ac-h">cuándo hay que entregar cada cosa</span></summary><div class="acordeon-cuerpo">
        <div class="tarjeta">
          <div class="tarjeta-cuerpo">
            <div class="fechas">
              <?= $fecha_card('Booking confirmado', $op['booking_confirmado_en'], false, true) ?>
              <?= $fecha_card('Límite para los documentos', $op['cutoff_doc']) ?>
              <?= $fecha_card('Límite para llevar la carga', $op['cutoff_fisico']) ?>
              <?= $fecha_card('Límite para el peso (VGM)', $op['vgm_limite']) ?>
              <?= $fecha_card('Salida estimada (ETD)', $op['etd'], false) ?>
              <?= $fecha_card('Llegada estimada a ' . $op['pod'] . ' (ETA)', $op['eta'], false) ?>
              <?= $fecha_card('Zarpe confirmado', $op['zarpe_confirmado'], true, true) ?>
            </div>
          </div>
        </div>
      </div></details>

      <details id="datos" class="acordeon"><summary><span class="ac-t">Datos de la reserva y a quién se avisa</span><span class="ac-h">barco, puertos, contenedores y contactos</span></summary><div class="acordeon-cuerpo">
        <div class="grilla grilla-2-1">
          <div class="tarjeta">
            <div class="tarjeta-cabecera"><h2>Datos de la reserva</h2></div>
            <div class="tarjeta-cuerpo">
              <dl class="datos">
                <div><dt>Referencia cliente</dt><dd><?= e($op['referencia_cliente'] ?: '—') ?></dd></div>
                <div><dt>Semana</dt><dd><?= $op['semana'] ? (int) $op['semana'] : '—' ?></dd></div>
                <div><dt>Naviera</dt><dd><?= e($op['naviera'] ?: '—') ?></dd></div>
                <div><dt>Barco</dt><dd><?= e($op['buque'] ?: 'A confirmar') ?></dd></div>
                <div><dt>Bandera del barco</dt><dd><?= e($op['bandera'] ?: '—') ?></dd></div>
                <div><dt><?= termino_p('Booking') ?></dt><dd><?= e($op['booking'] ?: '—') ?></dd></div>
                <div><dt><?= termino_p('Terminal') ?></dt><dd><?= e($op['terminal'] ?: 'A confirmar') ?></dd></div>
                <div><dt><?= termino_p('POL', 'Puerto de carga') ?></dt><dd><?= e($op['pol']) ?></dd></div>
                <div><dt><?= termino_p('POD', 'Puerto de destino') ?></dt><dd><?= e($op['pod']) ?></dd></div>
                <div><dt>Ruta</dt><dd><?= $op['via'] === 'directa' ? 'Directa' : 'Con transbordo' ?></dd></div>
                <div><dt>Contenedores</dt><dd><?= (int) $op['cantidad'] ?> × <?= e($op['tipo_contenedor']) ?></dd></div>
                <div><dt><?= termino_p('Días libres') ?> en destino</dt><dd><?= $op['dias_libres'] !== null ? (int) $op['dias_libres'] : '—' ?></dd></div>
                <div><dt>Orden de retiro</dt><dd><?= $op['orden_retiro'] ? e($op['orden_retiro']) . ' · ' . e(f_corta($op['orden_retiro_fecha'])) : '—' ?></dd></div>
                <div><dt>Cotización aceptada</dt><dd><?= $op['cot_codigo'] ? '<a href="cotizaciones.php">' . e($op['cot_codigo'] . ' v' . $op['cot_version']) . '</a>' : '—' ?></dd></div>
                <?php if ($op['cot_flete'] !== null) { ?><div><dt>Flete ofrecido</dt><dd><?= e(dinero($op['cot_flete'])) ?> / cont.</dd></div><?php } ?>
              </dl>
              <?php if ($op['observaciones']) { ?><p class="chico suave" style="margin:14px 0 0"><strong>Observaciones:</strong> <?= e($op['observaciones']) ?></p><?php } ?>
            </div>
          </div>

          <div class="tarjeta">
            <div class="tarjeta-cabecera"><h2>Destinatarios de avisos</h2></div>
            <ul class="lista">
              <?php foreach ($destinatarios as $p) { ?>
              <li>
                <div><div class="titulo"><?= e($p['nombre']) ?></div><div class="chico suave"><?= e($op['empresa']) ?> · <?= $p['rol'] === 'despachante' ? 'Despachante' : ($p['rol'] === 'contacto' ? 'Contacto' : 'Otro contacto') ?></div></div>
                <?= $estado_part($p) ?>
              </li>
              <?php } ?>
              <li>
                <div><div class="titulo"><?= e($op['responsable'] ?: '—') ?></div><div class="chico suave">ARPAFLU · Responsable</div></div>
                <span class="estado estado-neutro">Interno</span>
              </li>
            </ul>
          </div>
        </div>
      </div></details>

      <details id="contenedores" class="acordeon"><summary><span class="ac-t">Contenedores <span class="contador"><?= count($contenedores) ?></span></span><span class="ac-h">los que ya se cargaron</span></summary><div class="acordeon-cuerpo">
        <div class="tarjeta">
          <div class="tarjeta-cabecera">
            <h2>Contenedores y orden de retiro</h2>
            <span class="chico suave"><?= $op['orden_retiro'] ? 'Orden de retiro ' . e($op['orden_retiro']) . ' · recibida ' . e(f_corta($op['orden_retiro_fecha'])) : 'Sin orden de retiro registrada' ?></span>
          </div>
          <div class="tabla-contenedor">
            <table>
              <thead><tr><th>N.º de contenedor</th><th>Tipo</th><th>Retiro de vacío</th><th>Peso bruto (kg)</th><th><?= termino_p('VGM') ?></th><th>Precinto</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($contenedores as $c) {
                  $cl = ['pendiente' => 'estado-alerta', 'declarado' => 'estado-ok', 'transmitido' => 'estado-ok'][$c['vgm']]; ?>
                <tr>
                  <td><?= e($c['numero']) ?></td>
                  <td><?= e($c['tipo']) ?></td>
                  <td><?= $c['retiro_fecha'] ? e(f_corta($c['retiro_fecha'])) . ' · ' . e($c['retiro_lugar']) : '—' ?></td>
                  <?php if ($actuar && puede('crear_operaciones') && $abierta) { ?>
                    <td><input type="number" min="0" name="peso_bruto" form="cont-<?= (int) $c['id'] ?>" value="<?= e($c['peso_bruto']) ?>" style="width:110px"></td>
                    <td><select name="vgm" form="cont-<?= (int) $c['id'] ?>">
                      <?php foreach (['pendiente' => 'Pendiente', 'declarado' => 'Declarado', 'transmitido' => 'Transmitido'] as $k => $t) { ?><option value="<?= $k ?>"<?= $c['vgm'] === $k ? ' selected' : '' ?>><?= $t ?></option><?php } ?>
                    </select></td>
                    <td><input type="text" name="precinto" maxlength="30" form="cont-<?= (int) $c['id'] ?>" value="<?= e($c['precinto']) ?>" style="width:120px"></td>
                    <td>
                      <form id="cont-<?= (int) $c['id'] ?>" action="operacion.php?id=<?= $id ?>" method="post">
                        <?= csrf_campo() ?><input type="hidden" name="accion" value="contenedor_act"><input type="hidden" name="contenedor_id" value="<?= (int) $c['id'] ?>">
                        <button type="submit" class="boton boton-secundario boton-chico">Guardar</button>
                      </form>
                    </td>
                  <?php } else { ?>
                    <td class="num"><?= $c['peso_bruto'] ? number_format($c['peso_bruto'], 0, ',', '.') : '<span class="suave">Pendiente</span>' ?></td>
                    <td><?= badge(ucfirst($c['vgm']), $cl) ?></td>
                    <td><?= e($c['precinto'] ?: '—') ?></td>
                    <td></td>
                  <?php } ?>
                </tr>
              <?php } if (!$contenedores) { ?>
                <tr><td colspan="7" class="suave">Todavía no se cargaron contenedores.</td></tr>
              <?php } ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php if ($abierta && $actuar && puede('crear_operaciones')) { ?>
        <form class="tarjeta" action="operacion.php?id=<?= $id ?>" method="post">
          <?= csrf_campo() ?><input type="hidden" name="accion" value="contenedor">
          <div class="tarjeta-cabecera"><h2>Agregar contenedor</h2></div>
          <div class="tarjeta-cuerpo formulario">
            <div class="campos">
              <div class="campo"><label for="ct-num">N.º de contenedor</label><input type="text" id="ct-num" name="numero" maxlength="20" required placeholder="MSCU 123456-7"></div>
              <div class="campo"><label for="ct-ret">Retiro de vacío</label><input type="text" id="ct-ret" name="retiro_fecha" placeholder="dd/mm/aaaa"></div>
              <div class="campo"><label for="ct-lug">Lugar de retiro</label><input type="text" id="ct-lug" name="retiro_lugar" maxlength="80" placeholder="Depósito Dock Sud"></div>
              <div class="campo"><label for="ct-peso">Peso bruto (kg)</label><input type="number" id="ct-peso" name="peso_bruto" min="0"></div>
              <div class="campo"><label for="ct-vgm">VGM</label><select id="ct-vgm" name="vgm"><option value="pendiente">Pendiente</option><option value="declarado">Declarado</option><option value="transmitido">Transmitido</option></select></div>
              <div class="campo"><label for="ct-pre">Precinto</label><input type="text" id="ct-pre" name="precinto" maxlength="30"></div>
            </div>
            <div class="pie-formulario"><button type="submit" class="boton boton-primario">Agregar contenedor</button></div>
          </div>
        </form>
        <?php } ?>
      </div></details>

      <!-- ===== Documentos ===== -->
      <details id="documentos" class="acordeon"><summary><span class="ac-t">Documentos <span class="contador"><?= count($documentos) ?></span></span><span class="ac-h">lo que se cargó y lo que mandó el cliente</span></summary><div class="acordeon-cuerpo">
        <?php if (($abierta || $op['estado'] === 'zarpado') && $actuar && puede('cargar_documentos')) { ?>
        <form class="tarjeta" action="operacion.php?id=<?= $id ?>" method="post" enctype="multipart/form-data">
          <?= csrf_campo() ?><input type="hidden" name="accion" value="documento">
          <div class="tarjeta-cabecera"><h2>Subir un documento</h2></div>
          <div class="tarjeta-cuerpo formulario">
            <div class="campos">
              <div class="campo">
                <label for="doc-tipo">Tipo</label>
                <select id="doc-tipo" name="tipo">
                  <?php foreach (['Declaración (DECLA)', 'DRAFT', 'VGM', 'Ticket de pesada', 'Confirmación de booking', 'Orden de retiro', 'Otro'] as $t) { ?><option><?= e($t) ?></option><?php } ?>
                </select>
              </div>
              <div class="campo">
                <label for="doc-estado">Estado</label>
                <select id="doc-estado" name="estado">
                  <option value="solicitado">Solicitado</option><option value="recibido" selected>Recibido</option><option value="revisado">Revisado</option><option value="presentado">Presentado</option>
                </select>
              </div>
              <div class="campo">
                <label for="doc-origen">Origen</label>
                <input type="text" id="doc-origen" name="origen" maxlength="80" placeholder="<?= e($op['naviera'] ?: 'Naviera, cliente, terminal…') ?>">
              </div>
              <div class="campo">
                <label for="doc-archivo">Archivo</label>
                <input type="file" id="doc-archivo" name="archivo">
              </div>
              <div class="campo campo-ancho">
                <label for="doc-obs">Observaciones</label>
                <textarea id="doc-obs" name="observaciones" placeholder="Por ejemplo: corrección pedida por la naviera"></textarea>
              </div>
              <div class="campo campo-ancho">
                <label class="casilla"><input type="checkbox" name="visible_cliente" value="1" checked> Visible para el cliente en su portal</label>
              </div>
            </div>
            <p class="ayuda">Si ya existe un documento del mismo tipo, se guarda como una versión nueva y se conserva la anterior. Un documento “solicitado” puede cargarse sin archivo.</p>
            <div class="pie-formulario"><button type="submit" class="boton boton-primario">Guardar documento</button></div>
          </div>
        </form>
        <?php } ?>

        <div class="tarjeta">
          <div class="tarjeta-cabecera"><h2>Documentos de la operación</h2></div>
          <div class="tabla-contenedor">
            <table>
              <thead><tr><th>Documento</th><th>Versión</th><th>Estado</th><th>Origen</th><th>Actualizado</th><th class="envolver">Observaciones</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($documentos as $d) { ?>
                <tr>
                  <td><?= e($d['tipo']) ?><?= $d['visible_cliente'] ? '' : ' <span class="chico suave">(interno)</span>' ?></td>
                  <td>v<?= (int) $d['version'] ?></td>
                  <td><?= badge(ucfirst($d['estado']), $orden_label[$d['estado']]) ?></td>
                  <td><?= e($d['origen'] ?: '—') ?></td>
                  <td class="num"><?= e(f_hora($d['creado'])) ?></td>
                  <td class="envolver<?= $d['observaciones'] ? '' : ' suave' ?>"><?= e($d['observaciones'] ?: '—') ?></td>
                  <td><?= $d['archivo_ruta'] ? '<a href="../descargar.php?id=' . (int) $d['id'] . '" class="boton boton-secundario boton-chico">Ver</a>' : '' ?></td>
                </tr>
              <?php } if (!$documentos) { ?>
                <tr><td colspan="7" class="suave">Todavía no hay documentos cargados.</td></tr>
              <?php } ?>
              </tbody>
            </table>
          </div>
        </div>
      </div></details>

      <!-- ===== Cambios ===== -->
      <details id="cambios" class="acordeon"><summary><span class="ac-t">Qué cambió <span class="contador"><?= count($historial) ?></span></span><span class="ac-h">historial de cambios y novedades</span></summary><div class="acordeon-cuerpo">
        <?php if (($abierta || $op['estado'] === 'zarpado') && $actuar && puede('registrar_cambios')) { ?>
        <form class="tarjeta" action="operacion.php?id=<?= $id ?>" method="post">
          <?= csrf_campo() ?><input type="hidden" name="accion" value="cambio">
          <div class="tarjeta-cabecera"><h2>Avisar un cambio</h2></div>
          <div class="tarjeta-cuerpo formulario">
            <p class="ayuda" style="margin:0 0 10px">Usalo cuando la naviera cambia el barco, una fecha o un plazo, o cuando hay una novedad. Se guarda en el historial y se avisa a quienes elijas.</p>
            <div class="campos">
              <div class="campo">
                <label for="cambio-dato">Qué cambió</label>
                <select id="cambio-dato" name="dato">
                  <?php foreach (array_keys(datos_cambiables()) as $d) { if ($op['estado'] === 'zarpado' && !in_array($d, ['ETA', 'Novedad posterior al zarpe'], true)) { continue; } ?><option value="<?= e($d) ?>"><?= e(isset($etiquetas_cambio[$d]) ? $etiquetas_cambio[$d] : $d) ?></option><?php } ?>
                </select>
              </div>
              <div class="campo">
                <label for="cambio-actual">Cómo estaba</label>
                <input type="text" id="cambio-actual" value="" disabled>
              </div>
              <div class="campo">
                <label for="cambio-nuevo">Cómo queda</label>
                <input type="text" id="cambio-nuevo" name="valor_nuevo" maxlength="200" placeholder="">
                <span class="ayuda" id="cambio-ayuda"></span>
              </div>
              <div class="campo">
                <label for="cambio-origen">De dónde viene la novedad</label>
                <select id="cambio-origen" name="origen">
                  <option>Correo de la naviera</option><option>Portal de la naviera</option><option>Pedido del cliente</option><option>Terminal</option>
                </select>
              </div>
              <div class="campo" id="roleo-etd" hidden>
                <label for="cambio-etd">Nueva fecha de salida (solo si el barco se corre; opcional)</label>
                <input type="text" id="cambio-etd" name="etd_nuevo" placeholder="dd/mm/aaaa">
              </div>
              <div class="campo" id="roleo-cut" hidden>
                <label for="cambio-cut">Nuevo límite para los documentos (solo si el barco se corre; opcional)</label>
                <input type="text" id="cambio-cut" name="cutoff_nuevo" placeholder="dd/mm/aaaa hh:mm">
              </div>
              <div class="campo campo-ancho">
                <label for="cambio-detalle">Detalle (opcional)</label>
                <textarea id="cambio-detalle" name="detalle"></textarea>
              </div>
              <div class="campo campo-ancho">
                <span class="etiqueta">Avisar a</span>
                <?php foreach ($participantes_cliente as $p) { ?>
                  <label class="casilla"><input type="checkbox" name="avisar[]" value="<?= (int) $p['id'] ?>"<?= in_array($p['id'], $dest_ids) ? ' checked' : '' ?>>
                    <?= e($p['nombre']) ?> (<?= $p['rol'] === 'despachante' ? 'despachante' : e($op['empresa']) ?>)<?= $p['rol'] !== 'otro' ? ' · pide “Notado”' : '' ?></label>
                <?php } ?>
                <label class="casilla"><input type="checkbox" name="avisar[]" value="responsable" checked> <?= e($op['responsable']) ?> (responsable)</label>
              </div>
            </div>
            <div class="pie-formulario"><button type="submit" class="boton boton-primario">Guardar y avisar</button></div>
          </div>
        </form>
        <?php } ?>

        <?php if (puede('ver_historial')) { ?>
        <div class="tarjeta">
          <div class="tarjeta-cabecera"><h2>Historial de cambios</h2></div>
          <div class="tarjeta-cuerpo">
            <ol class="historial">
              <?php foreach ($historial as $h) { $r = resumen_avisos_cambio($h['id']); ?>
              <li>
                <div class="cuando"><?= e(f_completa($h['creado'])) ?> · <?= e($h['usuario']) ?><?= $h['origen'] ? ' · ' . e($h['origen']) : '' ?></div>
                <div class="que"><?= e($h['dato']) ?></div>
                <div class="cambio-valor"><span class="valor-anterior"><?= e($h['valor_anterior'] ?: '—') ?></span> → <span class="valor-nuevo"><?= e($h['valor_nuevo']) ?></span></div>
                <?php if ($h['detalle']) { ?><div class="chico"><?= e($h['detalle']) ?></div><?php } ?>
                <?php if ($r['total']) { ?>
                  <div class="chico suave">Avisado a <?= $r['total'] ?> destinatario<?= $r['total'] == 1 ? '' : 's' ?><?= $r['requeridos'] ? ' · “Notado”: ' . $r['notados'] . ' de ' . $r['requeridos'] . ' confirmados' : '' ?><?= $r['fallidos'] ? ' · ' . $r['fallidos'] . ' no enviado' : '' ?></div>
                <?php } ?>
              </li>
              <?php } if (!$historial) { ?>
              <li><div class="chico suave">Todavía no hay cambios registrados.</div></li>
              <?php } ?>
            </ol>
          </div>
        </div>
        <?php } else { ?>
        <div class="tarjeta"><div class="tarjeta-cuerpo"><p class="suave">Tu cuenta no tiene permiso para ver el historial completo de cambios.</p></div></div>
        <?php } ?>
      </div></details>

      <!-- ===== Comunicaciones ===== -->
      <details id="comunicaciones" class="acordeon"><summary><span class="ac-t">Avisos enviados <span class="contador"><?= count($comunicaciones) ?></span></span><span class="ac-h">qué se le avisó a cada persona</span></summary><div class="acordeon-cuerpo">
        <div class="tarjeta">
          <div class="tarjeta-cabecera"><h2>Avisos enviados</h2></div>
          <div class="tabla-contenedor">
            <table>
              <thead><tr><th>Fecha</th><th>Evento</th><th>Destinatario</th><th>Envío</th><th>“Notado”</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($comunicaciones as $c) { ?>
                <tr>
                  <td class="num"><?= e(f_hora($c['creado'])) ?></td>
                  <td><?= e($c['evento']) ?><?= $c['reenvio_de'] ? ' <span class="chico suave">(reenvío)</span>' : '' ?></td>
                  <td><?= e($c['destinatario_nombre']) ?><div class="chico suave"><?= e($c['destinatario_correo']) ?></div></td>
                  <td><?= $c['estado_envio'] === 'enviado' ? badge('Enviado', 'estado-ok') : '<span title="' . e($c['error']) . '">' . badge('Falló', 'estado-peligro') . '</span>' ?></td>
                  <td><?php
                      if ($c['notado_en'] !== null) { echo badge(f_hora($c['notado_en']), 'estado-ok'); }
                      elseif ($c['requiere_notado']) { echo strtotime($c['plazo_notado']) < time() ? badge('Vencido', 'estado-peligro') : badge('Esperando', 'estado-alerta'); }
                      else { echo '<span class="suave">No requiere</span>'; } ?></td>
                  <td>
                    <?php if ($actuar && puede('registrar_cambios')) { ?>
                      <form action="operacion.php?id=<?= $id ?>" method="post" style="display:inline">
                        <?= csrf_campo() ?><input type="hidden" name="comunicacion_id" value="<?= (int) $c['id'] ?>">
                        <?php if ($c['estado_envio'] === 'fallido') { ?>
                          <button type="submit" name="accion" value="reenviar" class="boton boton-secundario boton-chico">Reenviar</button>
                        <?php } elseif ($c['requiere_notado'] && $c['notado_en'] === null) { ?>
                          <button type="submit" name="accion" value="reenviar" class="boton boton-secundario boton-chico">Reenviar</button>
                          <button type="submit" name="accion" value="notado_manual" class="boton boton-secundario boton-chico" title="Registrar que el destinatario confirmó por otro medio">Marcar notado</button>
                        <?php } ?>
                      </form>
                    <?php } ?>
                  </td>
                </tr>
              <?php } if (!$comunicaciones) { ?>
                <tr><td colspan="6" class="suave">Todavía no se enviaron avisos.</td></tr>
              <?php } ?>
              </tbody>
            </table>
          </div>
        </div>
      </div></details>

<script>
// Valor actual del dato elegido y campos extra del roleo
(function () {
  var actuales = <?= json_encode($valores_actuales, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var sel = document.getElementById('cambio-dato');
  if (!sel) return;
  var ayudas = {'ETD': 'dd/mm/aaaa', 'ETA': 'dd/mm/aaaa', 'Cut-off documental': 'dd/mm/aaaa hh:mm', 'Cut-off físico': 'dd/mm/aaaa hh:mm', 'Fecha límite de VGM': 'dd/mm/aaaa hh:mm', 'Roleo (buque y fechas)': 'Buque nuevo'};
  function pintar() {
    document.getElementById('cambio-actual').value = actuales[sel.value] || '';
    document.getElementById('cambio-nuevo').placeholder = ayudas[sel.value] || '';
    var roleo = sel.value.indexOf('Roleo') === 0;
    document.getElementById('roleo-etd').hidden = !roleo;
    document.getElementById('roleo-cut').hidden = !roleo;
  }
  sel.addEventListener('change', pintar);
  pintar();
})();
</script>
<?php pie_personal(); ?>
