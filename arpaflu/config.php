<?php
// ARPAFLU · Configuración (solo para pruebas locales con WAMP)
// Compatible con PHP 7.3

date_default_timezone_set('America/Argentina/Buenos_Aires');

// Base de datos: en WAMP el usuario es "root" y la clave está vacía por defecto.
define('DB_HOST', getenv('ARPAFLU_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('ARPAFLU_DB_PORT') ?: '3306');
define('DB_NAME', getenv('ARPAFLU_DB_NAME') ?: 'arpaflu');
define('DB_USER', getenv('ARPAFLU_DB_USER') ?: 'root');
define('DB_PASS', getenv('ARPAFLU_DB_PASS') !== false ? getenv('ARPAFLU_DB_PASS') : '');

// Datos propios de cada instalación (claves de correo, etc.). Ese archivo NO se sube al repositorio (ver .gitignore).
if (file_exists(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}
function cfg_definir($nombre, $entorno, $defecto)
{
    if (defined($nombre)) { return; }
    $v = getenv($entorno);
    define($nombre, $v !== false && $v !== '' ? $v : $defecto);
}

// Correo de avisos:
//  'simulado' = no sale ningún correo; el aviso queda registrado en el sistema (no necesita nada instalado)
//  'smtp'     = se envía por un servidor SMTP:
//               · de prueba, como Mailpit (127.0.0.1:1025, sin cifrado ni usuario), o
//               · el de Google Workspace de ARPAFLU (smtp.gmail.com, puerto 587, STARTTLS, cuenta y contraseña de aplicación).
//  Para el segundo caso copiar config.local.example.php como config.local.php y completar los datos.
cfg_definir('MAIL_MODO', 'ARPAFLU_MAIL_MODO', 'simulado');
cfg_definir('SMTP_HOST', 'ARPAFLU_SMTP_HOST', '127.0.0.1');
cfg_definir('SMTP_PORT', 'ARPAFLU_SMTP_PORT', 1025);
cfg_definir('SMTP_SEGURIDAD', 'ARPAFLU_SMTP_SEGURIDAD', 'ninguna');   // 'ninguna', 'starttls' (puerto 587) o 'ssl' (puerto 465)
cfg_definir('SMTP_USUARIO', 'ARPAFLU_SMTP_USUARIO', '');
cfg_definir('SMTP_CLAVE', 'ARPAFLU_SMTP_CLAVE', '');
cfg_definir('SMTP_VERIFICAR_CERT', 'ARPAFLU_SMTP_VERIFICAR_CERT', true);
// Con Gmail el remitente tiene que ser la misma cuenta con la que se ingresa
cfg_definir('MAIL_DE', 'ARPAFLU_MAIL_DE', SMTP_USUARIO !== '' ? SMTP_USUARIO : 'avisos@arpaflu.local');

// Reglas del sistema
define('NOTADO_PLAZO_HORAS', 4);          // plazo para que un destinatario confirme “Notado”
define('RECORDATORIO_HORAS', 48);         // los recordatorios de vencimiento salen con esta anticipación
define('SUBIDA_MAX_MB', 10);

// Carpeta del depósito de documentos (queda fuera de lo que se sirve por web gracias al .htaccess)
define('DIR_ALMACEN', __DIR__ . '/almacen');

// Clave de las cuentas de prueba (la usa instalar.php al cargar los datos de ejemplo)
define('CLAVE_DEMO', 'demo1234');
// Muestra las cuentas de prueba en la pantalla de ingreso. Poner false fuera de la demostración.
define('MODO_DEMO', true);
