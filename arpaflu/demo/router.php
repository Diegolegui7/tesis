<?php
// Enrutador del servidor incorporado de PHP (solo para la demostración): cierra las carpetas internas, como hace el .htaccess en Apache
$ruta = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
if (preg_match('#^/(almacen|database|inc|partes)(/|$)#i', $ruta) || preg_match('#^/(config\.php|README\.md)$#i', $ruta)) {
    http_response_code(403);
    exit('Acceso denegado');
}
return false;
