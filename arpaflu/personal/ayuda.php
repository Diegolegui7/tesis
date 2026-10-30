<?php
// Ayuda para el personal: cómo avanza una reserva, qué hacer en cada situación y qué significa cada palabra técnica
require_once __DIR__ . '/../partes/layout.php';
$u = requerir_empleado();
$admin = fila("SELECT nombre, correo FROM usuarios WHERE rol = 'administrador' AND activo = 1 ORDER BY id LIMIT 1");

cabecera_personal('Ayuda', 'ayuda');
?>
      <div class="encabezado-pagina">
        <div>
          <h1>Ayuda</h1>
          <p>Cómo avanza una reserva, qué hacer en cada situación y qué significa cada palabra técnica.</p>
        </div>
      </div>

      <section class="tarjeta">
        <div class="tarjeta-cabecera"><h2>Cómo avanza una reserva</h2></div>
        <div class="tarjeta-cuerpo">
          <ol class="pasos-reserva">
            <li class="ok"><span class="punto">1</span><span><strong>Llega un pedido o se crea la reserva.</strong><span class="chico suave" style="display:block">El cliente pide lugar en “Pedir una reserva” y alguien de ARPAFLU lo acepta en “Pedidos de clientes”, o se crea una reserva nueva a mano.</span></span></li>
            <li class="ok"><span class="punto">2</span><span><strong>La naviera confirma el lugar.</strong><span class="chico suave" style="display:block">Se anota el número de booking. Se le avisa al cliente.</span></span></li>
            <li class="ok"><span class="punto">3</span><span><strong>Llega la orden de retiro.</strong><span class="chico suave" style="display:block">Se anota el número de la orden para retirar el contenedor vacío.</span></span></li>
            <li class="ok"><span class="punto">4</span><span><strong>Se juntan los documentos.</strong><span class="chico suave" style="display:block">Se suben los documentos que van llegando, antes del límite que fijó la naviera.</span></span></li>
            <li class="ok"><span class="punto">5</span><span><strong>El barco sale.</strong><span class="chico suave" style="display:block">Se toca “El barco ya salió”. Después solo se anotan novedades.</span></span></li>
          </ol>
          <p class="ayuda" style="margin-top:8px">En cada reserva, el recuadro <strong>Qué sigue</strong> te dice cuál es el paso que toca.</p>
        </div>
      </section>

      <section class="tarjeta">
        <div class="tarjeta-cabecera"><h2>Qué hago si…</h2></div>
        <div class="tabla-contenedor">
          <table>
            <thead><tr><th>Situación</th><th>Dónde se hace</th></tr></thead>
            <tbody>
              <tr><td>Un cliente pidió lugar</td><td>Pedidos de clientes → “Aceptar”, o escribir el motivo y “Rechazar”.</td></tr>
              <tr><td>Tengo lugar en un barco para ofrecer</td><td>Lugar disponible → “+ Cargar embarque”.</td></tr>
              <tr><td>La naviera cambió el barco, una fecha o un plazo</td><td>Abrir la reserva → “Avisar un cambio de barco, fecha o plazo”. Se avisa a las personas que elijas.</td></tr>
              <tr><td>Llegó un documento</td><td>Abrir la reserva → “Subir un documento”.</td></tr>
              <tr><td>El cliente no confirmó un aviso (“Notado”)</td><td>Avisos enviados → “Reenviar”, o “Marcar notado” si confirmó por otro medio.</td></tr>
              <tr><td>Un aviso no llegó</td><td>Avisos enviados → pestaña “No enviados” → revisar el correo y “Reenviar”.</td></tr>
              <tr><td>El barco ya salió</td><td>Abrir la reserva → “El barco ya salió”.</td></tr>
              <tr><td>Hay que cancelar una reserva</td><td>Abrir la reserva → “Cancelar la reserva”, escribir el motivo.</td></tr>
              <tr><td>No encuentro algo</td><td>Escribir en el buscador de arriba: sirve el número de reserva, el booking, el barco, el destino o el cliente.</td></tr>
              <tr><td>Quiero ver más datos (vencimientos, historial, cotizaciones)</td><td>“Ver todas las opciones”, abajo a la izquierda, muestra el Panel, las Cotizaciones y las Alternativas.</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="tarjeta">
        <div class="tarjeta-cabecera"><h2>Qué significa cada palabra</h2></div>
        <div class="tabla-contenedor">
          <table>
            <tbody>
            <?php foreach (glosario_personal() as $palabra => $explicacion) { ?>
              <tr><td style="width:180px"><strong><?= e($palabra) ?></strong></td><td><?= e($explicacion) ?></td></tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
      </section>

      <section class="tarjeta">
        <div class="tarjeta-cuerpo">
          <p style="margin:0"><strong>¿Algo no anda o no tenés permiso para hacer algo?</strong> Escribile a <?= $admin ? e($admin['nombre']) . ' (' . e($admin['correo']) . ')' : 'quien administra el sistema' ?>. Si olvidaste tu clave, usá “Mi clave” o pedí una nueva desde la pantalla de ingreso.</p>
        </div>
      </section>
<?php pie_personal(); ?>
