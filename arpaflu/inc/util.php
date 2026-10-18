<?php
// Funciones de apoyo: escape, fechas, avisos en pantalla, protección de formularios y subida de archivos

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
    session_start();
}

function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function redirigir($url)
{
    header('Location: ' . $url);
    exit;
}

// ---------- Mensajes en pantalla ----------
function flash($tipo, $mensaje)
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function mostrar_flash()
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    foreach ($_SESSION['flash'] as $f) {
        $clase = $f['tipo'] === 'error' ? 'aviso-peligro' : ($f['tipo'] === 'ok' ? 'aviso-ok' : 'aviso-info');
        echo '<div class="aviso ' . $clase . '"><div><p style="margin:0">' . e($f['mensaje']) . '</p></div></div>';
    }
    unset($_SESSION['flash']);
}

// ---------- Protección de formularios (CSRF) ----------
function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_campo()
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verificar()
{
    $ok = isset($_POST['csrf']) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
    if (!$ok) {
        http_response_code(400);
        exit('Solicitud no válida. Volvé a la página anterior y reintentá.');
    }
}

function es_post()
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

// ---------- Fechas ----------
function a_fecha($dt)
{
    if ($dt === null || $dt === '' || $dt === '0000-00-00' || $dt === '0000-00-00 00:00:00') {
        return null;
    }
    try {
        return new DateTime($dt);
    } catch (Exception $e) {
        return null;
    }
}

function f_fecha($dt)       // 13/10/2026
{
    $d = a_fecha($dt);
    return $d ? $d->format('d/m/Y') : '—';
}

function f_corta($dt)       // 13/10
{
    $d = a_fecha($dt);
    return $d ? $d->format('d/m') : '—';
}

function f_hora($dt)        // 13/10 09:15
{
    $d = a_fecha($dt);
    return $d ? $d->format('d/m H:i') : '—';
}

function f_completa($dt)    // 13/10/2026 09:15
{
    $d = a_fecha($dt);
    return $d ? $d->format('d/m/Y H:i') : '—';
}

function f_input_dt($dt)    // para campos de texto de formularios
{
    $d = a_fecha($dt);
    return $d ? $d->format('d/m/Y H:i') : '';
}

function f_input_d($dt)
{
    $d = a_fecha($dt);
    return $d ? $d->format('d/m/Y') : '';
}

// Lee 13/10/2026, 13/10/2026 12:00, 2026-10-13 o 2026-10-13T12:00. Devuelve null si está vacío o no se entiende.
function parse_fecha($texto, $conHora = false)
{
    $t = trim((string) $texto);
    if ($t === '') {
        return null;
    }
    $formatos = ['d/m/Y H:i', 'd/m/Y', 'Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d'];
    foreach ($formatos as $f) {
        $d = DateTime::createFromFormat('!' . $f, $t);
        $err = DateTime::getLastErrors();
        if ($d && (!$err || ($err['warning_count'] == 0 && $err['error_count'] == 0))) {
            return $conHora ? $d->format('Y-m-d H:i:s') : $d->format('Y-m-d');
        }
    }
    return false;
}

// Tiempo que falta para una fecha: texto y clase de color
function faltan($dt)
{
    $d = a_fecha($dt);
    if (!$d) {
        return ['texto' => '—', 'clase' => 'estado-neutro', 'dias' => null];
    }
    $seg = $d->getTimestamp() - time();
    if ($seg < 0) {
        return ['texto' => 'Vencido', 'clase' => 'estado-peligro', 'dias' => -1];
    }
    $horas = (int) ceil($seg / 3600);
    if ($horas < 24) {
        return ['texto' => $horas . ' h', 'clase' => 'estado-peligro', 'dias' => 0];
    }
    $dias = (int) ceil($seg / 86400);
    $clase = $dias <= 1 ? 'estado-peligro' : ($dias <= 3 ? 'estado-alerta' : 'estado-neutro');
    return ['texto' => $dias . ($dias == 1 ? ' día' : ' días'), 'clase' => $clase, 'dias' => $dias];
}

function fecha_larga_hoy()
{
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $n = new DateTime();
    return ucfirst($dias[(int) $n->format('w')]) . ' ' . $n->format('j') . ' de ' . $meses[(int) $n->format('n') - 1]
        . ' de ' . $n->format('Y') . ' · Semana ' . (int) $n->format('W');
}

function dinero($n)
{
    return 'US$ ' . number_format((float) $n, 0, ',', '.');
}

// ---------- Estados ----------
function estados_operacion()
{
    return [
        'reserva_solicitada' => ['Reserva solicitada', 'estado-neutro'],
        'booking_confirmado' => ['Booking confirmado', 'estado-ok'],
        'orden_retiro' => ['Orden de retiro', 'estado-info'],
        'documentacion' => ['Documentación', 'estado-alerta'],
        'zarpado' => ['Zarpado', 'estado-info'],
        'cancelada' => ['Cancelada', 'estado-peligro'],
    ];
}

function badge($texto, $clase)
{
    return '<span class="estado ' . e($clase) . '">' . e($texto) . '</span>';
}

function badge_estado_op($estado)
{
    $m = estados_operacion();
    return isset($m[$estado]) ? badge($m[$estado][0], $m[$estado][1]) : e($estado);
}

function iniciales($nombre)
{
    $p = preg_split('/\s+/', trim($nombre));
    $i = mb_substr($p[0], 0, 1);
    if (count($p) > 1) {
        $i .= mb_substr($p[count($p) - 1], 0, 1);
    }
    return mb_strtoupper($i);
}

// ---------- Subida de archivos (depósito de documentos) ----------
// Devuelve [nombre_original, ruta_relativa] | null si no se envió archivo | string con el motivo del error
function subir_archivo($campo, $subcarpeta)
{
    if (empty($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$campo];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        return 'No se pudo subir el archivo (código ' . $f['error'] . ').';
    }
    if ($f['size'] > SUBIDA_MAX_MB * 1024 * 1024) {
        return 'El archivo supera los ' . SUBIDA_MAX_MB . ' MB.';
    }
    $nombre = basename($f['name']);
    $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
    $permitidas = ['pdf', 'xls', 'xlsx', 'doc', 'docx', 'png', 'jpg', 'jpeg', 'eml', 'msg', 'txt', 'csv'];
    if (!in_array($ext, $permitidas, true)) {
        return 'Tipo de archivo no permitido (' . $ext . '). Se aceptan: ' . implode(', ', $permitidas) . '.';
    }
    $limpio = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nombre);
    $rel = trim($subcarpeta, '/') . '/' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '_' . $limpio;
    $dest = DIR_ALMACEN . '/' . $rel;
    if (!is_dir(dirname($dest)) && !mkdir(dirname($dest), 0775, true)) {
        return 'No se pudo crear la carpeta del depósito de documentos.';
    }
    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        return 'No se pudo guardar el archivo en el depósito.';
    }
    return [$nombre, $rel];
}
