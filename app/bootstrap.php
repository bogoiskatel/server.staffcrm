<?php
// Load private configuration and establish a strict PDO connection.
function configuration(): array
{
    $path = getenv('STAFF_SERVER_CONFIG') ?: dirname(__DIR__) . '/config.php';
    if (!is_file($path)) { throw new RuntimeException('Private configuration is missing'); }
    $config = require $path;
    if (!is_array($config) || empty($config['admin_username']) || empty($config['admin_password_hash']) || empty($config['db_dsn'])) {
        throw new RuntimeException('Private configuration is incomplete');
    }
    if ((password_get_info($config['admin_password_hash'])['algo'] ?? 0) === 0) { throw new RuntimeException('Password must be a hash'); }
    return $config;
}
function database(array $config): PDO
{
    $db = new PDO($config['db_dsn'], $config['db_username'], $config['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $db->exec("SET time_zone = '+00:00'");
    return $db;
}
require_once __DIR__ . '/Statistics.php';
require_once __DIR__ . '/Repository.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/View.php';

require_once __DIR__.'/Orders.php';
require_once __DIR__.'/ReportFilter.php';
