<?php
require dirname(__DIR__).'/app/bootstrap.php';
if (PHP_SAPI !== 'cli') { exit(1); }
$db = database(configuration());
$db->exec(file_get_contents(dirname(__DIR__).'/database/schema.sql'));
if (!$db->query("SHOW COLUMNS FROM statistics_snapshots LIKE 'source_metadata'")->fetch()) {
    $db->exec('ALTER TABLE statistics_snapshots ADD source_metadata MEDIUMTEXT NULL');
}
$db->prepare('INSERT IGNORE INTO schema_migrations(version,applied_at) VALUES(?,?)')->execute(['001',gmdate('Y-m-d H:i:s')]);
$db->prepare('INSERT IGNORE INTO schema_migrations(version,applied_at) VALUES(?,?)')->execute(['002-source-metadata',gmdate('Y-m-d H:i:s')]);
$db->prepare('INSERT IGNORE INTO schema_migrations(version,applied_at) VALUES(?,?)')->execute(['003-demo-orders',gmdate('Y-m-d H:i:s')]);
echo "Schema ready\n";
