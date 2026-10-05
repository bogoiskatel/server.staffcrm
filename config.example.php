<?php
// Copy to config.php outside the public document root.
$db_dsn = 'mysql:host=127.0.0.1;dbname=server;charset=utf8mb4';
$db_username = '';
$db_password = '';
$admin_username = '';
$admin_password_hash = ''; // Generate with php cli/password.php.
$phone_decryption_key = ''; // Set the private phone decryption key in config.php only.
$statistics_sources = []; // Each source: url, bearer, uuid; local TLS sources also use local=true and ca_file.
$orders_webhook_key = ''; // Separate shared secret for the website form receiver.
$local_http = false;

return [
    'db_dsn' => $db_dsn, 'db_username' => $db_username, 'db_password' => $db_password,
    'admin_username' => $admin_username, 'admin_password_hash' => $admin_password_hash,
    'phone_decryption_key' => $phone_decryption_key,
    'statistics_sources' => $statistics_sources,
    'orders_webhook_key' => $orders_webhook_key,
    'phpmyadmin_url' => '', // Optional local DB administration link.
    'local_http' => $local_http, 'session_timeout' => 3600,
    'login_limit' => 5, 'login_window' => 900,
    'session_name' => 'STAFFSERVER', 'locale' => 'uk',
    'tile_url' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
    'tile_attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
];
