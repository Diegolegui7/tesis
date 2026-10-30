<?php
// SS1 / SS2 · Clientes, contactos, despachantes y empleado responsable (RF-03, RF-05)
require_once __DIR__ . '/../partes/layout.php';
$u = requerir_empleado('ver_operaciones');
$admin = puede('administrar_usuarios');

// Sincroniza los participantes (contacto y despachante) con los datos del cliente
function sincronizar_participantes($cliente_id, $contacto, $correo, $despachante, $correo_desp)
{
    foreach ([['contacto', $contacto, $correo], ['despachante', $despachante, $correo_desp]] as $x) {
        list($rol, $nombre, $mail) = $x;
        $ex = fila("SELECT id FROM participantes WHERE cliente_id = ? AND rol = ? ORDER BY id LIMIT 1", [$cliente_id, $rol]);
        if ($nombre !== '' && $mail !== '') {
            if ($ex) {
                ejecutar('UPDATE participantes SET nombre = ?, correo = ?, activo = 1 WHERE id = ?', [$nombre, $mail, $ex['id']]);
            } else {
                ejecutar('INSERT INTO participantes (cliente_id, nombre, correo, rol) VALUES (?,?,?,?)', [$cliente_id, $nombre, $mail, $rol]);
            }
        } elseif ($ex) {
            ejecutar('UPDATE participantes SET activo = 0 WHERE id = ?', [$ex['id']]);
        }
    }
}

// Cambia el responsable de un cliente: cierra la asignación anterior y habilita al nuevo empleado
function asignar_responsable($cliente_id, $usuario_id)
{
    $actual = valor('SELECT responsable_id FROM clientes WHERE id = ?', [$cliente_id]);
    if ((int) $actual === (int) $usuario_id) {
        return;
    }
    ejecutar('UPDATE asignaciones SET hasta = CURDATE() WHERE cliente_id = ? AND hasta IS NULL', [$cliente_id]);
    if ($usuario_id) {
        ejecutar('INSERT INTO asignaciones (cliente_id, usuario_id, desde) VALUES (?,?,CURDATE())', [$cliente_id, $usuario_id]);
        ejecutar("INSERT INTO permisos_cliente (usuario_id, cliente_id, nivel) VALUES (?,?,'gestion') ON DUPLICATE KEY UPDATE nivel = 'gestion'", [$usuario_id, $cliente_id]);
    }
    ejecutar('UPDATE clientes SET responsable_id = ? WHERE id = ?', [$usuario_id ?: null, $cliente_id]);
}

$editar = null;
if (es_post()) {
    csrf_verificar();
    if (!$admin && !puede('crear_operaciones')) {
        flash('error', 'No tenés autorización para modificar clientes.');
        redirigir('clientes.php');
    }
    $id = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
    $d = [];
    foreach (['empresa', 'cuit', 'contacto', 'correo', 'despachante', 'correo_despachante'] as $k) { $d[$k] = trim(isset($_POST[$k]) ? $_POST[$k] : ''); }
    $d['canal'] = (isset($_POST['canal']) && $_POST['canal'] === 'whatsapp') ? 'whatsapp' : 'correo';
    $resp = (int) (isset($_POST['responsable_id']) ? $_POST['responsable_id'] : 0);

    $errores = [];
    if ($d['empresa'] === '') { $errores[] = 'Indicá el nombre de la empresa.'; }
    foreach (['correo' => 'del contacto', 'correo_despachante' => 'del despachante'] as $k => $t) {
        if ($d[$k] !== '' && !filter_var($d[$k], FILTER_VALIDATE_EMAIL)) { $errores[] = 'El correo ' . $t . ' no es válido.'; }
    }
    if ($id && !$admin && !puede_actuar_cliente($id)) { $errores[] = 'No tenés autorización para editar este cliente.'; }
    if ($errores) {
        foreach ($errores as $er) { flash('error', $er); }
        redirigir($id ? 'clientes.php?editar=' . $id : 'clientes.php');
    }
    if ($id) {
        ejecutar('UPDATE clientes SET empresa=?, cuit=?, contacto=?, correo=?, despachante=?, correo_despachante=?, canal=? WHERE id=?',
            [$d['empresa'], $d['cuit'] ?: null, $d['contacto'] ?: null, $d['correo'] ?: null, $d['despachante'] ?: null, $d['correo_despachante'] ?: null, $d['canal'], $id]);
        flash('ok', 'Cliente actualizado.');
    } else {
        ejecutar('INSERT INTO clientes (empresa, cuit, contacto, correo, despachante, correo_despachante, canal) VALUES (?,?,?,?,?,?,?)',
            [$d['empresa'], $d['cuit'] ?: null, $d['contacto'] ?: null, $d['correo'] ?: null, $d['despachante'] ?: null, $d['correo_despachante'] ?: null, $d['canal']]);
        $id = (int) db()->lastInsertId();
        flash('ok', 'Cliente creado.');
    }
    sincronizar_participantes($id, $d['contacto'], $d['correo'], $d['despachante'], $d['correo_despachante']);
    if ($admin) {              // solo el administrador asigna o reasigna responsables (RF-03)
        asignar_responsable($id, $resp);
    }
    redirigir('clientes.php');
}

if (isset($_GET['editar'])) {
    $editar = fila('SELECT * FROM clientes WHERE id = ?', [(int) $_GET['editar']]);
}

$q = trim(isset($_GET['q']) ? $_GET['q'] : '');
$resp_f = (int) (isset($_GET['responsable']) ? $_GET['responsable'] : 0);
$donde = ['c.activo = 1'];
$p = [];
if ($q !== '') {
    $donde[] = '(c.empresa LIKE ? OR c.contacto LIKE ? OR c.despachante LIKE ?)';
    $l = '%' . $q . '%';
    array_push($p, $l, $l, $l);
}
if ($resp_f) { $donde[] = 'c.responsable_id = ?'; $p[] = $resp_f; }
$clientes = consulta(
    "SELECT c.*, r.nombre AS responsable,
        (SELECT COUNT(*) FROM usuarios x WHERE x.cliente_id = c.id AND x.activo = 1) AS usuarios_portal,
        (SELECT COUNT(*) FROM operaciones o WHERE o.cliente_id = c.id AND o.estado NOT IN ('zarpado','cancelada')) AS ops_activas
     FROM clientes c LEFT JOIN usuarios r ON r.id = c.responsable_id WHERE " . implode(' AND ', $donde) . ' ORDER BY c.empresa',
    $p
);
$empleados = consulta("SELECT id, nombre FROM usuarios WHERE tipo = 'empleado' AND activo = 1 AND rol <> 'consulta' ORDER BY nombre");
$form = $editar ?: ['id' => 0, 'empresa' => '', 'cuit' => '', 'contacto' => '', 'correo' => '', 'despachante' => '', 'correo_despachante' => '', 'canal' => 'correo', 'responsable_id' => ''];
$puede_editar = $admin || puede('crear_operaciones');

cabecera_personal('Clientes', 'clientes');
?>
      <div class="encabezado-pagina">
        <div>
          <h1>Clientes</h1>
          <p>Empresas clientes, sus contactos, despachantes y el empleado responsable de cada una.</p>
        </div>
        <div class="acciones">
          <?php if ($puede_editar) { ?><button type="button" class="boton boton-primario" data-alternar="form-cliente">+ Nuevo cliente</button><?php } ?>
        </div>
      </div>

      <?php if ($puede_editar) { ?>
      <form id="form-cliente" class="tarjeta" action="clientes.php" method="post"<?= $editar ? '' : ' hidden' ?> style="margin-bottom:18px">
        <?= csrf_campo() ?><input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
        <div class="tarjeta-cabecera"><h2><?= $editar ? 'Editar cliente' : 'Nuevo cliente' ?></h2></div>
        <div class="tarjeta-cuerpo formulario">
          <div class="campos">
            <div class="campo"><label for="cli-empresa">Empresa</label><input type="text" id="cli-empresa" name="empresa" maxlength="120" value="<?= e($form['empresa']) ?>" required></div>
            <div class="campo"><label for="cli-cuit">CUIT</label><input type="text" id="cli-cuit" name="cuit" maxlength="20" value="<?= e($form['cuit']) ?>" placeholder="30-00000000-0"></div>
            <div class="campo"><label for="cli-contacto">Contacto principal</label><input type="text" id="cli-contacto" name="contacto" maxlength="120" value="<?= e($form['contacto']) ?>"></div>
            <div class="campo"><label for="cli-correo">Correo del contacto</label><input type="email" id="cli-correo" name="correo" maxlength="160" value="<?= e($form['correo']) ?>"></div>
            <div class="campo"><label for="cli-despachante">Despachante</label><input type="text" id="cli-despachante" name="despachante" maxlength="120" value="<?= e($form['despachante']) ?>"></div>
            <div class="campo"><label for="cli-correo-desp">Correo del despachante</label><input type="email" id="cli-correo-desp" name="correo_despachante" maxlength="160" value="<?= e($form['correo_despachante']) ?>"></div>
            <?php if ($admin) { ?>
            <div class="campo">
              <label for="cli-resp">Empleado responsable</label>
              <select id="cli-resp" name="responsable_id">
                <option value="">Sin asignar</option>
                <?php foreach ($empleados as $em) { ?><option value="<?= (int) $em['id'] ?>"<?= (int) $form['responsable_id'] === (int) $em['id'] ? ' selected' : '' ?>><?= e($em['nombre']) ?></option><?php } ?>
              </select>
            </div>
            <?php } ?>
            <div class="campo">
              <label for="cli-canal">Canal preferido</label>
              <select id="cli-canal" name="canal">
                <option value="correo"<?= $form['canal'] === 'correo' ? ' selected' : '' ?>>Correo electrónico</option>
                <option value="whatsapp"<?= $form['canal'] === 'whatsapp' ? ' selected' : '' ?>>WhatsApp (contacto directo)</option>
              </select>
            </div>
          </div>
          <p class="ayuda">El contacto y el despachante reciben los avisos de cambios y se les pide la confirmación “Notado”.</p>
          <div class="pie-formulario">
            <a href="clientes.php" class="boton boton-secundario">Cancelar</a>
            <button type="submit" class="boton boton-primario">Guardar cliente</button>
          </div>
        </div>
      </form>
      <?php } ?>

      <section class="tarjeta">
        <form class="filtros" action="clientes.php" method="get">
          <input type="search" class="filtro-ancho" name="q" value="<?= e($q) ?>" placeholder="Buscar cliente, contacto o despachante…" aria-label="Buscar">
          <select name="responsable" aria-label="Responsable">
            <option value="">Todos los responsables</option>
            <?php foreach ($empleados as $em) { ?><option value="<?= (int) $em['id'] ?>"<?= $resp_f === (int) $em['id'] ? ' selected' : '' ?>><?= e($em['nombre']) ?></option><?php } ?>
          </select>
          <button type="submit" class="boton boton-secundario">Filtrar</button>
        </form>
        <div class="tabla-contenedor">
          <table>
            <thead><tr><th>Empresa</th><th>Contacto principal</th><th>Despachante</th><th>Responsable</th><th>Usuarios del portal</th><th>Operaciones activas</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($clientes as $c) { ?>
              <tr>
                <td><strong><?= e($c['empresa']) ?></strong><?= $c['selecciona_alternativas'] ? '<div class="chico suave">Selecciona alternativas en el portal</div>' : '' ?></td>
                <td><?= e($c['contacto']) ?><div class="chico suave"><?= e($c['correo']) ?></div></td>
                <td><?= e($c['despachante'] ?: '—') ?></td>
                <td><?= e($c['responsable'] ?: 'Sin asignar') ?></td>
                <td><?= (int) $c['usuarios_portal'] ?></td>
                <td><?= (int) $c['ops_activas'] ?></td>
                <td><?= $puede_editar ? '<a href="clientes.php?editar=' . (int) $c['id'] . '" class="boton boton-secundario boton-chico">Editar</a>' : '' ?></td>
              </tr>
            <?php } if (!$clientes) { ?>
              <tr><td colspan="7" class="suave">No hay clientes que coincidan.</td></tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
      </section>
<?php pie_personal(); ?>
