<?php
// SS2 · P2 Registrar cotización (con versiones y versión aceptada)
require_once __DIR__ . '/../partes/layout.php';
$u = requerir_empleado('cotizaciones');
$ver_costos = puede('costos_internos');

if (es_post()) {
    csrf_verificar();
    $accion = isset($_POST['accion']) ? $_POST['accion'] : 'crear';

    if ($accion === 'estado') {
        $cot = fila('SELECT * FROM cotizaciones WHERE id = ?', [(int) (isset($_POST['id']) ? $_POST['id'] : 0)]);
        $nuevo = isset($_POST['estado']) ? $_POST['estado'] : '';
        if (!$cot || !in_array($nuevo, ['aceptada', 'rechazada'], true)) {
            flash('error', 'Solicitud no válida.');
        } elseif (!exigir_actuar_cliente($cot['cliente_id'])) {
            // mensaje ya cargado
        } else {
            ejecutar('UPDATE cotizaciones SET estado = ? WHERE id = ?', [$nuevo, $cot['id']]);
            if ($nuevo === 'aceptada') { // la aceptada reemplaza a las otras versiones que seguían abiertas
                ejecutar("UPDATE cotizaciones SET estado = 'reemplazada' WHERE codigo = ? AND id <> ? AND estado = 'enviada'", [$cot['codigo'], $cot['id']]);
            }
            flash('ok', $cot['codigo'] . ' v' . $cot['version'] . ' marcada como ' . $nuevo . '.');
        }
        redirigir('cotizaciones.php');
    }

    // Crear
    $cliente_id = (int) (isset($_POST['cliente_id']) ? $_POST['cliente_id'] : 0);
    $pod = trim(isset($_POST['pod']) ? $_POST['pod'] : '');
    $flete = isset($_POST['flete']) ? $_POST['flete'] : '';
    $vigencia = parse_fecha(isset($_POST['vigencia']) ? $_POST['vigencia'] : '');
    $errores = [];
    if (!$cliente_id || !fila('SELECT id FROM clientes WHERE id = ? AND activo = 1', [$cliente_id])) { $errores[] = 'Elegí un cliente.'; }
    elseif (!puede_actuar_cliente($cliente_id)) { $errores[] = 'No tenés autorización para cotizar a ese cliente.'; }
    if ($pod === '') { $errores[] = 'Indicá el destino.'; }
    if ($flete === '' || !is_numeric($flete) || $flete < 0) { $errores[] = 'Indicá el flete ofrecido (un número).'; }
    if ($vigencia === false) { $errores[] = 'La vigencia no se entiende (usá dd/mm/aaaa).'; }
    $costo = isset($_POST['costo_interno']) && $_POST['costo_interno'] !== '' ? $_POST['costo_interno'] : null;
    if ($costo !== null && (!is_numeric($costo) || $costo < 0)) { $errores[] = 'El costo interno debe ser un número.'; }
    if (!$ver_costos) { $costo = null; }

    $archivo = subir_archivo('archivo', 'cotizaciones');
    if (is_string($archivo)) { $errores[] = $archivo; }

    if ($errores) {
        foreach ($errores as $er) { flash('error', $er); }
        redirigir('cotizaciones.php');
    }

    // ¿Nueva versión de una cotización existente?
    $base = isset($_POST['nueva_version_de']) ? trim($_POST['nueva_version_de']) : '';
    if ($base !== '' && fila('SELECT id FROM cotizaciones WHERE codigo = ? AND cliente_id = ?', [$base, $cliente_id])) {
        $codigo = $base;
        $version = 1 + (int) valor('SELECT MAX(version) FROM cotizaciones WHERE codigo = ?', [$codigo]);
        ejecutar("UPDATE cotizaciones SET estado = 'reemplazada' WHERE codigo = ? AND estado = 'enviada'", [$codigo]);
    } else {
        $n = 1 + (int) valor("SELECT COALESCE(MAX(CAST(SUBSTRING(codigo, 5) AS UNSIGNED)), 0) FROM cotizaciones");
        $codigo = 'COT-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
        $version = 1;
    }
    ejecutar(
        'INSERT INTO cotizaciones (codigo, version, cliente_id, naviera_id, pod, contenedores, flete, costo_interno, condiciones, vigencia, archivo_nombre, archivo_ruta, creado_por)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$codigo, $version, $cliente_id, (int) (isset($_POST['naviera_id']) ? $_POST['naviera_id'] : 0) ?: null, $pod, trim(isset($_POST['contenedores']) ? $_POST['contenedores'] : '') ?: null,
         $flete, $costo, trim(isset($_POST['condiciones']) ? $_POST['condiciones'] : '') ?: null, $vigencia, $archivo ? $archivo[0] : null, $archivo ? $archivo[1] : null, $u['id']]
    );
    flash('ok', 'Cotización ' . $codigo . ' v' . $version . ' registrada.');
    redirigir('cotizaciones.php');
}

$q = trim(isset($_GET['q']) ? $_GET['q'] : '');
$est = isset($_GET['estado']) ? $_GET['estado'] : '';
$donde = [];
$p = [];
if ($q !== '') {
    $donde[] = '(q.codigo LIKE ? OR c.empresa LIKE ? OR q.pod LIKE ?)';
    $l = '%' . $q . '%';
    array_push($p, $l, $l, $l);
}
$cots = consulta(
    "SELECT q.*, c.empresa, n.nombre AS naviera, o.id AS op_id, o.referencia,
            IF(q.estado = 'enviada' AND q.vigencia IS NOT NULL AND q.vigencia < CURDATE(), 'vencida', q.estado) AS estado_visible
     FROM cotizaciones q JOIN clientes c ON c.id = q.cliente_id LEFT JOIN navieras n ON n.id = q.naviera_id LEFT JOIN operaciones o ON o.cotizacion_id = q.id"
    . ($donde ? ' WHERE ' . implode(' AND ', $donde) : '') . ' ORDER BY q.codigo DESC, q.version DESC',
    $p
);
if ($est !== '') {
    $cots = array_values(array_filter($cots, function ($c) use ($est) { return $c['estado_visible'] === $est; }));
}
$clientes = consulta('SELECT id, empresa FROM clientes WHERE activo = 1 ORDER BY empresa');
$navieras = consulta('SELECT id, nombre FROM navieras ORDER BY nombre');
$codigos = consulta('SELECT DISTINCT q.codigo, c.empresa FROM cotizaciones q JOIN clientes c ON c.id = q.cliente_id ORDER BY q.codigo DESC');
$destinos = consulta('SELECT nombre FROM puertos WHERE es_origen = 0 ORDER BY nombre');
$cls = ['aceptada' => 'estado-ok', 'enviada' => 'estado-info', 'rechazada' => 'estado-peligro', 'vencida' => 'estado-neutro', 'reemplazada' => 'estado-neutro'];
$txt = ['aceptada' => 'Aceptada', 'enviada' => 'Enviada', 'rechazada' => 'Rechazada', 'vencida' => 'Vencida', 'reemplazada' => 'Reemplazada'];

cabecera_personal('Cotizaciones', 'cotizaciones');
?>
      <div class="encabezado-pagina">
        <div>
          <h1>Cotizaciones</h1>
          <p>Ofertas enviadas a los clientes, con sus versiones y la versión aceptada.</p>
        </div>
        <div class="acciones">
          <button type="button" class="boton boton-primario" data-alternar="form-cotizacion">+ Registrar cotización</button>
        </div>
      </div>

      <form id="form-cotizacion" class="tarjeta" action="cotizaciones.php" method="post" enctype="multipart/form-data" hidden style="margin-bottom:18px">
        <?= csrf_campo() ?><input type="hidden" name="accion" value="crear">
        <div class="tarjeta-cabecera"><h2>Registrar cotización</h2></div>
        <div class="tarjeta-cuerpo formulario">
          <div class="campos">
            <div class="campo">
              <label for="cot-cliente">Cliente</label>
              <select id="cot-cliente" name="cliente_id" required>
                <option value="">Elegí un cliente</option>
                <?php foreach ($clientes as $c) { ?><option value="<?= (int) $c['id'] ?>"><?= e($c['empresa']) ?></option><?php } ?>
              </select>
            </div>
            <div class="campo">
              <label for="cot-base">Es una nueva versión de</label>
              <select id="cot-base" name="nueva_version_de">
                <option value="">— Cotización nueva —</option>
                <?php foreach ($codigos as $c) { ?><option value="<?= e($c['codigo']) ?>"><?= e($c['codigo'] . ' · ' . $c['empresa']) ?></option><?php } ?>
              </select>
            </div>
            <div class="campo">
              <label for="cot-naviera">Naviera</label>
              <select id="cot-naviera" name="naviera_id">
                <?php foreach ($navieras as $n) { ?><option value="<?= (int) $n['id'] ?>"><?= e($n['nombre']) ?></option><?php } ?>
              </select>
            </div>
            <div class="campo">
              <label for="cot-pod">Destino (POD)</label>
              <input type="text" id="cot-pod" name="pod" maxlength="80" list="cot-puertos" required>
              <datalist id="cot-puertos"><?php foreach ($destinos as $d) { ?><option value="<?= e($d['nombre']) ?>"><?php } ?></datalist>
            </div>
            <div class="campo">
              <label for="cot-cont">Contenedores</label>
              <input type="text" id="cot-cont" name="contenedores" maxlength="60" placeholder="2 × 40' HC">
            </div>
            <div class="campo">
              <label for="cot-flete">Flete ofrecido (US$ por contenedor)</label>
              <input type="number" id="cot-flete" name="flete" min="0" step="0.01" required>
            </div>
            <?php if ($ver_costos) { ?>
            <div class="campo">
              <label for="cot-costo">Costo de la naviera (interno)</label>
              <input type="number" id="cot-costo" name="costo_interno" min="0" step="0.01">
              <span class="ayuda">No se muestra al cliente.</span>
            </div>
            <?php } ?>
            <div class="campo">
              <label for="cot-vigencia">Vigente hasta</label>
              <input type="text" id="cot-vigencia" name="vigencia" placeholder="dd/mm/aaaa">
            </div>
            <div class="campo">
              <label for="cot-archivo">Correo o archivo enviado</label>
              <input type="file" id="cot-archivo" name="archivo">
            </div>
            <div class="campo campo-ancho">
              <label for="cot-cond">Condiciones</label>
              <textarea id="cot-cond" name="condiciones" placeholder="Días libres, recargos, validez…"></textarea>
            </div>
          </div>
          <div class="pie-formulario">
            <button type="button" class="boton boton-secundario" data-alternar="form-cotizacion">Cancelar</button>
            <button type="submit" class="boton boton-primario">Guardar cotización</button>
          </div>
        </div>
      </form>

      <section class="tarjeta">
        <form class="filtros" action="cotizaciones.php" method="get">
          <input type="search" class="filtro-ancho" name="q" value="<?= e($q) ?>" placeholder="Buscar cotización, cliente o destino…" aria-label="Buscar">
          <select name="estado" aria-label="Estado">
            <option value="">Todos los estados</option>
            <?php foreach ($txt as $k => $t) { ?><option value="<?= $k ?>"<?= $est === $k ? ' selected' : '' ?>><?= $t ?></option><?php } ?>
          </select>
          <button type="submit" class="boton boton-secundario">Filtrar</button>
        </form>
        <div class="tabla-contenedor">
          <table>
            <thead>
              <tr>
                <th>Cotización</th><th>Versión</th><th>Cliente</th><th>Naviera</th><th>Destino</th><th>Contenedores</th>
                <th class="derecha">Flete / cont.</th><?php if ($ver_costos) { ?><th class="derecha">Costo interno</th><?php } ?><th>Vigencia</th><th>Estado</th><th>Operación</th><th></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($cots as $c) { ?>
              <tr>
                <td><strong><?= e($c['codigo']) ?></strong><?= $c['archivo_ruta'] ? ' <a class="chico" href="../descargar.php?tipo=cot&id=' . (int) $c['id'] . '">adjunto</a>' : '' ?></td>
                <td>v<?= (int) $c['version'] ?></td>
                <td><?= e($c['empresa']) ?></td>
                <td><?= e($c['naviera']) ?></td>
                <td><?= e($c['pod']) ?></td>
                <td><?= e($c['contenedores']) ?></td>
                <td class="num derecha"><?= e(dinero($c['flete'])) ?></td>
                <?php if ($ver_costos) { ?><td class="num derecha"><?= $c['costo_interno'] !== null ? e(dinero($c['costo_interno'])) : '—' ?></td><?php } ?>
                <td class="num"><?= e(f_corta($c['vigencia'])) ?></td>
                <td><?= badge($txt[$c['estado_visible']], $cls[$c['estado_visible']]) ?></td>
                <td><?= $c['op_id'] ? '<a href="operacion.php?id=' . (int) $c['op_id'] . '" class="ref">' . e($c['referencia']) . '</a>' : '<span class="suave">—</span>' ?></td>
                <td>
                  <?php if ($c['estado'] === 'enviada' && puede_actuar_cliente($c['cliente_id'])) { ?>
                    <form action="cotizaciones.php" method="post" style="display:inline">
                      <?= csrf_campo() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                      <button name="estado" value="aceptada" class="boton boton-secundario boton-chico">Aceptada</button>
                      <button name="estado" value="rechazada" class="boton boton-secundario boton-chico">Rechazada</button>
                    </form>
                  <?php } ?>
                </td>
              </tr>
            <?php } if (!$cots) { ?>
              <tr><td colspan="12" class="suave">No hay cotizaciones que coincidan.</td></tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
      </section>
<?php pie_personal(); ?>
