<?php
// SS2 · Disponibilidad de espacio: embarques con cupo de contenedores que los clientes pueden pedir (RF-21)
require_once __DIR__ . '/../partes/layout.php';
$u = requerir_empleado('crear_operaciones');

if (es_post()) {
    csrf_verificar();
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';
    if ($accion === 'crear') {
        $naviera = (int) (isset($_POST['naviera_id']) ? $_POST['naviera_id'] : 0);
        $buque = trim(isset($_POST['buque']) ? $_POST['buque'] : '');
        $pod = trim(isset($_POST['pod']) ? $_POST['pod'] : '');
        $pol = trim(isset($_POST['pol']) ? $_POST['pol'] : '') ?: 'Buenos Aires';
        $via = isset($_POST['via']) && $_POST['via'] === 'transbordo' ? 'transbordo' : 'directa';
        $tipo = trim(isset($_POST['tipo_contenedor']) ? $_POST['tipo_contenedor'] : '') ?: "40' HC";
        $etd = parse_fecha(isset($_POST['etd']) ? $_POST['etd'] : '');
        $eta = parse_fecha(isset($_POST['eta']) ? $_POST['eta'] : '');
        $cut = parse_fecha(isset($_POST['cutoff_doc']) ? $_POST['cutoff_doc'] : '', true);
        $cupo = isset($_POST['cupo']) ? $_POST['cupo'] : '';
        $flete = isset($_POST['flete']) ? $_POST['flete'] : '';
        $libres = trim(isset($_POST['dias_libres']) ? $_POST['dias_libres'] : '');
        $err = [];
        if (!$naviera) { $err[] = 'Elegí una naviera.'; }
        if ($buque === '') { $err[] = 'Indicá el buque y viaje.'; }
        if ($pod === '') { $err[] = 'Indicá el puerto de destino.'; }
        if (!ctype_digit((string) $cupo) || (int) $cupo < 1) { $err[] = 'El cupo tiene que ser un número entero de 1 o más.'; }
        if ($flete === '' || !is_numeric($flete) || $flete < 0) { $err[] = 'Indicá el flete por contenedor (un número).'; }
        if ($etd === null) { $err[] = 'Indicá la fecha de salida (ETD).'; }
        foreach (['ETD' => $etd, 'ETA' => $eta, 'cut-off documental' => $cut] as $n => $x) {
            if ($x === false) { $err[] = 'La fecha de ' . $n . ' no se entiende (usá dd/mm/aaaa o dd/mm/aaaa hh:mm).'; }
        }
        if ($libres !== '' && !ctype_digit($libres)) { $err[] = 'Los días libres deben ser un número entero.'; }
        if ($err) {
            foreach ($err as $er) { flash('error', $er); }
        } else {
            ejecutar('INSERT INTO disponibilidad (naviera_id, buque, via, pol, pod, etd, eta, cutoff_doc, tipo_contenedor, cupo, flete, dias_libres, creado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$naviera, $buque, $via, $pol, $pod, $etd, $eta ?: null, $cut ?: null, $tipo, (int) $cupo, $flete, $libres !== '' ? (int) $libres : null, $u['id']]);
            flash('ok', 'Cargamos el embarque a ' . $pod . ' con ' . (int) $cupo . ' lugares. Los clientes ya pueden pedir lugar.');
        }
    } elseif ($accion === 'estado') {
        $id = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
        $n = ejecutar("UPDATE disponibilidad SET estado = IF(estado = 'abierta', 'cerrada', 'abierta') WHERE id = ?", [$id]);
        flash($n ? 'ok' : 'error', $n ? 'Listo, cambiamos el estado del embarque.' : 'No existe ese embarque.');
    } elseif ($accion === 'cerrar_todos') {
        $n = ejecutar("UPDATE disponibilidad SET estado = 'cerrada' WHERE estado = 'abierta'");
        flash('ok', $n ? 'Cerramos ' . $n . ' embarque' . ($n == 1 ? '' : 's') . '. Los clientes ya no pueden pedir lugar.' : 'No había embarques abiertos.');
    }
    redirigir('disponibilidad.php');
}

$embarques = disponibilidades(false);
$navieras = consulta('SELECT id, nombre FROM navieras ORDER BY nombre');
$destinos = consulta('SELECT nombre FROM puertos WHERE es_origen = 0 ORDER BY nombre');
$abiertos = 0;
foreach ($embarques as $d) { if ($d['estado'] === 'abierta') { $abiertos++; } }

cabecera_personal('Lugar disponible', 'disponibilidad');
?>
      <div class="encabezado-pagina">
        <div>
          <h1>Lugar disponible</h1>
          <p>Los barcos en los que hay lugar para ofrecer. Los clientes habilitados solo pueden pedir si queda lugar libre.</p>
        </div>
        <div class="acciones">
          <?php if ($abiertos) { ?>
          <form action="disponibilidad.php" method="post" data-confirmar="¿Cerrar todos los embarques abiertos? Los clientes no van a poder pedir lugar hasta que abras alguno."><?= csrf_campo() ?><input type="hidden" name="accion" value="cerrar_todos"><button class="boton boton-secundario">Cerrar todos los abiertos</button></form>
          <?php } ?>
          <button type="button" class="boton boton-primario" data-alternar="form-disp">+ Cargar embarque</button>
        </div>
      </div>

      <form id="form-disp" class="tarjeta" action="disponibilidad.php" method="post" hidden style="margin-bottom:18px">
        <?= csrf_campo() ?><input type="hidden" name="accion" value="crear">
        <div class="tarjeta-cabecera"><h2>Cargar embarque con lugar</h2></div>
        <div class="tarjeta-cuerpo formulario">
          <div class="campos">
            <div class="campo"><label for="d-naviera">Naviera</label><select id="d-naviera" name="naviera_id" required><?php foreach ($navieras as $n) { ?><option value="<?= (int) $n['id'] ?>"><?= e($n['nombre']) ?></option><?php } ?></select></div>
            <div class="campo"><label for="d-buque">Barco y viaje</label><input type="text" id="d-buque" name="buque" maxlength="120" required></div>
            <div class="campo"><label for="d-pol">Puerto de carga</label><input type="text" id="d-pol" name="pol" maxlength="80" value="Buenos Aires"></div>
            <div class="campo"><label for="d-pod">Puerto de destino</label><input type="text" id="d-pod" name="pod" maxlength="80" list="d-puertos" required><datalist id="d-puertos"><?php foreach ($destinos as $d) { ?><option value="<?= e($d['nombre']) ?>"><?php } ?></datalist></div>
            <div class="campo"><label for="d-via">Ruta</label><select id="d-via" name="via"><option value="directa">Directa</option><option value="transbordo">Con transbordo</option></select></div>
            <div class="campo"><label for="d-tipo">Tipo de contenedor</label><select id="d-tipo" name="tipo_contenedor"><option>40' HC</option><option>20' DV</option><option>40' RF</option></select></div>
            <div class="campo"><label for="d-etd">Salida (<?= termino_p('ETD') ?>)</label><input type="text" id="d-etd" name="etd" placeholder="dd/mm/aaaa" required></div>
            <div class="campo"><label for="d-eta">Llegada (<?= termino_p('ETA') ?>)</label><input type="text" id="d-eta" name="eta" placeholder="dd/mm/aaaa"></div>
            <div class="campo"><label for="d-cut">Documentos hasta (<?= termino_p('Cut-off documental', 'cut-off') ?>)</label><input type="text" id="d-cut" name="cutoff_doc" placeholder="dd/mm/aaaa hh:mm"></div>
            <div class="campo"><label for="d-cupo">Cuántos contenedores entran (cupo)</label><input type="number" id="d-cupo" name="cupo" min="1" step="1" required></div>
            <div class="campo"><label for="d-flete">Flete (US$ por contenedor)</label><input type="number" id="d-flete" name="flete" min="0" step="0.01" required></div>
            <div class="campo"><label for="d-libres">Días libres</label><input type="number" id="d-libres" name="dias_libres" min="0" step="1"></div>
          </div>
          <div class="pie-formulario">
            <button type="button" class="boton boton-secundario" data-alternar="form-disp">Cancelar</button>
            <button type="submit" class="boton boton-primario">Guardar embarque</button>
          </div>
        </div>
      </form>

      <section class="tarjeta">
        <div class="tabla-contenedor">
          <table>
            <thead><tr><th>Destino</th><th>Barco</th><th>Sale</th><th>Documentos hasta</th><th class="derecha">Flete</th><th>Lugares libres</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($embarques as $d) {
                if ($d['pedible']) { $b = badge('Con lugar', 'estado-ok'); }
                elseif ($d['estado'] !== 'abierta') { $b = badge('Cerrado', 'estado-neutro'); }
                elseif ($d['motivo'] === 'Completo') { $b = badge('Completo', 'estado-alerta'); }
                else { $b = badge('Venció el plazo', 'estado-neutro'); } ?>
              <tr>
                <td><strong><?= e($d['pod']) ?></strong><div class="chico suave"><?= e($d['tipo_contenedor']) ?></div></td>
                <td><?= e($d['buque']) ?><div class="chico suave"><?= e($d['naviera']) ?> · <?= $d['via'] === 'directa' ? 'directa' : 'con transbordo' ?></div></td>
                <td class="num"><?= e(f_corta($d['etd'])) ?></td>
                <td class="num"><?= e(f_hora($d['cutoff_doc'])) ?></td>
                <td class="num derecha"><?= e(dinero($d['flete'])) ?></td>
                <td class="num"><strong><?= (int) $d['libres'] ?></strong> de <?= (int) $d['cupo'] ?><?= $d['pendientes'] ? '<div class="chico suave">' . (int) $d['pendientes'] . ' pedido' . ($d['pendientes'] == 1 ? '' : 's') . ' por responder</div>' : '' ?></td>
                <td><?= $b ?></td>
                <td>
                  <form action="disponibilidad.php" method="post" style="display:inline"><?= csrf_campo() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                    <button class="boton boton-secundario boton-chico"><?= $d['estado'] === 'abierta' ? 'Cerrar' : 'Abrir' ?></button></form>
                </td>
              </tr>
            <?php } if (!$embarques) { ?>
              <tr><td colspan="8" class="suave">Todavía no hay embarques cargados.</td></tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
      </section>
      <p class="ayuda" style="margin-top:10px">Los pedidos pendientes y aceptados ocupan cupo; si un pedido se rechaza o el cliente lo cancela, el lugar vuelve a quedar libre.</p>
<?php pie_personal(); ?>
