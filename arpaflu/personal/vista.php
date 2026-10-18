<?php
// Cambia entre la vista simple y la vista completa del menú del personal (se recuerda en una cookie del navegador)
require_once __DIR__ . '/../partes/layout.php';
requerir_empleado();
$modo = isset($_GET['modo']) && $_GET['modo'] === 'completa' ? 'completa' : 'simple';
setcookie('vista', $modo, time() + 365 * 86400, '/', '', false, true);
redirigir($modo === 'completa' ? 'panel.php' : 'inicio.php');
