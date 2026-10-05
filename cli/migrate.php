<?php
require dirname(__DIR__).'/app/bootstrap.php';
if (PHP_SAPI !== 'cli') { exit(1); }
$db = database(configuration());
$db->exec(file_get_contents(dirname(__DIR__).'/database/schema.sql'));
$db->prepare('INSERT IGNORE INTO schema_migrations(version,applied_at) VALUES(?,?)')->execute(['001',gmdate('Y-m-d H:i:s')]);
echo "Schema ready\n";
