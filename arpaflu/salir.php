<?php
require_once __DIR__ . '/inc/auth.php';
cerrar_sesion();
redirigir(base_url() . 'index.php');
