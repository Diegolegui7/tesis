<?php
// SS1 · Seguridad y administración de accesos
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/util.php';

// Funciones del sistema sobre las que se otorgan permisos (RF-01)
function funciones_sistema()
{
    return [
        'ver_operaciones' => 'Ver operaciones',
        'crear_operaciones' => 'Crear y editar operaciones',
        'cotizaciones' => 'Registrar cotizaciones',
        'costos_internos' => 'Ver costos internos',
        'cargar_documentos' => 'Cargar documentos',
        'registrar_cambios' => 'Registrar cambios y avisar',
        'ver_historial' => 'Ver historial completo',
        'administrar_usuarios' => 'Administrar usuarios y asignaciones',
    ];
}

function etiqueta_rol($rol)
{
    $m = ['administrador' => 'Administrador', 'operaciones' => 'Operaciones', 'comercial' => 'Comercial',
        'consulta' => 'Solo consulta', 'cliente' => 'Cliente'];
    return isset($m[$rol]) ? $m[$rol] : $rol;
}

// Permisos por defecto de cada rol (se copian a la tabla permisos al crear un empleado)
function permisos_por_rol($rol)
{
    $todas = array_keys(funciones_sistema());
    $m = [
        'administrador' => $todas,
        'operaciones' => ['ver_operaciones', 'crear_operaciones', 'cotizaciones', 'costos_internos', 'cargar_documentos', 'registrar_cambios', 'ver_historial'],
        'comercial' => ['ver_operaciones', 'cotizaciones', 'costos_internos'],
        'consulta' => ['ver_operaciones'],
    ];
    return isset($m[$rol]) ? $m[$rol] : [];
}

function usuario_actual()
{
    return isset($_SESSION['usuario']) ? $_SESSION['usuario'] : null;
}

function iniciar_sesion($correo, $clave)
{
    $u = fila('SELECT * FROM usuarios WHERE correo = ?', [trim($correo)]);
    // password_verify también se ejecuta si el usuario no existe, para no revelar qué cuentas existen
    $hash = $u ? $u['clave_hash'] : '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ0123';
    $ok = password_verify($clave, $hash);
    if (!$u || !$ok) {
        return 'Correo o contraseña incorrectos.';
    }
    if (!(int) $u['activo']) {
        return 'La cuenta está inactiva. Consultá con el administrador.';
    }
    session_regenerate_id(true);
    $permisos = [];
    $actuables = [];
    if ($u['tipo'] === 'empleado') {
        foreach (consulta('SELECT funcion, permitido FROM permisos WHERE usuario_id = ?', [$u['id']]) as $p) {
            $permisos[$p['funcion']] = (bool) $p['permitido'];
        }
        if ($u['rol'] === 'administrador') {
            $actuables = '*';
        } else {
            foreach (consulta("SELECT cliente_id FROM permisos_cliente WHERE usuario_id = ? AND nivel = 'gestion'", [$u['id']]) as $c) {
                $actuables[] = (int) $c['cliente_id'];
            }
        }
    }
    $_SESSION['usuario'] = [
        'id' => (int) $u['id'], 'nombre' => $u['nombre'], 'correo' => $u['correo'], 'tipo' => $u['tipo'], 'rol' => $u['rol'],
        'cliente_id' => $u['cliente_id'] !== null ? (int) $u['cliente_id'] : null,
        'puede_seleccionar' => (bool) $u['puede_seleccionar'],
        'permisos' => $permisos, 'actuables' => $actuables,
    ];
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
    ejecutar('UPDATE usuarios SET ultimo_ingreso = NOW() WHERE id = ?', [$u['id']]);
    return true;
}

function cerrar_sesion()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], false, true);
    }
    session_destroy();
}

function inicio_segun_tipo($u)
{
    return base_url() . ($u['tipo'] === 'cliente' ? 'cliente/reservas.php' : 'personal/inicio.php');
}

// Páginas del personal: exige sesión de empleado y, si se indica, un permiso de función
function requerir_empleado($funcion = null)
{
    $u = usuario_actual();
    if (!$u) {
        redirigir(base_url() . 'index.php');
    }
    if ($u['tipo'] !== 'empleado') {
        redirigir(base_url() . 'cliente/reservas.php');
    }
    if ($funcion !== null && !puede($funcion)) {
        http_response_code(403);
        exit('<p style="font-family:sans-serif;padding:24px">Tu cuenta no tiene permiso para esta sección. <a href="'
            . e(base_url() . 'personal/inicio.php') . '">Volver al inicio</a></p>');
    }
    return $u;
}

// Páginas del portal de clientes
function requerir_cliente()
{
    $u = usuario_actual();
    if (!$u) {
        redirigir(base_url() . 'index.php');
    }
    if ($u['tipo'] !== 'cliente') {
        redirigir(base_url() . 'personal/inicio.php');
    }
    return $u;
}

function puede($funcion)
{
    $u = usuario_actual();
    if (!$u || $u['tipo'] !== 'empleado') {
        return false;
    }
    return !empty($u['permisos'][$funcion]);
}

// ¿Puede el empleado actuar (cargar, modificar, avisar) sobre este cliente? Consultar está permitido a todo el personal (RF-02)
function puede_actuar_cliente($cliente_id)
{
    $u = usuario_actual();
    if (!$u || $u['tipo'] !== 'empleado') {
        return false;
    }
    if ($u['actuables'] === '*') {
        return true;
    }
    return in_array((int) $cliente_id, $u['actuables'], true);
}

function exigir_actuar_cliente($cliente_id)
{
    if (!puede_actuar_cliente($cliente_id)) {
        flash('error', 'No tenés autorización para modificar información de este cliente.');
        return false;
    }
    return true;
}

// Cliente al que pertenece una cuenta de portal
function cliente_del_usuario()
{
    $u = usuario_actual();
    return $u && $u['tipo'] === 'cliente' ? $u['cliente_id'] : null;
}
