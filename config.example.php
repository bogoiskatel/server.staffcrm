<?php
// Copy to config.php outside the public document root.
$db_dsn = 'mysql:host=127.0.0.1;dbname=server;charset=utf8mb4';
$db_username = '';
$db_password = '';
$admin_username = '';
$admin_password_hash = ''; // Generate with php cli/password.php.
$local_http = false;

return [
    'db_dsn' => $db_dsn, 'db_username' => $db_username, 'db_password' => $db_password,
    'admin_username' => $admin_username, 'admin_password_hash' => $admin_password_hash,
    'local_http' => $local_http, 'session_timeout' => 3600,
    'login_limit' => 5, 'login_window' => 900,
    'session_name' => 'STAFFSERVER', 'locale' => 'uk',
    'tile_url' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
    'tile_attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
];
