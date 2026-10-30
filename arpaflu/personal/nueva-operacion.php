<?php
// P3 · Registrar reserva: se crea al solicitar el espacio, aunque todavía no tenga booking (RF-06)
require_once __DIR__ . '/../partes/layout.php';
$u = requerir_empleado('crear_operaciones');

$tipos_cont = ["20' DV", "40' DV", "40' HC", "40' RF"];
$v = [ // valores del formulario (se conservan si hay errores)
    'cliente_id' => '', 'referencia_cliente' => '', 'cotizacion_id' => '', 'responsable_id' => '', 'naviera_id' => '', 'buque' => '',
    'booking' => '', 'semana' => (int) date('W'), 'pol' => 'Buenos Aires', 'pod' => '', 'via' => 'directa', 'terminal_id' => '',
    'cantidad' => 1, 'tipo_contenedor' => "40' HC", 'dias_libres' => '', 'etd' => '', 'eta' => '', 'cutoff_documental' => '',
    'cutoff_fisico' => '', 'observaciones' => '',
];
$dest_marcados = null;
if (!es_post() && isset($_GET['cliente_id'])) {   // viene del aviso de una selección de alternativa
    $v['cliente_id'] = (int) $_GET['cliente_id'];
}

if (es_post()) {
    csrf_verificar();
    foreach ($v as $k => $_) {
        if (isset($_POST[$k])) {
            $v[$k] = trim($_POST[$k]);
        }
    }
    $dest_marcados = isset($_POST['destinatarios']) && is_array($_POST['destinatarios']) ? array_map('intval', $_POST['destinatarios']) : [];
    $errores = [];

    $cliente_id = (int) $v['cliente_id'];
    $cliente = $cliente_id ? fila('SELECT * FROM clientes WHERE id = ? AND activo = 1', [$cliente_id]) : null;
    if (!$cliente) {
        $errores[] = 'Elegí un cliente.';
    } elseif (!puede_actuar_cliente($cliente_id)) {
        $errores[] = 'No tenés autorización para cargar operaciones de ' . $cliente['empresa'] . '.';
    }
    if (!(int) $v['naviera_id']) { $errores[] = 'Elegí una naviera.'; }
    if ($v['pod'] === '') { $errores[] = 'Indicá el puerto de destino.'; }
    if ((int) $v['cantidad'] < 1) { $errores[] = 'La cantidad de contenedores debe ser 1 o más.'; }
    $etd = parse_fecha($v['etd']);
    $eta = parse_fecha($v['eta']);
    $cutdoc = parse_fecha($v['cutoff_documental'], true);
    $cutfis = parse_fecha($v['cutoff_fisico'], true);
    foreach (['ETD' => $etd, 'ETA' => $eta, 'cut-off documental' => $cutdoc, 'cut-off físico' => $cutfis] as $n => $x) {
        if ($x === false) { $errores[] = 'La fecha de ' . $n . ' no se entiende (usá dd/mm/aaaa o dd/mm/aaaa hh:mm).'; }
    }
    $cot_id = (int) $v['cotizacion_id'];
    if ($cot_id && $cliente && !valor('SELECT id FROM cotizaciones WHERE id = ? AND cliente_id = ?', [$cot_id, $cliente_id])) {
        $errores[] = 'La cotización elegida no corresponde al cliente.';
    }

    if (!$errores) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $n = (int) valor("SELECT COALESCE(MAX(CAST(SUBSTRING(referencia, 5) AS UNSIGNED)), 0) + 1 FROM operaciones FOR UPDATE");
            $ref = 'ARP-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
            $resp = (int) $v['responsable_id'] ?: ($cliente['responsable_id'] ?: $u['id']);
            $con_booking = $v['booking'] !== '';
            ejecutar(
                'INSERT INTO operaciones (referencia, cliente_id, referencia_cliente, cotizacion_id, responsable_id, naviera_id, buque, booking, semana,
                    pol, pod, via, terminal_id, cantidad, tipo_contenedor, dias_libres, etd, eta, cutoff_doc, cutoff_fisico, booking_confirmado_en,
                    estado, observaciones, creado_por)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,' . ($con_booking ? 'NOW()' : 'NULL') . ',?,?,?)',
                [
                    $ref, $cliente_id, $v['referencia_cliente'] ?: null, $cot_id ?: null, $resp, (int) $v['naviera_id'], $v['buque'] ?: null,
                    $v['booking'] ?: null, (int) $v['semana'] ?: null, $v['pol'] ?: null, $v['pod'], $v['via'] === 'transbordo' ? 'transbordo' : 'directa',
                    (int) $v['terminal_id'] ?: null, (int) $v['cantidad'], $v['tipo_contenedor'] ?: null, $v['dias_libres'] !== '' ? (int) $v['dias_libres'] : null,
                    $etd, $eta, $cutdoc, $cutfis, $con_booking ? 'booking_confirmado' : 'reserva_solicitada', $v['observaciones'] ?: null, $u['id'],
                ]
            );
            $op_id = (int) $pdo->lastInsertId();

            // Destinatarios de avisos de esta operación (si no se marcó ninguno, se usan el contacto y el despachante del cliente)
            $ids = $dest_marcados;
            if (!$ids) {
                foreach (consulta("SELECT id FROM participantes WHERE cliente_id = ? AND activo = 1 AND rol IN ('contacto','despachante')", [$cliente_id]) as $r) {
                    $ids[] = (int) $r['id'];
                }
            }
            foreach ($ids as $pid) {
                ejecutar('INSERT IGNORE INTO operacion_destinatarios (operacion_id, participante_id)
                          SELECT ?, id FROM participantes WHERE id = ? AND cliente_id = ?', [$op_id, $pid, $cliente_id]);
            }
            $pdo->commit();
        } catch (Exception $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        $op = fila('SELECT * FROM operaciones WHERE id = ?', [$op_id]);
        if ((int) $op['responsable_id'] !== (int) $u['id']) {
            aviso_interno($op, 'Nueva operación asignada', 'otro', $ref . ' · Nueva operación asignada',
                $u['nombre'] . ' creó la operación ' . $ref . ' de ' . $cliente['empresa'] . ' y te asignó como responsable.');
        }
        flash('ok', 'Operación ' . $ref . ' creada' . ($con_booking ? ' con booking confirmado.' : '. Cuando la naviera confirme, cargá el booking desde la ficha.'));
        redirigir('operacion.php?id=' . $op_id);
    }
    foreach ($errores as $er) { flash('error', $er); }
}

$clientes = consulta('SELECT id, empresa, responsable_id FROM clientes WHERE activo = 1 ORDER BY empresa');
$navieras = consulta('SELECT id, nombre FROM navieras ORDER BY nombre');
$terminales = consulta('SELECT id, nombre FROM terminales ORDER BY nombre');
$origenes = consulta('SELECT nombre FROM puertos WHERE es_origen = 1 ORDER BY nombre');
$destinos = consulta('SELECT nombre FROM puertos WHERE es_origen = 0 ORDER BY nombre');
$buques = consulta('SELECT nombre FROM buques ORDER BY nombre');
$responsables = consulta("SELECT id, nombre FROM usuarios WHERE tipo = 'empleado' AND activo = 1 AND rol IN ('administrador','operaciones') ORDER BY nombre");
$cotizaciones = consulta(
    "SELECT q.id, q.codigo, q.version, q.cliente_id, q.pod, c.empresa FROM cotizaciones q JOIN clientes c ON c.id = q.cliente_id
     WHERE q.estado IN ('enviada','aceptada') ORDER BY q.codigo DESC, q.version DESC"
);
$participantes = consulta('SELECT id, cliente_id, nombre, rol FROM participantes WHERE activo = 1 ORDER BY cliente_id, rol, nombre');
$etiq_rol = ['contacto' => 'contacto principal', 'despachante' => 'despachante', 'otro' => 'otro contacto'];

cabecera_personal('Nueva reserva', 'operaciones');
?>
      <div class="encabezado-pagina">
        <div>
          <div class="migas"><a href="operaciones.php">Reservas</a> / Nueva</div>
          <h1>Nueva reserva</h1>
          <p>Se crea al solicitar el espacio a la naviera, aunque todavía no tenga booking. Los datos que falten se completan después.</p>
        </div>
      </div>

      <form class="formulario" action="nueva-operacion.php" method="post" id="form-operacion">
        <?= csrf_campo() ?>
        <fieldset>
          <legend>Cliente y cotización</legend>
          <div class="campos">
            <div class="campo">
              <label for="cliente">Cliente</label>
              <select id="cliente" name="cliente_id" required>
                <option value="">Elegí un cliente</option>
                <?php foreach ($clientes as $c) { ?>
                  <option value="<?= (int) $c['id'] ?>" data-resp="<?= (int) $c['responsable_id'] ?>"<?= (string) $v['cliente_id'] === (string) $c['id'] ? ' selected' : '' ?>><?= e($c['empresa']) ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="campo">
              <label for="ref-cliente">Referencia del cliente</label>
              <input type="text" id="ref-cliente" name="referencia_cliente" maxlength="60" value="<?= e($v['referencia_cliente']) ?>" placeholder="OLG-EXP-1190">
            </div>
            <div class="campo">
              <label for="cotizacion">Cotización vinculada</label>
              <select id="cotizacion" name="cotizacion_id">
                <option value="">Sin cotización</option>
                <?php foreach ($cotizaciones as $q) { ?>
                  <option value="<?= (int) $q['id'] ?>" data-cliente="<?= (int) $q['cliente_id'] ?>"<?= (string) $v['cotizacion_id'] === (string) $q['id'] ? ' selected' : '' ?>><?= e($q['codigo'] . ' v' . $q['version'] . ' · ' . $q['empresa'] . ' · ' . $q['pod']) ?></option>
                <?php } ?>
              </select>
            </div>
            <div class="campo">
              <label for="responsable">Empleado responsable</label>
              <select id="responsable" name="responsable_id">
                <?php foreach ($responsables as $r) { ?>
                  <option value="<?= (int) $r['id'] ?>"<?= (string) $v['responsable_id'] === (string) $r['id'] ? ' selected' : '' ?>><?= e($r['nombre']) ?></option>
                <?php } ?>
              </select>
            </div>
          </div>
        </fieldset>

        <fieldset>
          <legend>Transporte</legend>
          <div class="campos">
            <div class="campo">
              <label for="naviera">Naviera</label>
              <select id="naviera" name="naviera_id" required>
                <option value="">Elegí una naviera</option>
                <?php foreach ($navieras as $n) { ?><option value="<?= (int) $n['id'] ?>"<?= (string) $v['naviera_id'] === (string) $n['id'] ? ' selected' : '' ?>><?= e($n['nombre']) ?></option><?php } ?>
              </select>
            </div>
            <div class="campo">
              <label for="buque">Barco y viaje</label>
              <input type="text" id="buque" name="buque" maxlength="120" value="<?= e($v['buque']) ?>" list="lista-buques" placeholder="A confirmar">
              <datalist id="lista-buques"><?php foreach ($buques as $b) { ?><option value="<?= e($b['nombre']) ?>"><?php } ?></datalist>
            </div>
            <div class="campo">
              <label for="booking">Booking</label>
              <input type="text" id="booking" name="booking" maxlength="40" value="<?= e($v['booking']) ?>" placeholder="Se carga al confirmar">
            </div>
            <div class="campo">
              <label for="semana">Semana</label>
              <input type="number" id="semana" name="semana" min="1" max="53" value="<?= e($v['semana']) ?>">
            </div>
            <div class="campo">
              <label for="pol">Puerto de carga (POL)</label>
              <select id="pol" name="pol">
                <?php foreach ($origenes as $o) { ?><option<?= $v['pol'] === $o['nombre'] ? ' selected' : '' ?>><?= e($o['nombre']) ?></option><?php } ?>
              </select>
            </div>
            <div class="campo">
              <label for="pod">Puerto de destino (POD)</label>
              <input type="text" id="pod" name="pod" maxlength="80" value="<?= e($v['pod']) ?>" list="puertos" required>
              <datalist id="puertos"><?php foreach ($destinos as $d) { ?><option value="<?= e($d['nombre']) ?>"><?php } ?></datalist>
            </div>
            <div class="campo">
              <label for="via">Vía</label>
              <select id="via" name="via">
                <option value="directa"<?= $v['via'] === 'directa' ? ' selected' : '' ?>>Directa</option>
                <option value="transbordo"<?= $v['via'] === 'transbordo' ? ' selected' : '' ?>>Con transbordo</option>
              </select>
            </div>
            <div class="campo">
              <label for="terminal">Terminal</label>
              <select id="terminal" name="terminal_id">
                <option value="">A confirmar</option>
                <?php foreach ($terminales as $t) { ?><option value="<?= (int) $t['id'] ?>"<?= (string) $v['terminal_id'] === (string) $t['id'] ? ' selected' : '' ?>><?= e($t['nombre']) ?></option><?php } ?>
              </select>
            </div>
          </div>
        </fieldset>

        <fieldset>
          <legend>Contenedores</legend>
          <div class="campos">
            <div class="campo">
              <label for="cantidad">Cantidad</label>
              <input type="number" id="cantidad" name="cantidad" min="1" value="<?= e($v['cantidad']) ?>" required>
            </div>
            <div class="campo">
              <label for="tipo">Tipo</label>
              <select id="tipo" name="tipo_contenedor">
                <?php foreach ($tipos_cont as $t) { ?><option<?= $v['tipo_contenedor'] === $t ? ' selected' : '' ?>><?= e($t) ?></option><?php } ?>
              </select>
            </div>
            <div class="campo">
              <label for="dias-libres">Días libres en destino</label>
              <input type="number" id="dias-libres" name="dias_libres" min="0" value="<?= e($v['dias_libres']) ?>">
            </div>
          </div>
        </fieldset>

        <fieldset>
          <legend>Fechas</legend>
          <div class="campos">
            <div class="campo">
              <label for="etd">Salida estimada (<?= termino_p('ETD') ?>)</label>
              <input type="text" id="etd" name="etd" value="<?= e($v['etd']) ?>" placeholder="dd/mm/aaaa">
            </div>
            <div class="campo">
              <label for="eta">Llegada estimada (<?= termino_p('ETA') ?>)</label>
              <input type="text" id="eta" name="eta" value="<?= e($v['eta']) ?>" placeholder="dd/mm/aaaa">
            </div>
            <div class="campo">
              <label for="cutoff-doc">Límite para los documentos (<?= termino_p('Cut-off documental', 'cut-off') ?>)</label>
              <input type="text" id="cutoff-doc" name="cutoff_documental" value="<?= e($v['cutoff_documental']) ?>" placeholder="dd/mm/aaaa hh:mm">
            </div>
            <div class="campo">
              <label for="cutoff-fis">Límite para llevar la carga (<?= termino_p('Cut-off físico', 'cut-off físico') ?>)</label>
              <input type="text" id="cutoff-fis" name="cutoff_fisico" value="<?= e($v['cutoff_fisico']) ?>" placeholder="dd/mm/aaaa hh:mm">
            </div>
          </div>
          <p class="ayuda" style="margin:10px 0 0">El sistema envía recordatorios antes de cada cut-off (control de vencimientos).</p>
        </fieldset>

        <fieldset>
          <legend>Destinatarios de avisos</legend>
          <p class="ayuda">Se proponen los contactos configurados para el cliente elegido. Podés quitar o agregar destinatarios para esta operación. El empleado responsable siempre recibe los avisos internos.</p>
          <div class="campos">
            <div class="campo campo-ancho" id="destinatarios">
              <p class="ayuda" id="dest-vacio">Elegí un cliente para ver sus contactos.</p>
              <?php foreach ($participantes as $p) {
                  $marcado = $dest_marcados !== null ? in_array((int) $p['id'], $dest_marcados, true) : $p['rol'] !== 'otro'; ?>
                <label class="casilla" data-cliente="<?= (int) $p['cliente_id'] ?>" hidden>
                  <input type="checkbox" name="destinatarios[]" value="<?= (int) $p['id'] ?>"<?= $marcado ? ' checked' : '' ?>>
                  <?= e($p['nombre']) ?> · <?= e($etiq_rol[$p['rol']]) ?>
                </label>
              <?php } ?>
            </div>
            <div class="campo campo-ancho">
              <label for="obs">Observaciones</label>
              <textarea id="obs" name="observaciones"><?= e($v['observaciones']) ?></textarea>
            </div>
          </div>
        </fieldset>

        <div class="pie-formulario">
          <a href="operaciones.php" class="boton boton-secundario">Cancelar</a>
          <button type="submit" class="boton boton-primario">Crear operación</button>
        </div>
      </form>

<script>
// Muestra solo los contactos y cotizaciones del cliente elegido y propone su responsable
(function () {
  var cli = document.getElementById('cliente');
  function actualizar() {
    var id = cli.value, hay = false;
    document.querySelectorAll('#destinatarios label[data-cliente]').forEach(function (l) {
      var mostrar = id !== '' && l.dataset.cliente === id;
      l.hidden = !mostrar;
      l.querySelector('input').disabled = !mostrar;
      if (mostrar) hay = true;
    });
    document.getElementById('dest-vacio').hidden = hay;
    document.querySelectorAll('#cotizacion option[data-cliente]').forEach(function (o) {
      o.hidden = o.dataset.cliente !== id;
      if (o.hidden && o.selected) o.parentNode.value = '';
    });
    var sel = cli.options[cli.selectedIndex];
    if (sel && sel.dataset.resp && sel.dataset.resp !== '0' && !window.__respTocado) {
      document.getElementById('responsable').value = sel.dataset.resp;
    }
  }
  document.getElementById('responsable').addEventListener('change', function () { window.__respTocado = true; });
  cli.addEventListener('change', actualizar);
  actualizar();
})();
</script>
<?php pie_personal(); ?>
