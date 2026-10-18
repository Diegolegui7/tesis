<?php
// Portal de clientes · Ficha de la reserva, confirmación «Notado» y envío de documentos (P10 / RF-11 / RF-17)
require_once __DIR__ . '/../partes/layout.php';
require_once __DIR__ . '/../inc/operaciones.php';
$u = requerir_cliente();

$id = (int) (isset($_GET['id']) ? $_GET['id'] : 0);
$op = cargar_operacion($id);
if (!$op || (int) $op['cliente_id'] !== (int) $u['cliente_id']) {   // un cliente nunca ve operaciones de otro
    http_response_code(404);
    exit('<p style="font-family:sans-serif;padding:24px">No encontramos esa reserva. <a href="reservas.php">Volver a Mis reservas</a></p>');
}
$abierta = !in_array($op['estado'], ['zarpado', 'cancelada'], true);

if (es_post()) {
    csrf_verificar();
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';
    if ($accion === 'notado') {                                       // P10 · Confirmar «Notado»
        $cid = (int) (isset($_POST['comunicacion_id']) ? $_POST['comunicacion_id'] : 0);
        $c = fila("SELECT id FROM comunicaciones WHERE id = ? AND operacion_id = ? AND destinatario_tipo = 'cliente'", [$cid, $id]);
        if ($c && confirmar_notado($cid, $u['id'])) {
            flash('ok', 'Gracias, quedó registrado que tomaste conocimiento.');
        } else {
            flash('error', 'No se pudo registrar la confirmación (puede que ya estuviera confirmada).');
        }
    } elseif ($accion === 'documento' && $abierta) {
        $archivo = subir_archivo('archivo', 'operaciones/' . $op['id']);
        $tipo = isset($_POST['tipo']) ? $_POST['tipo'] : '';
        if (!in_array($tipo, ['VGM', 'Declaración (DECLA)', 'Corrección de DRAFT', 'Otro'], true)) {
            flash('error', 'Elegí el tipo de documento.');
        } elseif ($archivo === null) {
            flash('error', 'Adjuntá el archivo.');
        } else {
            $com = trim(isset($_POST['comentario']) ? $_POST['comentario'] : '');
            $r = guardar_documento($op, $u, $tipo, 'recibido', $op['empresa'], $com, true, $archivo);
            if ($r[0]) {
                aviso_interno($op, 'Documento recibido del cliente', 'documento', $op['referencia'] . ' · ' . $tipo . ' enviado por ' . $op['empresa'],
                    $u['nombre'] . ' (' . $op['empresa'] . ') envió: ' . $tipo . ($com !== '' ? '. Comentario: ' . $com : '') . '.');
            }
            flash($r[0] ? 'ok' : 'error', $r[0] ? 'Documento enviado a ARPAFLU.' : $r[1]);
        }
    }
    redirigir('operacion.php?id=' . $id);
}

$pendientes = consulta(
    "SELECT * FROM comunicaciones WHERE operacion_id = ? AND destinatario_tipo = 'cliente' AND requiere_notado = 1 AND notado_en IS NULL ORDER BY creado DESC", [$id]);
$confirmados = consulta(
    "SELECT * FROM comunicaciones WHERE operacion_id = ? AND destinatario_tipo = 'cliente' AND requiere_notado = 1 AND notado_en IS NOT NULL ORDER BY notado_en DESC LIMIT 2", [$id]);
$docs = consulta('SELECT * FROM documentos WHERE operacion_id = ? AND visible_cliente = 1 ORDER BY tipo, version DESC', [$id]);
$despachante = valor("SELECT p.nombre FROM operacion_destinatarios od JOIN participantes p ON p.id = od.participante_id WHERE od.operacion_id = ? AND p.rol = 'despachante' LIMIT 1", [$id]);
list($n_pend, $docs_faltan) = pendientes_cliente($id);
$q = que_hacer_ahora($op, $n_pend, $docs_faltan);
$est = estado_cliente($op['estado']);

$fecha = function ($etiqueta, $dt, $conHora) use ($abierta) {
    if (!$dt) { return ''; }
    $f = faltan($dt);
    $clase = $abierta && $f['dias'] !== null && $f['dias'] >= 0 && $f['dias'] <= 3 ? 'proxima' : '';
    return '<div class="fecha ' . $clase . '"><span class="etiqueta">' . $etiqueta . '</span><strong>' . e($conHora ? f_hora($dt) : f_fecha($dt)) . '</strong>'
        . ($abierta && $conHora && $f['dias'] !== null && $f['dias'] >= 0 ? '<span class="chico">Faltan ' . e($f['texto']) . '</span>' : '') . '</div>';
};
$cls_doc = ['solicitado' => 'estado-alerta', 'recibido' => 'estado-ok', 'revisado' => 'estado-info', 'presentado' => 'estado-ok'];
$txt_doc = ['solicitado' => 'Falta enviarlo', 'recibido' => 'Recibido', 'revisado' => 'Revisado', 'presentado' => 'Presentado'];

cabecera_cliente($op['referencia'], 'reservas');
?>
    <div class="encabezado-pagina">
      <div>
        <div class="migas"><a href="reservas.php">Inicio</a> / <?= e($op['referencia']) ?></div>
        <h1><?= e($op['pod']) ?> · <?= (int) $op['cantidad'] ?> × <?= termino($op['tipo_contenedor']) ?> <span style="vertical-align:middle"><?= badge_cliente($op['estado']) ?></span></h1>
        <p><?= e($op['referencia']) ?><?= $op['referencia_cliente'] ? ' · tu referencia ' . e($op['referencia_cliente']) : '' ?> · <?= $op['booking'] ? termino('Booking') . ' ' . e($op['booking']) . ' · ' : '' ?><?= e($op['buque'] ?: 'Barco a confirmar') ?> · <?= e($op['pol']) ?> → <?= e($op['pod']) ?></p>
      </div>
    </div>

    <?php if ($op['estado'] === 'cancelada') { ?>
      <div class="aviso aviso-peligro"><div><strong>Reserva cancelada</strong><p>El <?= e(f_completa($op['cancelada_en'])) ?>. Motivo: <?= e($op['cancelacion_motivo']) ?></p></div></div>
    <?php } else { ?>
      <div class="reserva-tarjeta" style="padding-bottom:12px"><?= barra_progreso($op['estado']) ?><p class="suave" style="text-align:center;margin:8px 0 0"><?= e($est[1]) ?></p></div>
    <?php } ?>

    <section class="cuadro-hacer <?= e($q[3]) ?>" aria-label="Qué tenés que hacer ahora">
      <div>
        <h2>Qué tenés que hacer ahora</h2>
        <p><strong><?= e($q[0]) ?>.</strong> <?= e($q[1]) ?></p>
      </div>
      <?php if ($q[2]) { ?><a class="boton boton-primario" href="<?= e($q[2][1]) ?>"><?= e($q[2][0]) ?></a><?php } ?>
    </section>

    <div id="avisos">
    <?php foreach ($pendientes as $c) { ?>
    <form class="aviso aviso-info" action="operacion.php?id=<?= $id ?>" method="post">
      <?= csrf_campo() ?><input type="hidden" name="accion" value="notado"><input type="hidden" name="comunicacion_id" value="<?= (int) $c['id'] ?>">
      <div>
        <strong>Aviso de ARPAFLU · <?= e($c['evento']) ?> · <?= e(f_hora($c['creado'])) ?></strong>
        <p style="white-space:pre-line"><?= e($c['cuerpo']) ?></p>
      </div>
      <div class="acciones"><button type="submit" class="boton boton-primario" title="Avisa a ARPAFLU que leíste esta novedad">Notado: ya lo leí</button></div>
    </form>
    <?php } ?>
    <?php foreach ($confirmados as $c) { ?>
    <div class="aviso aviso-info">
      <div><strong><?= e($c['evento']) ?> · informado el <?= e(f_hora($c['creado'])) ?></strong></div>
      <div class="acciones"><span class="estado estado-ok">Ya lo leíste el <?= e(f_hora($c['notado_en'])) ?></span></div>
    </div>
    <?php } ?>
    </div>

    <div class="tarjeta">
      <div class="tarjeta-cabecera"><h2>Fechas importantes</h2></div>
      <div class="tarjeta-cuerpo">
        <div class="fechas">
          <?= $fecha('Último momento para entregar documentos (' . termino('Cut-off documental', 'cut-off documental') . ')', $op['cutoff_doc'], true) ?>
          <?= $fecha('Último momento para llevar la carga al puerto (' . termino('Cut-off físico', 'cut-off físico') . ')', $op['cutoff_fisico'], true) ?>
          <?= $fecha('Sale el barco (' . termino('ETD') . ')', $op['etd'], false) ?>
          <?= $fecha('Llega a destino (' . termino('ETA') . ', estimado)', $op['eta'], false) ?>
        </div>
      </div>
    </div>

    <div class="grilla grilla-2">
      <div class="tarjeta">
        <div class="tarjeta-cabecera"><h2>Datos de la reserva</h2></div>
        <div class="tarjeta-cuerpo">
          <dl class="datos">
            <div><dt>Naviera</dt><dd><?= e($op['naviera'] ?: '—') ?></dd></div>
            <div><dt>Barco</dt><dd><?= e($op['buque'] ?: 'A confirmar') ?></dd></div>
            <div><dt><?= termino('Terminal') ?></dt><dd><?= e($op['terminal'] ?: 'A confirmar') ?></dd></div>
            <div><dt>Ruta</dt><dd><?= $op['via'] === 'directa' ? 'Directa' : termino('Transbordo', 'Con transbordo') ?></dd></div>
            <div><dt>Contenedores</dt><dd><?= (int) $op['cantidad'] ?> × <?= termino($op['tipo_contenedor']) ?></dd></div>
            <div><dt><?= termino('Días libres') ?> en destino</dt><dd><?= $op['dias_libres'] !== null ? (int) $op['dias_libres'] : '—' ?></dd></div>
            <?php if ($op['cot_flete'] !== null) { ?><div><dt><?= termino('Flete') ?></dt><dd><?= e(dinero($op['cot_flete'])) ?> por contenedor</dd></div><?php } ?>
            <?php if ($despachante) { ?><div><dt>Despachante</dt><dd><?= e($despachante) ?></dd></div><?php } ?>
          </dl>
        </div>
      </div>

      <div class="tarjeta">
        <div class="tarjeta-cabecera"><h2>Documentos</h2></div>
        <ul class="lista">
          <?php foreach ($docs as $d) { ?>
          <li>
            <div><div class="titulo"><?= e($d['tipo']) ?><?= $d['version'] > 1 ? ' (versión ' . (int) $d['version'] . ')' : '' ?></div>
              <div class="chico suave"><?= e(f_corta($d['creado'])) ?><?= $d['observaciones'] ? ' · ' . e($d['observaciones']) : '' ?></div></div>
            <?php if ($d['archivo_ruta']) { ?>
              <a href="../descargar.php?id=<?= (int) $d['id'] ?>" class="boton boton-secundario boton-chico">Descargar</a>
            <?php } else { ?>
              <?= badge($txt_doc[$d['estado']], $cls_doc[$d['estado']]) ?>
            <?php } ?>
          </li>
          <?php } if (!$docs) { ?>
          <li><div class="chico suave">Todavía no hay documentos. Cuando haya, los vas a ver acá.</div></li>
          <?php } ?>
        </ul>
      </div>
    </div>

    <?php if ($abierta) { ?>
    <form class="tarjeta" id="enviar" action="operacion.php?id=<?= $id ?>" method="post" enctype="multipart/form-data">
      <?= csrf_campo() ?><input type="hidden" name="accion" value="documento">
      <div class="tarjeta-cabecera"><h2>Enviar un documento a ARPAFLU</h2></div>
      <div class="tarjeta-cuerpo formulario">
        <p class="ayuda" style="margin:0">Elegí qué documento es, adjuntá el archivo (un PDF, una foto o una planilla) y tocá «Enviar». ARPAFLU recibe un aviso.</p>
        <div class="campos">
          <div class="campo">
            <label for="env-tipo">¿Qué documento es?</label>
            <select id="env-tipo" name="tipo">
              <option value="VGM">VGM (peso verificado del contenedor)</option>
              <option value="Declaración (DECLA)">Declaración (datos de la carga)</option>
              <option value="Corrección de DRAFT">Corrección del borrador (DRAFT)</option>
              <option value="Otro">Otro documento</option>
            </select>
          </div>
          <div class="campo"><label for="env-archivo">Archivo</label><input type="file" id="env-archivo" name="archivo" required></div>
          <div class="campo campo-ancho"><label for="env-obs">Comentario para ARPAFLU (opcional)</label><textarea id="env-obs" name="comentario"></textarea></div>
        </div>
        <div class="pie-formulario"><button type="submit" class="boton boton-primario">Enviar documento</button></div>
      </div>
    </form>
    <?php } ?>
<?php pie_cliente();
