<?php
// Portal de clientes · Ayuda: cómo funciona, glosario y con quién hablar
require_once __DIR__ . '/../partes/layout.php';
$u = requerir_cliente();
$resp = responsable_de($u['cliente_id']);

cabecera_cliente('Ayuda', 'ayuda');
?>
    <div class="encabezado-pagina">
      <div>
        <h1>Ayuda</h1>
        <p>Cómo funciona el portal, qué significa cada palabra y con quién hablar.</p>
      </div>
      <div class="acciones"><a href="#" class="boton boton-secundario" data-tour-abrir>Ver el recorrido de bienvenida</a></div>
    </div>

    <h2 class="titulo-seccion" style="margin-top:0">¿Cómo funciona?</h2>
    <ol class="lista" style="list-style:none;counter-reset:paso">
      <li style="display:block"><strong>1. Pedís lugar.</strong> En «Pedir una reserva» elegís un barco que tenga lugar y decís cuántos contenedores necesitás. Si no hay lugar, el portal te lo explica y no hace falta llenar nada.</li>
      <li style="display:block"><strong>2. ARPAFLU responde.</strong> Revisa tu pedido y te avisa por mail. Si lo acepta, se convierte en una reserva.</li>
      <li style="display:block"><strong>3. Seguís tu carga.</strong> En «Inicio» ves en qué paso está cada reserva. Un cuadro de color te dice qué tenés que hacer ahora; si no dice nada, no tenés que hacer nada.</li>
      <li style="display:block"><strong>4. Respondés cuando te lo pedimos.</strong> Si hay una novedad, tocás «Notado». Si falta un papel, lo subís desde la reserva.</li>
    </ol>

    <h2 class="titulo-seccion">¿Con quién hablo?</h2>
    <div class="tarjeta"><div class="tarjeta-cuerpo">
      <?php if ($resp) { ?>
        <p style="margin:0">Tu responsable en ARPAFLU es <strong><?= e($resp['nombre']) ?></strong>. Podés escribirle a <strong><?= e($resp['correo']) ?></strong>.</p>
      <?php } else { ?>
        <p style="margin:0">Todavía no tenés un responsable asignado. Escribile a ARPAFLU.</p>
      <?php } ?>
    </div></div>

    <h2 class="titulo-seccion">¿Qué significa cada palabra?</h2>
    <dl class="glosario">
      <?php foreach (glosario() as $palabra => $significado) { ?>
        <div><dt><?= e($palabra) ?></dt><dd><?= e($significado) ?></dd></div>
      <?php } ?>
    </dl>
<?php pie_cliente();
