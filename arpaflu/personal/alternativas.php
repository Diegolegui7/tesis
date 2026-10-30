<?php
// SS2 · Alternativas confirmadas para los clientes que eligen entre varias reservas (caso OLEGA, RF-09)
// ARPAFLU carga las alternativas ya confirmadas; el cliente elige una desde su portal (cliente/alternativas.php).
require_once __DIR__ . '/../partes/layout.php';
$u = requerir_empleado('crear_operaciones');

if (es_post()) {
    csrf_verificar();
    $cliente_id = (int) (isset($_POST['cliente_id']) ? $_POST['cliente_id'] : 0);
    $c = fila('SELECT * FROM clientes WHERE id = ? AND activo = 1 AND selecciona_alternativas = 1', [$cliente_id]);
    $grupo = trim(isset($_POST['grupo']) ? $_POST['grupo'] : '');
    $naviera = (int) (isset($_POST['naviera_id']) ? $_POST['naviera_id'] : 0);
    $buque = trim(isset($_POST['buque']) ? $_POST['buque'] : '');
    $via = trim(isset($_POST['via']) ? $_POST['via'] : '');
    $etd = parse_fecha(isset($_POST['etd']) ? $_POST['etd'] : '');
    $eta = parse_fecha(isset($_POST['eta']) ? $_POST['eta'] : '');
    $cut = parse_fecha(isset($_POST['cutoff_doc']) ? $_POST['cutoff_doc'] : '', true);
    $flete = isset($_POST['flete']) ? $_POST['flete'] : '';
    $libres = trim(isset($_POST['dias_libres']) ? $_POST['dias_libres'] : '');
    $errores = [];
    if (!$c) { $errores[] = 'Elegí un cliente que seleccione entre alternativas.'; }
    elseif (!puede_actuar_cliente($cliente_id)) { $errores[] = 'No tenés autorización para cargar alternativas de ' . $c['empresa'] . '.'; }
    if ($grupo === '') { $errores[] = 'Indicá el nombre del grupo (por ejemplo: Próximo embarque · Buenos Aires → Rotterdam).'; }
    if (!$naviera) { $errores[] = 'Elegí una naviera.'; }
    if ($buque === '') { $errores[] = 'Indicá el buque y viaje.'; }
    if ($flete === '' || !is_numeric($flete) || $flete < 0) { $errores[] = 'Indicá el flete (un número).'; }
    foreach (['ETD' => $etd, 'ETA' => $eta, 'cut-off documental' => $cut] as $n => $x) {
        if ($x === false) { $errores[] = 'La fecha de ' . $n . ' no se entiende (usá dd/mm/aaaa o dd/mm/aaaa hh:mm).'; }
    }
    if ($libres !== '' && !ctype_digit($libres)) { $errores[] = 'Los días libres deben ser un número entero.'; }
    if ($errores) {
        foreach ($errores as $er) { flash('error', $er); }
        redirigir('alternativas.php');
    }
    // la etiqueta (A, B, C…) sigue la serie del grupo; si el grupo ya tiene una elegida o todas resueltas, la nueva se suma igual
    $n = (int) valor('SELECT COUNT(*) FROM alternativas WHERE cliente_id = ? AND grupo = ?', [$cliente_id, $grupo]);
    $etiqueta = chr(ord('A') + min($n, 25));
    $carga = trim(isset($_POST['carga']) ? $_POST['carga'] : '');
    ejecutar(
        'INSERT INTO alternativas (cliente_id, grupo, carga, etiqueta, naviera_id, buque, via, etd, eta, cutoff_doc, flete, dias_libres, estado)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,"disponible")',
        [$cliente_id, $grupo, $carga !== '' ? $carga : null, $etiqueta, $naviera, $buque, $via !== '' ? $via : 'Directa', $etd, $eta, $cut, $flete, $libres !== '' ? (int) $libres : null]
    );
    flash('ok', 'Alternativa ' . $etiqueta . ' cargada en “' . $grupo . '”. ' . $c['empresa'] . ' ya puede elegirla desde su portal.');
    redirigir('alternativas.php');
}

$alts = consulta(
    "SELECT a.*, c.empresa, n.nombre AS naviera, e.nombre AS eligio, o.referencia
     FROM alternativas a JOIN clientes c ON c.id = a.cliente_id LEFT JOIN navieras n ON n.id = a.naviera_id
     LEFT JOIN usuarios e ON e.id = a.elegida_por LEFT JOIN operaciones o ON o.id = a.operacion_id
     ORDER BY a.creado DESC, a.cliente_id, a.grupo, a.etiqueta"
);
$grupos = [];
foreach ($alts as $a) { $grupos[$a['empresa'] . '|' . $a['grupo']][] = $a; }
$clientes = consulta('SELECT id, empresa FROM clientes WHERE activo = 1 AND selecciona_alternativas = 1 ORDER BY empresa');
$navieras = consulta('SELECT id, nombre FROM navieras ORDER BY nombre');
$txt = ['disponible' => ['Disponible', 'estado-info'], 'elegida' => ['Elegida', 'estado-ok'], 'no_elegida' => ['No elegida', 'estado-neutro']];

cabecera_personal('Alternativas', 'alternativas');
?>
      <div class="encabezado-pagina">
        <div>
          <h1>Alternativas para elegir</h1>
          <p>Reservas ya confirmadas por las navieras que ARPAFLU ofrece a los clientes que eligen entre varias (hoy, OLEGA).</p>
        </div>
        <div class="acciones">
          <button type="button" class="boton boton-primario" data-alternar="form-alternativa">+ Cargar alternativa</button>
        </div>
      </div>

      <form id="form-alternativa" class="tarjeta" action="alternativas.php" method="post" hidden style="margin-bottom:18px">
        <?= csrf_campo() ?>
        <div class="tarjeta-cabecera"><h2>Cargar alternativa</h2></div>
        <div class="tarjeta-cuerpo formulario">
          <div class="campos">
            <div class="campo">
              <label for="alt-cliente">Cliente</label>
              <select id="alt-cliente" name="cliente_id" required>
                <option value="">Elegí un cliente</option>
                <?php foreach ($clientes as $c) { ?><option value="<?= (int) $c['id'] ?>"><?= e($c['empresa']) ?></option><?php } ?>
              </select>
            </div>
            <div class="campo">
              <label for="alt-grupo">Grupo (mismo texto para todas las alternativas del embarque)</label>
              <input type="text" id="alt-grupo" name="grupo" maxlength="80" placeholder="Próximo embarque · Buenos Aires → Rotterdam" required>
            </div>
            <div class="campo">
              <label for="alt-carga">Carga</label>
              <input type="text" id="alt-carga" name="carga" maxlength="60" placeholder="2 × 40' HC">
            </div>
            <div class="campo">
              <label for="alt-naviera">Naviera</label>
              <select id="alt-naviera" name="naviera_id" required>
                <?php foreach ($navieras as $n) { ?><option value="<?= (int) $n['id'] ?>"><?= e($n['nombre']) ?></option><?php } ?>
              </select>
            </div>
            <div class="campo">
              <label for="alt-buque">Buque y viaje</label>
              <input type="text" id="alt-buque" name="buque" maxlength="120" required>
            </div>
            <div class="campo">
              <label for="alt-via">Vía</label>
              <input type="text" id="alt-via" name="via" maxlength="60" placeholder="Directa">
            </div>
            <div class="campo">
              <label for="alt-etd">ETD</label>
              <input type="text" id="alt-etd" name="etd" placeholder="dd/mm/aaaa">
            </div>
            <div class="campo">
              <label for="alt-eta">ETA</label>
              <input type="text" id="alt-eta" name="eta" placeholder="dd/mm/aaaa">
            </div>
            <div class="campo">
              <label for="alt-cut">Cut-off documental</label>
              <input type="text" id="alt-cut" name="cutoff_doc" placeholder="dd/mm/aaaa hh:mm">
            </div>
            <div class="campo">
              <label for="alt-flete">Flete (US$ por contenedor)</label>
              <input type="number" id="alt-flete" name="flete" min="0" step="0.01" required>
            </div>
            <div class="campo">
              <label for="alt-libres">Días libres en destino</label>
              <input type="number" id="alt-libres" name="dias_libres" min="0" step="1">
            </div>
          </div>
          <div class="pie-formulario">
            <button type="button" class="boton boton-secundario" data-alternar="form-alternativa">Cancelar</button>
            <button type="submit" class="boton boton-primario">Guardar alternativa</button>
          </div>
        </div>
      </form>

      <?php foreach ($grupos as $clave => $lista) { $p = $lista[0]; $elegida = null; foreach ($lista as $x) { if ($x['estado'] === 'elegida') { $elegida = $x; } } ?>
      <section class="tarjeta" style="margin-bottom:18px">
        <div class="tarjeta-cabecera">
          <h2><?= e($p['empresa']) ?> · <?= e($p['grupo']) ?></h2>
          <?php if ($elegida && !$elegida['referencia']) { ?>
            <a class="boton boton-secundario boton-chico" href="nueva-operacion.php?cliente_id=<?= (int) $p['cliente_id'] ?>">Crear la operación con la elegida</a>
          <?php } ?>
        </div>
        <div class="tabla-contenedor">
          <table>
            <thead><tr><th>Alt.</th><th>Naviera</th><th>Buque y vía</th><th>ETD</th><th>Cut-off doc.</th><th class="derecha">Flete / cont.</th><th>Días libres</th><th>Estado</th><th>Elección</th></tr></thead>
            <tbody>
            <?php foreach ($lista as $a) { ?>
              <tr>
                <td><strong><?= e($a['etiqueta']) ?></strong></td>
                <td><?= e($a['naviera']) ?></td>
                <td><?= e($a['buque']) ?><div class="chico suave"><?= e($a['via']) ?></div></td>
                <td class="num"><?= e(f_corta($a['etd'])) ?></td>
                <td class="num"><?= e(f_hora($a['cutoff_doc'])) ?></td>
                <td class="num derecha"><?= e(dinero($a['flete'])) ?></td>
                <td class="num"><?= $a['dias_libres'] !== null ? (int) $a['dias_libres'] : '—' ?></td>
                <td><?= badge($txt[$a['estado']][0], $txt[$a['estado']][1]) ?></td>
                <td><?= $a['estado'] === 'elegida' ? e($a['eligio']) . '<div class="chico suave">' . e(f_completa($a['elegida_en'])) . '</div>' : '<span class="suave">—</span>' ?></td>
              </tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php } if (!$grupos) { ?>
      <section class="tarjeta"><div class="tarjeta-cuerpo"><p class="suave">Todavía no se cargaron alternativas.</p></div></section>
      <?php } ?>
<?php pie_personal(); ?>
