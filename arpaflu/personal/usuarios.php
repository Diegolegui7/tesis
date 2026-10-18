<?php
// SS1 · P1 Administrar accesos: cuentas, permisos por función y por cliente (RF-01 a RF-04)
require_once __DIR__ . '/../partes/layout.php';
$u = requerir_empleado('administrar_usuarios');

function clave_temporal()
{
    $alfabeto = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $c = '';
    for ($i = 0; $i < 10; $i++) { $c .= $alfabeto[random_int(0, strlen($alfabeto) - 1)]; }
    return $c;
}

if (es_post()) {
    csrf_verificar();
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';

    if ($accion === 'crear') {
        $nombre = trim(isset($_POST['nombre']) ? $_POST['nombre'] : '');
        $correo = trim(isset($_POST['correo']) ? $_POST['correo'] : '');
        $tipo = isset($_POST['tipo']) && $_POST['tipo'] === 'cliente' ? 'cliente' : 'empleado';
        $funcion = isset($_POST['funcion']) ? $_POST['funcion'] : '';
        $errores = [];
        if ($nombre === '') { $errores[] = 'Indicá el nombre.'; }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) { $errores[] = 'El correo no es válido.'; }
        elseif (valor('SELECT id FROM usuarios WHERE correo = ?', [$correo])) { $errores[] = 'Ya existe una cuenta con ese correo.'; }
        $cliente_id = null;
        $rol = null;
        if ($tipo === 'cliente') {
            $cliente_id = (int) $funcion;
            if (!$cliente_id || !valor('SELECT id FROM clientes WHERE id = ?', [$cliente_id])) { $errores[] = 'Elegí la empresa del usuario cliente.'; }
            $rol = 'cliente';
        } else {
            if (!in_array($funcion, ['administrador', 'operaciones', 'comercial', 'consulta'], true)) { $errores[] = 'Elegí la función del empleado.'; }
            $rol = $funcion;
        }
        if ($errores) {
            foreach ($errores as $er) { flash('error', $er); }
        } else {
            $clave = clave_temporal();
            $pdo = db();
            $pdo->beginTransaction();
            ejecutar('INSERT INTO usuarios (nombre, correo, clave_hash, tipo, rol, cliente_id, puede_seleccionar) VALUES (?,?,?,?,?,?,?)',
                [$nombre, $correo, password_hash($clave, PASSWORD_DEFAULT), $tipo, $rol, $cliente_id, ($tipo === 'cliente' && !empty($_POST['puede_seleccionar'])) ? 1 : 0]);
            $nuevo = (int) $pdo->lastInsertId();
            if ($tipo === 'empleado') {
                $permisos = permisos_por_rol($rol);
                foreach (array_keys(funciones_sistema()) as $f) {
                    ejecutar('INSERT INTO permisos (usuario_id, funcion, permitido) VALUES (?,?,?)', [$nuevo, $f, in_array($f, $permisos, true) ? 1 : 0]);
                }
            }
            $pdo->commit();
            // En esta versión de prueba la clave inicial se muestra una sola vez (en producción se enviaría por correo)
            flash('ok', 'Usuario creado. Clave inicial de ' . $correo . ': ' . $clave . ' (se muestra una sola vez; el usuario debería cambiarla al ingresar).');
        }
    } elseif ($accion === 'clave') {              // restablecer la clave de una cuenta: se genera una temporal que se muestra una sola vez
        $uid = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
        $cta = fila('SELECT id, correo FROM usuarios WHERE id = ?', [$uid]);
        if (!$cta) {
            flash('error', 'No existe esa cuenta.');
        } else {
            $clave = clave_temporal();
            ejecutar('UPDATE usuarios SET clave_hash = ? WHERE id = ?', [password_hash($clave, PASSWORD_DEFAULT), $uid]);
            flash('ok', 'Nueva clave de ' . $cta['correo'] . ': ' . $clave . ' (se muestra una sola vez; la persona puede cambiarla desde «Mi clave»).');
        }
    } elseif ($accion === 'estado') {
        $uid = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
        if ($uid === $u['id']) {
            flash('error', 'No podés desactivar tu propia cuenta.');
        } else {
            ejecutar('UPDATE usuarios SET activo = 1 - activo WHERE id = ?', [$uid]);
            flash('ok', 'Estado de la cuenta actualizado.');
        }
    } elseif ($accion === 'permisos') {          // permisos por función de un empleado
        $uid = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
        $emp = fila("SELECT id, rol FROM usuarios WHERE id = ? AND tipo = 'empleado'", [$uid]);
        if ($emp && $emp['rol'] !== 'administrador') {
            $marcados = isset($_POST['funcion']) && is_array($_POST['funcion']) ? $_POST['funcion'] : [];
            foreach (array_keys(funciones_sistema()) as $f) {
                if ($f === 'administrar_usuarios') { continue; }
                ejecutar('INSERT INTO permisos (usuario_id, funcion, permitido) VALUES (?,?,?) ON DUPLICATE KEY UPDATE permitido = VALUES(permitido)',
                    [$uid, $f, in_array($f, $marcados, true) ? 1 : 0]);
            }
            // clientes sobre los que puede actuar
            $act = isset($_POST['actua']) && is_array($_POST['actua']) ? array_map('intval', $_POST['actua']) : [];
            ejecutar('DELETE FROM permisos_cliente WHERE usuario_id = ?', [$uid]);
            foreach (consulta('SELECT id FROM clientes WHERE activo = 1') as $c) {
                ejecutar('INSERT INTO permisos_cliente (usuario_id, cliente_id, nivel) VALUES (?,?,?)', [$uid, $c['id'], in_array((int) $c['id'], $act, true) ? 'gestion' : 'consulta']);
            }
            flash('ok', 'Permisos actualizados. Se aplican la próxima vez que la persona ingrese.');
        } else {
            flash('error', 'Ese usuario no admite cambios de permisos.');
        }
    } elseif ($accion === 'seleccion') {         // habilitar a un usuario cliente a elegir alternativas
        ejecutar("UPDATE usuarios SET puede_seleccionar = 1 - puede_seleccionar WHERE id = ? AND tipo = 'cliente'", [(int) (isset($_POST['id']) ? $_POST['id'] : 0)]);
        flash('ok', 'Permiso de selección actualizado.');
    }
    redirigir('usuarios.php' . (isset($_POST['volver']) ? '#' . preg_replace('/\W/', '', $_POST['volver']) : ''));
}

$personal = consulta(
    "SELECT u.*, (SELECT GROUP_CONCAT(c.empresa ORDER BY c.empresa SEPARATOR ', ') FROM permisos_cliente pc JOIN clientes c ON c.id = pc.cliente_id
                  WHERE pc.usuario_id = u.id AND pc.nivel = 'gestion') AS clientes_asignados
     FROM usuarios u WHERE u.tipo = 'empleado' ORDER BY u.activo DESC, u.id"
);
$clientes_u = consulta("SELECT u.*, c.empresa FROM usuarios u JOIN clientes c ON c.id = u.cliente_id WHERE u.tipo = 'cliente' ORDER BY c.empresa, u.nombre");
$clientes = consulta('SELECT id, empresa FROM clientes WHERE activo = 1 ORDER BY empresa');
$permisos_u = [];
foreach (consulta('SELECT usuario_id, funcion, permitido FROM permisos') as $r) { $permisos_u[$r['usuario_id']][$r['funcion']] = (bool) $r['permitido']; }
$actua_u = [];
foreach (consulta("SELECT usuario_id, cliente_id FROM permisos_cliente WHERE nivel = 'gestion'") as $r) { $actua_u[$r['usuario_id']][] = (int) $r['cliente_id']; }
$editar_id = (int) (isset($_GET['permisos']) ? $_GET['permisos'] : 0);
$editar = null;
foreach ($personal as $pp) { if ((int) $pp['id'] === $editar_id && $pp['rol'] !== 'administrador') { $editar = $pp; } }

$ultimo = function ($dt) { return $dt ? f_hora($dt) : '—'; };

cabecera_personal('Usuarios y permisos', 'usuarios');
?>
      <div class="encabezado-pagina">
        <div>
          <h1>Usuarios y permisos</h1>
          <p>Cuentas del personal y de los clientes. Cada cliente solo ve sus propias operaciones.</p>
        </div>
        <div class="acciones">
          <button type="button" class="boton boton-primario" data-alternar="form-usuario">+ Nuevo usuario</button>
        </div>
      </div>

      <form id="form-usuario" class="tarjeta" action="usuarios.php" method="post" hidden style="margin-bottom:18px">
        <?= csrf_campo() ?><input type="hidden" name="accion" value="crear">
        <div class="tarjeta-cabecera"><h2>Nuevo usuario</h2></div>
        <div class="tarjeta-cuerpo formulario">
          <div class="campos">
            <div class="campo"><label for="usu-nombre">Nombre y apellido</label><input type="text" id="usu-nombre" name="nombre" maxlength="120" required></div>
            <div class="campo"><label for="usu-correo">Correo</label><input type="email" id="usu-correo" name="correo" maxlength="160" required></div>
            <div class="campo">
              <label for="usu-tipo">Tipo de cuenta</label>
              <select id="usu-tipo" name="tipo"><option value="empleado">Empleado</option><option value="cliente">Cliente</option></select>
            </div>
            <div class="campo">
              <label for="usu-funcion">Función o empresa</label>
              <select id="usu-funcion" name="funcion">
                <optgroup label="Empleado">
                  <option value="administrador">Administrador</option><option value="operaciones" selected>Operaciones</option>
                  <option value="comercial">Comercial</option><option value="consulta">Solo consulta</option>
                </optgroup>
                <optgroup label="Cliente">
                  <?php foreach ($clientes as $c) { ?><option value="<?= (int) $c['id'] ?>"><?= e($c['empresa']) ?></option><?php } ?>
                </optgroup>
              </select>
            </div>
            <div class="campo campo-ancho"><label class="casilla"><input type="checkbox" name="puede_seleccionar" value="1"> Si es un usuario cliente: puede pedir reservas y elegir alternativas</label></div>
          </div>
          <p class="ayuda">En esta versión de prueba la clave inicial se muestra una sola vez al crear la cuenta.</p>
          <div class="pie-formulario">
            <button type="button" class="boton boton-secundario" data-alternar="form-usuario">Cancelar</button>
            <button type="submit" class="boton boton-primario">Crear usuario</button>
          </div>
        </div>
      </form>

      <?php if ($editar) { $pu = isset($permisos_u[$editar['id']]) ? $permisos_u[$editar['id']] : []; $au = isset($actua_u[$editar['id']]) ? $actua_u[$editar['id']] : []; ?>
      <form class="tarjeta" action="usuarios.php" method="post" style="margin-bottom:18px">
        <?= csrf_campo() ?><input type="hidden" name="accion" value="permisos"><input type="hidden" name="id" value="<?= (int) $editar['id'] ?>">
        <div class="tarjeta-cabecera"><h2>Permisos de <?= e($editar['nombre']) ?></h2></div>
        <div class="tarjeta-cuerpo formulario">
          <div class="campos">
            <div class="campo campo-ancho">
              <span class="etiqueta">Funciones</span>
              <?php foreach (funciones_sistema() as $k => $t) { if ($k === 'administrar_usuarios') continue; ?>
                <label class="casilla"><input type="checkbox" name="funcion[]" value="<?= e($k) ?>"<?= !empty($pu[$k]) ? ' checked' : '' ?>> <?= e($t) ?></label>
              <?php } ?>
            </div>
            <div class="campo campo-ancho">
              <span class="etiqueta">Clientes sobre los que puede actuar (cargar, modificar y avisar). Puede consultar todos.</span>
              <?php foreach ($clientes as $c) { ?>
                <label class="casilla"><input type="checkbox" name="actua[]" value="<?= (int) $c['id'] ?>"<?= in_array((int) $c['id'], $au, true) ? ' checked' : '' ?>> <?= e($c['empresa']) ?></label>
              <?php } ?>
            </div>
          </div>
          <div class="pie-formulario">
            <a href="usuarios.php" class="boton boton-secundario">Cancelar</a>
            <button type="submit" class="boton boton-primario">Guardar permisos</button>
          </div>
        </div>
      </form>
      <?php } ?>

      <nav class="pestanas" role="tablist">
        <button class="pestana activa" data-pestana="personal" role="tab" aria-selected="true">Personal <span class="contador"><?= count($personal) ?></span></button>
        <button class="pestana" data-pestana="clientes" role="tab" aria-selected="false">Usuarios de clientes <span class="contador"><?= count($clientes_u) ?></span></button>
        <button class="pestana" data-pestana="permisos" role="tab" aria-selected="false">Permisos por función</button>
      </nav>

      <section id="personal" class="panel-pestana tarjeta" role="tabpanel">
        <div class="tabla-contenedor">
          <table>
            <thead><tr><th>Usuario</th><th>Función</th><th>Clientes asignados</th><th>Último ingreso</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($personal as $x) { ?>
              <tr>
                <td><strong><?= e($x['nombre']) ?></strong><div class="chico suave"><?= e($x['correo']) ?></div></td>
                <td><?= e(etiqueta_rol($x['rol'])) ?></td>
                <td class="<?= $x['rol'] === 'administrador' || !$x['clientes_asignados'] ? 'suave' : '' ?>"><?= $x['rol'] === 'administrador' ? 'Todos' : e($x['clientes_asignados'] ?: '—') ?></td>
                <td class="num"><?= e($ultimo($x['ultimo_ingreso'])) ?></td>
                <td><?= $x['activo'] ? badge('Activo', 'estado-ok') : badge('Inactivo', 'estado-neutro') ?></td>
                <td>
                  <?php if ($x['rol'] !== 'administrador') { ?><a href="usuarios.php?permisos=<?= (int) $x['id'] ?>" class="boton boton-secundario boton-chico">Permisos</a><?php } ?>
                  <form action="usuarios.php" method="post" style="display:inline" data-confirmar="¿Generar una clave nueva para esta cuenta? La anterior deja de funcionar."><?= csrf_campo() ?><input type="hidden" name="accion" value="clave"><input type="hidden" name="id" value="<?= (int) $x['id'] ?>"><button class="boton boton-secundario boton-chico">Restablecer clave</button></form>
                  <?php if ((int) $x['id'] !== $u['id']) { ?>
                  <form action="usuarios.php" method="post" style="display:inline"><?= csrf_campo() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= (int) $x['id'] ?>">
                    <button class="boton boton-secundario boton-chico"><?= $x['activo'] ? 'Desactivar' : 'Activar' ?></button></form>
                  <?php } ?>
                </td>
              </tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
      </section>

      <section id="clientes" class="panel-pestana tarjeta" role="tabpanel" hidden>
        <div class="tabla-contenedor">
          <table>
            <thead><tr><th>Usuario</th><th>Empresa</th><th>Puede pedir reservas y elegir</th><th>Último ingreso</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($clientes_u as $x) { ?>
              <tr>
                <td><strong><?= e($x['nombre']) ?></strong><div class="chico suave"><?= e($x['correo']) ?></div></td>
                <td><?= e($x['empresa']) ?></td>
                <td><?= $x['puede_seleccionar'] ? 'Sí' : 'No (solo consulta)' ?></td>
                <td class="num"><?= e($ultimo($x['ultimo_ingreso'])) ?></td>
                <td><?= !$x['activo'] ? badge('Inactivo', 'estado-neutro') : ($x['ultimo_ingreso'] ? badge('Activo', 'estado-ok') : badge('Invitación pendiente', 'estado-alerta')) ?></td>
                <td>
                  <form action="usuarios.php" method="post" style="display:inline"><?= csrf_campo() ?><input type="hidden" name="volver" value="clientes"><input type="hidden" name="id" value="<?= (int) $x['id'] ?>">
                    <button name="accion" value="seleccion" class="boton boton-secundario boton-chico"><?= $x['puede_seleccionar'] ? 'Quitar permiso de pedir' : 'Permitir pedir y elegir' ?></button>
                    <button name="accion" value="clave" class="boton boton-secundario boton-chico">Restablecer clave</button>
                    <button name="accion" value="estado" class="boton boton-secundario boton-chico"><?= $x['activo'] ? 'Desactivar' : 'Activar' ?></button>
                  </form>
                </td>
              </tr>
            <?php } ?>
            </tbody>
          </table>
        </div>
      </section>

      <section id="permisos" class="panel-pestana tarjeta" role="tabpanel" hidden>
        <div class="tabla-contenedor">
          <table>
            <thead><tr><th>Acción</th><th>Administrador</th><th>Operaciones</th><th>Comercial</th><th>Solo consulta</th><th>Cliente</th></tr></thead>
            <tbody>
            <?php
            $cliente_txt = ['ver_operaciones' => 'Solo las propias', 'cargar_documentos' => 'Sí (de sus operaciones)'];
            foreach (funciones_sistema() as $k => $t) { ?>
              <tr>
                <td><?= e($t) ?></td>
                <?php foreach (['administrador', 'operaciones', 'comercial', 'consulta'] as $rol) { ?>
                  <td><?= in_array($k, permisos_por_rol($rol), true) ? ($k === 'ver_operaciones' ? 'Todas' : 'Sí') : 'No' ?></td>
                <?php } ?>
                <td><?= isset($cliente_txt[$k]) ? e($cliente_txt[$k]) : 'No' ?></td>
              </tr>
            <?php } ?>
              <tr><td>Seleccionar alternativas</td><td>No</td><td>No</td><td>No</td><td>No</td><td>Si está habilitado</td></tr>
              <tr><td>Confirmar «Notado»</td><td>No</td><td>No</td><td>No</td><td>No</td><td>Sí</td></tr>
            </tbody>
          </table>
        </div>
        <p class="ayuda" style="padding:0 18px 14px">Son los permisos que se cargan al crear una cuenta; se pueden ajustar por persona con «Permisos». Además, cada empleado solo puede modificar los clientes que tiene asignados (RF-02).</p>
      </section>
<?php pie_personal(); ?>
