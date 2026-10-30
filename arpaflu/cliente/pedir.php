<?php
// Portal de clientes · Pedir una reserva (RF-21). Solo se puede llenar el pedido si hay lugar disponible.
require_once __DIR__ . '/../partes/layout.php';
$u = requerir_cliente();
$cid = $u['cliente_id'];

if (!puede_pedir($u)) {
    cabecera_cliente('Pedir una reserva', 'pedir');
    ?>
    <div class="sin-disponibilidad">
      <div class="grande" aria-hidden="true">👀</div>
      <h2>Tu cuenta solo puede consultar</h2>
      <p class="suave">Podés ver tus reservas, pero no pedir lugar nuevo. Si lo necesitás, pedile a ARPAFLU que habilite tu cuenta.</p>
      <a class="boton boton-secundario" href="reservas.php">Volver al inicio</a>
    </div>
    <?php
    pie_cliente();
    exit;
}

if (es_post()) {
    csrf_verificar();
    $r = crear_solicitud($u, isset($_POST['disponibilidad_id']) ? $_POST['disponibilidad_id'] : 0, isset($_POST['cantidad']) ? $_POST['cantidad'] : 0,
        isset($_POST['carga']) ? $_POST['carga'] : '', isset($_POST['comentario']) ? $_POST['comentario'] : '');
    if ($r[0]) {
        flash('ok', '¡Listo! Recibimos tu pedido. ARPAFLU lo revisa y te avisa por mail. Mientras tanto, lo ves en “Mis pedidos de reserva”.');
        redirigir('reservas.php#pedidos');
    }
    flash('error', $r[1]);
    redirigir('pedir.php');
}

$todos = array_values(array_filter(disponibilidades(false), function ($d) {
    return $d['estado'] === 'abierta' && ($d['cutoff_doc'] === null || strtotime($d['cutoff_doc']) > time());
}));
usort($todos, function ($x, $y) { return ($y['pedible'] <=> $x['pedible']) ?: strcmp((string) $x['etd'], (string) $y['etd']); });   // primero los que tienen lugar
$hay = false;
foreach ($todos as $d) { if ($d['pedible']) { $hay = true; } }
$resp = responsable_de($cid);

if (!$hay && demo_activa()) {           // la guía de demostración registra que el cliente vio la pantalla sin lugar
    demo_set('vio_sin_lugar', valor('SELECT NOW()'));
}

cabecera_cliente('Pedir una reserva', 'pedir');
?>
    <div class="encabezado-pagina">
      <div>
        <h1>Pedir una reserva</h1>
        <p><?= $hay ? 'Elegí el barco donde querés mandar tu carga y decinos cuántos contenedores necesitás.' : 'Acá se ven los barcos que tienen lugar.' ?></p>
      </div>
    </div>

    <?php if (!$hay) { ?>
    <div class="sin-disponibilidad">
      <div class="grande" aria-hidden="true">⏳</div>
      <h2>Por ahora no hay lugar disponible</h2>
      <p>ARPAFLU no tiene lugar libre en ningún barco en este momento, por eso no se puede enviar un pedido.</p>
      <p class="suave">No tenés que hacer nada. Cuando haya lugar, esta pantalla se va a habilitar sola.<?php if ($resp) { ?> Si es urgente, escribile a <strong><?= e($resp['nombre']) ?></strong> (<?= e($resp['correo']) ?>).<?php } ?></p>
      <a class="boton boton-secundario" href="reservas.php">Volver al inicio</a>
    </div>
    <?php if ($todos) { ?>
      <h2 class="titulo-seccion">Barcos completos</h2>
      <div class="embarques">
      <?php foreach ($todos as $d) { ?>
        <div class="embarque sin-lugar">
          <div class="embarque-destino"><?= e($d['pod']) ?></div>
          <div class="reserva-sub"><?= e($d['buque']) ?> (<?= e($d['naviera']) ?>) · sale el <?= e(f_fecha($d['etd'])) ?></div>
          <span class="lugares nada"><?= e($d['motivo'] ?: 'Completo') ?></span>
        </div>
      <?php } ?>
      </div>
    <?php } ?>
    <?php } else { ?>

    <form action="pedir.php" method="post" id="form-pedido" data-confirmar="¿Enviamos el pedido a ARPAFLU? Podés cancelarlo mientras no lo respondan.">
      <?= csrf_campo() ?>
      <h2 class="titulo-seccion" style="margin-top:0">1. Elegí el barco</h2>
      <div class="embarques">
        <?php foreach ($todos as $d) {
            $pocos = $d['libres'] <= 2; ?>
        <label class="embarque<?= $d['pedible'] ? '' : ' sin-lugar' ?>">
          <input type="radio" name="disponibilidad_id" value="<?= (int) $d['id'] ?>" required<?= $d['pedible'] ? '' : ' disabled' ?>
                 data-libres="<?= (int) $d['libres'] ?>" data-destino="<?= e($d['pod']) ?>" data-tipo="<?= e($d['tipo_contenedor']) ?>" data-flete="<?= e((string) (float) $d['flete']) ?>" data-sale="<?= e(f_fecha($d['etd'])) ?>">
          <div class="embarque-destino"><?= e($d['pod']) ?></div>
          <div class="embarque-precio"><?= e(dinero($d['flete'])) ?> <small>por contenedor (<?= termino('Flete', 'flete') ?>)</small></div>
          <dl class="datos">
            <div><dt>Barco</dt><dd><?= e($d['buque']) ?><br><span class="suave"><?= e($d['naviera']) ?></span></dd></div>
            <div><dt>Sale (<?= termino('ETD') ?>)</dt><dd><?= e(f_fecha($d['etd'])) ?></dd></div>
            <div><dt>Llega (<?= termino('ETA') ?>)</dt><dd><?= e(f_fecha($d['eta'])) ?></dd></div>
            <div><dt>Contenedor</dt><dd><?= termino($d['tipo_contenedor']) ?></dd></div>
            <div><dt>Ruta</dt><dd><?= $d['via'] === 'directa' ? 'Directa' : termino('Transbordo', 'Con transbordo') ?></dd></div>
            <div><dt><?= termino('Días libres') ?></dt><dd><?= $d['dias_libres'] !== null ? (int) $d['dias_libres'] : '—' ?></dd></div>
          </dl>
          <?php if ($d['cutoff_doc']) { ?><div class="reserva-sub" style="margin-top:10px">Documentos hasta el <?= e(f_hora($d['cutoff_doc'])) ?> (<?= termino('Cut-off documental', 'fecha límite') ?>)</div><?php } ?>
          <?php if ($d['pedible']) { ?>
            <span class="lugares<?= $pocos ? ' pocos' : '' ?>">Quedan <?= (int) $d['libres'] ?> lugar<?= $d['libres'] == 1 ? '' : 'es' ?></span>
          <?php } else { ?>
            <span class="lugares nada"><?= e($d['motivo']) ?></span>
          <?php } ?>
        </label>
        <?php } ?>
      </div>

      <h2 class="titulo-seccion">2. Contanos qué necesitás</h2>
      <div class="tarjeta">
        <div class="tarjeta-cuerpo formulario">
          <div class="campos">
            <div class="campo">
              <label for="cantidad">¿Cuántos contenedores necesitás?</label>
              <input type="number" id="cantidad" name="cantidad" min="1" max="1" value="1" required>
              <span class="ayuda" id="ayuda-cantidad">Primero elegí un barco.</span>
            </div>
            <div class="campo">
              <label for="carga">¿Qué vas a enviar? (opcional)</label>
              <input type="text" id="carga" name="carga" maxlength="120" placeholder="Por ejemplo: maní en bolsas">
            </div>
            <div class="campo campo-ancho">
              <label for="comentario">¿Algo más que quieras decirnos? (opcional)</label>
              <textarea id="comentario" name="comentario" placeholder="Por ejemplo: necesito retirar el contenedor vacío temprano."></textarea>
            </div>
          </div>
          <div class="resumen-pedido" id="resumen" hidden></div>
          <p class="ayuda" style="margin:0">Este pedido <strong>no es todavía una reserva</strong>: ARPAFLU lo revisa y te confirma por mail. Mientras tanto, el lugar queda guardado para vos y lo podés cancelar.</p>
          <div class="pie-formulario">
            <a href="reservas.php" class="boton boton-secundario">Volver</a>
            <button type="submit" class="boton boton-primario" id="enviar-pedido" disabled>Enviar pedido</button>
          </div>
        </div>
      </div>
    </form>

    <script>
    (function () {
      var form = document.getElementById('form-pedido');
      var radios = form.querySelectorAll('input[name="disponibilidad_id"]');
      var cant = document.getElementById('cantidad'), ayuda = document.getElementById('ayuda-cantidad'), res = document.getElementById('resumen'), btn = document.getElementById('enviar-pedido');
      function fmt(n) { return 'US$ ' + n.toLocaleString('es-AR', { maximumFractionDigits: 0 }); }
      function actualizar() {
        var r = form.querySelector('input[name="disponibilidad_id"]:checked');
        radios.forEach(function (x) { x.closest('.embarque').classList.toggle('elegida', x.checked); });
        if (!r) { return; }
        var libres = parseInt(r.dataset.libres, 10);
        cant.max = libres;
        if (parseInt(cant.value, 10) > libres) { cant.value = libres; }
        if (parseInt(cant.value, 10) < 1 || isNaN(parseInt(cant.value, 10))) { cant.value = 1; }
        ayuda.textContent = 'En este barco quedan ' + libres + ' lugar' + (libres === 1 ? '' : 'es') + '.';
        var n = parseInt(cant.value, 10), flete = parseFloat(r.dataset.flete);
        res.hidden = false;
        res.innerHTML = 'Vas a pedir <strong>' + n + ' contenedor' + (n === 1 ? '' : 'es') + ' de ' + r.dataset.tipo + '</strong> a <strong>' + r.dataset.destino + '</strong>. El barco sale el ' + r.dataset.sale +
          '. Precio: ' + fmt(flete) + ' por contenedor, <strong>' + fmt(flete * n) + ' en total</strong>.';
        btn.disabled = false;
      }
      radios.forEach(function (x) { x.addEventListener('change', actualizar); });
      cant.addEventListener('input', actualizar);
    })();
    </script>
    <?php } ?>
<?php pie_cliente();
