<?php
// Portal de clientes · P5 Seleccionar alternativa (caso OLEGA): se registra la elección con usuario y fecha (RF-09)
require_once __DIR__ . '/../partes/layout.php';
$u = requerir_cliente();
if (!$u['puede_seleccionar']) {
    flash('error', 'Tu cuenta no está habilitada para seleccionar alternativas.');
    redirigir('reservas.php');
}
$cid = $u['cliente_id'];

if (es_post()) {
    csrf_verificar();
    $alt = fila("SELECT * FROM alternativas WHERE id = ? AND cliente_id = ? AND estado = 'disponible'", [(int) (isset($_POST['alternativa']) ? $_POST['alternativa'] : 0), $cid]);
    if (!$alt) {
        flash('error', 'Esa alternativa ya no está disponible. Si ya elegiste una, la elección quedó registrada.');
        redirigir('alternativas.php');
    }
    $comentario = trim(isset($_POST['comentario']) ? $_POST['comentario'] : '');
    $pdo = db();
    $pdo->beginTransaction();
    // el cambio de estado solo se aplica si nadie eligió antes (evita doble selección)
    $n = ejecutar("UPDATE alternativas SET estado = 'elegida', elegida_por = ?, elegida_en = NOW(), comentario = ? WHERE id = ? AND estado = 'disponible'",
        [$u['id'], $comentario !== '' ? $comentario : null, $alt['id']]);
    if ($n) {
        ejecutar("UPDATE alternativas SET estado = 'no_elegida' WHERE cliente_id = ? AND grupo = ? AND id <> ? AND estado = 'disponible'", [$cid, $alt['grupo'], $alt['id']]);
    }
    $pdo->commit();
    if (!$n) {
        flash('error', 'Otra persona de tu empresa ya eligió una alternativa.');
        redirigir('alternativas.php');
    }
    // Aviso interno al responsable de la cuenta (no hay operación todavía: la crea ARPAFLU)
    $c = fila('SELECT c.empresa, r.nombre, r.correo FROM clientes c LEFT JOIN usuarios r ON r.id = c.responsable_id WHERE c.id = ?', [$cid]);
    $naviera = valor('SELECT nombre FROM navieras WHERE id = ?', [$alt['naviera_id']]);
    if ($c && $c['correo']) {
        registrar_aviso([
            'operacion_id' => null, 'cambio_id' => null, 'evento' => 'Selección de alternativa', 'tipo' => 'seleccion',
            'nombre' => $c['nombre'], 'correo' => $c['correo'], 'dest_tipo' => 'interno',
            'asunto' => $c['empresa'] . ' eligió la alternativa ' . $alt['etiqueta'] . ' (' . $alt['grupo'] . ')',
            'cuerpo' => $u['nombre'] . ' (' . $c['empresa'] . ') eligió la alternativa ' . $alt['etiqueta'] . ': ' . $naviera . ' · ' . $alt['buque'] . ' · ' . dinero($alt['flete'])
                . ' por contenedor.' . ($comentario !== '' ? ' Comentario: ' . $comentario : '') . ' Falta crear la operación con la reserva elegida.',
            'notado' => false,
        ]);
    }
    flash('ok', 'Registramos tu elección (alternativa ' . $alt['etiqueta'] . '). ARPAFLU recibió el aviso.');
    redirigir('reservas.php');
}

$grupo = valor("SELECT grupo FROM alternativas WHERE cliente_id = ? AND estado = 'disponible' ORDER BY creado DESC, id DESC LIMIT 1", [$cid]);
$alts = $grupo ? consulta("SELECT a.*, n.nombre AS naviera FROM alternativas a LEFT JOIN navieras n ON n.id = a.naviera_id
                            WHERE a.cliente_id = ? AND a.grupo = ? AND a.estado = 'disponible' ORDER BY a.etiqueta", [$cid, $grupo]) : [];

cabecera_cliente('Elegir una opción', 'alternativas');
?>
    <?php if (!$alts) { ?>
      <div class="encabezado-pagina"><div><h1>Elegir alternativa</h1><p>No hay alternativas pendientes de elección por el momento.</p></div></div>
      <a href="reservas.php" class="boton boton-secundario">Volver a Mis reservas</a>
    <?php } else { ?>
    <div class="encabezado-pagina">
      <div>
        <h1>Elegí una opción</h1>
        <p><?= e($grupo) ?><?= $alts[0]['carga'] ? ' · ' . e($alts[0]['carga']) : '' ?> · Las opciones ya tienen el lugar confirmado por la naviera: elegí la que más te convenga.</p>
      </div>
    </div>

    <form action="alternativas.php" method="post" data-confirmar="¿Confirmás esta opción? Después de confirmar no se puede cambiar desde el portal.">
      <?= csrf_campo() ?>
      <div class="alternativas">
        <?php foreach ($alts as $a) { ?>
        <label class="alternativa">
          <input type="radio" name="alternativa" value="<?= (int) $a['id'] ?>" required>
          <span class="marca-elegida">Tu elección</span>
          <div class="alternativa-cabecera">
            <div>
              <div class="chico suave">Opción <?= e($a['etiqueta']) ?></div>
              <div class="naviera"><?= e($a['naviera']) ?></div>
            </div>
            <div class="alternativa-precio"><?= e(dinero($a['flete'])) ?> <small>/ cont.</small></div>
          </div>
          <dl class="datos">
            <div><dt>Barco</dt><dd><?= e($a['buque']) ?></dd></div>
            <div><dt>Ruta</dt><dd><?= e($a['via']) ?></dd></div>
            <div><dt>Sale (<?= termino('ETD') ?>)</dt><dd><?= e(f_fecha($a['etd'])) ?></dd></div>
            <div><dt>Llega (<?= termino('ETA') ?>)</dt><dd><?= e(f_fecha($a['eta'])) ?></dd></div>
            <div><dt>Documentos hasta (<?= termino('Cut-off documental', 'cut-off') ?>)</dt><dd><?= e(f_hora($a['cutoff_doc'])) ?></dd></div>
            <div><dt><?= termino('Días libres') ?></dt><dd><?= (int) $a['dias_libres'] ?></dd></div>
          </dl>
        </label>
        <?php } ?>
      </div>

      <div class="tarjeta" style="margin-top:18px">
        <div class="tarjeta-cuerpo formulario">
          <div class="campo">
            <label for="comentario">Comentario para ARPAFLU (opcional)</label>
            <textarea id="comentario" name="comentario"></textarea>
          </div>
          <p class="ayuda" style="margin:0">Al confirmar, la elección queda registrada con su usuario y la fecha, y ARPAFLU recibe un aviso. Las alternativas no elegidas se conservan en el historial.</p>
          <div class="pie-formulario">
            <a href="reservas.php" class="boton boton-secundario">Volver al inicio</a>
            <button type="submit" class="boton boton-primario" id="confirmar-seleccion" disabled>Confirmar mi elección</button>
          </div>
        </div>
      </div>
    </form>
    <?php } ?>
<?php pie_cliente(); ?>
