<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

define('APP_NAME', 'Oficina');
define('APP_SUBTITLE', 'Gestão de Almoxarifado Automotivo');
define('BASE_URL', '/almoxarifado');

define('DB_HOST', 'localhost');
define('DB_NAME', 'almoxarifado_automotivo');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

date_default_timezone_set('America/Sao_Paulo');
?>
