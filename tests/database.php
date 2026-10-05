<?php
putenv('STAFF_SERVER_CONFIG=' . dirname(__DIR__).'/work/test-config.php');
require dirname(__DIR__).'/app/bootstrap.php';
$config=configuration();
if (strpos($config['db_dsn'],'dbname=server_test;')===false) { throw new RuntimeException('Test database required'); }
$db=database($config);
foreach(['collection_attempts','statistics_snapshots','installation_credentials','installations','login_attempts'] as $table) { $db->exec('DELETE FROM '.$table); }
$q=$db->prepare('INSERT INTO installations(uuid,name,api_url,country_iso,postcode,church_latitude,church_longitude,registered_at) VALUES(?,?,?,?,?,?,?,?)');
$q->execute(['11111111-1111-4111-8111-111111111111','Test <script>alert(1)</script> Church','https://example.invalid/api/v1/statistics','UA','00123',0,0,'2026-09-01 00:00:00']);
$first=$db->lastInsertId();
$q->execute(['22222222-2222-4222-8222-222222222222','No location','https://second.example.invalid/api/v1/statistics','US','00501',null,null,'2026-09-01 00:00:00']);
$second=$db->lastInsertId();
$q=$db->prepare('INSERT INTO statistics_snapshots(installation_id,reporting_period,generated_at,collected_at,members_total,baptized_this_month) VALUES(?,?,?,?,?,?)');
$q->execute([$first,'2026-09','2026-10-01 00:00:00','2026-10-01 00:05:00',12,0]);
$q->execute([$second,'2026-08','2026-09-01 00:00:00','2026-09-01 00:05:00',999,4]);
try { $q->execute([$first,'2026-09','2026-10-02 00:00:00','2026-10-02 00:05:00',20,5]); throw new RuntimeException('Duplicate snapshot accepted'); } catch(PDOException $e) { if($e->getCode()!=='23000') {throw $e;} }
$repo=new Repository($db); $rows=$repo->installations('2026-09');
$sum=Statistics::aggregate($rows,2);
if($sum['members_total']['value']!==12 || $sum['members_total']['complete']) {throw new RuntimeException('Periods mixed');}
if($repo->detail('11111111-1111-4111-8111-111111111111')['postcode']!=='00123') {throw new RuntimeException('Leading zero lost');}
if(count($repo->periods())!==2) {throw new RuntimeException('Period history missing');}
echo "PASS: isolated MariaDB schema, uniqueness, NULL, zero, period separation, postal code\n";
