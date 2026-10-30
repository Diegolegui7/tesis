<?php
// Copiar este archivo como config.local.php y completar los datos. config.local.php no se sube al repositorio.
// Ejemplo: avisos por Google Workspace de ARPAFLU.
//   1. Crear (o elegir) una cuenta del dominio, por ejemplo avisos@arpaflu.com.
//   2. En esa cuenta activar la verificación en dos pasos y generar una “contraseña de aplicación” (16 letras).
//      Si el administrador no permite contraseñas de aplicación, usar el relay SMTP de Google (smtp-relay.gmail.com)
//      autorizando la IP del servidor: en ese caso se deja SMTP_USUARIO y SMTP_CLAVE vacíos.
define('MAIL_MODO', 'smtp');
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SEGURIDAD', 'starttls');
define('SMTP_USUARIO', 'avisos@arpaflu.com');
define('SMTP_CLAVE', 'xxxx xxxx xxxx xxxx');
