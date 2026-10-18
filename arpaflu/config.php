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

// Correo de avisos:
//  'simulado' = no sale ningún correo; el aviso queda registrado en el sistema (no necesita nada instalado)
//  'smtp'     = se envía a un servidor SMTP de prueba, por ejemplo Mailpit (127.0.0.1:1025)
define('MAIL_MODO', getenv('ARPAFLU_MAIL_MODO') ?: 'simulado');
define('SMTP_HOST', getenv('ARPAFLU_SMTP_HOST') ?: '127.0.0.1');
define('SMTP_PORT', (int) (getenv('ARPAFLU_SMTP_PORT') ?: 1025));
define('MAIL_DE', 'avisos@arpaflu.local');

// Reglas del sistema
define('NOTADO_PLAZO_HORAS', 4);          // plazo para que un destinatario confirme «Notado»
define('RECORDATORIO_HORAS', 48);         // los recordatorios de vencimiento salen con esta anticipación
define('SUBIDA_MAX_MB', 10);

// Carpeta del depósito de documentos (queda fuera de lo que se sirve por web gracias al .htaccess)
define('DIR_ALMACEN', __DIR__ . '/almacen');

// Clave de las cuentas de prueba (la usa instalar.php al cargar los datos de ejemplo)
define('CLAVE_DEMO', 'demo1234');
// Muestra las cuentas de prueba en la pantalla de ingreso. Poner false fuera de la demostración.
define('MODO_DEMO', true);
