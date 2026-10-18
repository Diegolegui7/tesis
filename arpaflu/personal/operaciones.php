<?php
// SS6 · Consultas: listado de operaciones con filtros (P13)
require_once __DIR__ . '/../partes/layout.php';
$u = requerir_empleado('ver_operaciones');

$q = trim(isset($_GET['q']) ? $_GET['q'] : '');
$cliente = (int) (isset($_GET['cliente']) ? $_GET['cliente'] : 0);
$naviera = (int) (isset($_GET['naviera']) ? $_GET['naviera'] : 0);
$estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$semana = (int) (isset($_GET['semana']) ? $_GET['semana'] : 0);

$donde = [];
$p = [];
if ($q !== '') {
    $donde[] = '(o.referencia LIKE ? OR o.booking LIKE ? OR o.buque LIKE ? OR c.empresa LIKE ? OR o.referencia_cliente LIKE ? OR o.pod LIKE ?)';
    $like = '%' . $q . '%';
    array_push($p, $like, $like, $like, $like, $like, $like);
}
if ($cliente) { $donde[] = 'o.cliente_id = ?'; $p[] = $cliente; }
if ($naviera) { $donde[] = 'o.naviera_id = ?'; $p[] = $naviera; }
if ($estado !== '' && isset(estados_operacion()[$estado])) { $donde[] = 'o.estado = ?'; $p[] = $estado; }
if ($semana) { $donde[] = 'o.semana = ?'; $p[] = $semana; }

$ops = consulta(
    'SELECT o.*, c.empresa, n.nombre AS naviera FROM operaciones o
     JOIN clientes c ON c.id = o.cliente_id LEFT JOIN navieras n ON n.id = o.naviera_id'
    . ($donde ? ' WHERE ' . implode(' AND ', $donde) : '')
    . " ORDER BY (o.estado IN ('zarpado','cancelada')), o.cutoff_doc IS NULL, o.cutoff_doc, o.id DESC",
    $p
);

$clientes = consulta('SELECT id, empresa FROM clientes WHERE activo = 1 ORDER BY empresa');
$navieras = consulta('SELECT id, nombre FROM navieras ORDER BY nombre');
$semanas = consulta('SELECT DISTINCT semana FROM operaciones WHERE semana IS NOT NULL ORDER BY semana');

cabecera_personal('Operaciones', 'operaciones');
?>
      <div class="encabezado-pagina">
        <div>
          <h1>Operaciones</h1>
          <p>Reservas marítimas de exportación, de la solicitud de espacio al zarpe.</p>
        </div>
        <div class="acciones">
          <?php if (puede('crear_operaciones')) { ?>
          <a href="nueva-operacion.php" class="boton boton-primario">+ Nueva operación</a>
          <?php } ?>
        </div>
      </div>

      <section class="tarjeta">
        <form class="filtros" action="operaciones.php" method="get">
          <input type="search" class="filtro-ancho" name="q" value="<?= e($q) ?>" placeholder="Referencia, booking, buque, cliente…" aria-label="Buscar">
          <select name="cliente" aria-label="Cliente">
            <option value="">Todos los clientes</option>
            <?php foreach ($clientes as $c) { ?><option value="<?= (int) $c['id'] ?>"<?= $cliente == $c['id'] ? ' selected' : '' ?>><?= e($c['empresa']) ?></option><?php } ?>
          </select>
          <select name="naviera" aria-label="Naviera">
            <option value="">Todas las navieras</option>
            <?php foreach ($navieras as $n) { ?><option value="<?= (int) $n['id'] ?>"<?= $naviera == $n['id'] ? ' selected' : '' ?>><?= e($n['nombre']) ?></option><?php } ?>
          </select>
          <select name="estado" aria-label="Estado">
            <option value="">Todos los estados</option>
            <?php foreach (estados_operacion() as $k => $v) { ?><option value="<?= e($k) ?>"<?= $estado === $k ? ' selected' : '' ?>><?= e($v[0]) ?></option><?php } ?>
          </select>
          <select name="semana" aria-label="Semana">
            <option value="">Todas las semanas</option>
            <?php foreach ($semanas as $s) { ?><option value="<?= (int) $s['semana'] ?>"<?= $semana == $s['semana'] ? ' selected' : '' ?>>S<?= (int) $s['semana'] ?></option><?php } ?>
          </select>
          <button type="submit" class="boton boton-secundario">Filtrar</button>
        </form>

        <div class="tabla-contenedor">
          <table id="tabla-operaciones">
            <thead>
              <tr>
                <th>Referencia</th><th>Sem.</th><th>Cliente</th><th>Booking</th><th>Buque</th><th>Naviera</th>
                <th>POL → POD</th><th>Contenedores</th><th>Cut-off doc.</th><th>Estado</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($ops as $o) {
                $activa = !in_array($o['estado'], ['zarpado', 'cancelada'], true);
                $f = $activa && $o['cutoff_doc'] ? faltan($o['cutoff_doc']) : ['clase' => 'estado-neutro']; ?>
              <tr>
                <td><a href="operacion.php?id=<?= (int) $o['id'] ?>" class="ref"><?= e($o['referencia']) ?></a></td>
                <td><?= $o['semana'] ? 'S' . (int) $o['semana'] : '—' ?></td>
                <td><?= e($o['empresa']) ?></td>
                <td><?= $o['booking'] ? e($o['booking']) : '<span class="suave">Sin booking</span>' ?></td>
                <td><?= $o['buque'] ? e($o['buque']) : '<span class="suave">A confirmar</span>' ?></td>
                <td><?= e($o['naviera']) ?></td>
                <td><?= e($o['pol']) ?> → <?= e($o['pod']) ?></td>
                <td><?= (int) $o['cantidad'] ?> × <?= e($o['tipo_contenedor']) ?></td>
                <td class="num"><?= $o['cutoff_doc'] ? badge(f_corta($o['cutoff_doc']), $f['clase']) : '<span class="suave">—</span>' ?></td>
                <td><?= badge_estado_op($o['estado']) ?></td>
              </tr>
            <?php } if (!$ops) { ?>
              <tr><td colspan="10" class="suave">No hay operaciones que coincidan con la búsqueda.</td></tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
      </section>
<?php pie_personal(); ?>
