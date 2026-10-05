<?php
// Verify latest-per-CRM selection, inclusive date bounds and absence of duplicate stock totals.
putenv('STAFF_SERVER_CONFIG='.__DIR__.'/.local/config.php');require dirname(__DIR__).'/app/bootstrap.php';
$c=configuration();if(strpos($c['db_dsn'],'dbname=server_test;')===false)throw new RuntimeException('Isolated test database required');$db=database($c);$repo=new Repository($db);
$q=$db->prepare('INSERT INTO statistics_snapshots(installation_id,reporting_period,generated_at,collected_at,members_total) SELECT id,?,?,?,? FROM installations WHERE uuid=?');
$q->execute(['2025-12','2025-12-31 23:59:59','2026-01-01 00:00:00',8,'11111111-1111-4111-8111-111111111111']);
$read=function($query)use($repo){return $repo->installationsForReport(ReportFilter::fromQuery($query,'2026-09'));};
$all=$read(['report'=>'all']);$first=array_values(array_filter($all,function($r){return $r['uuid']==='11111111-1111-4111-8111-111111111111';}))[0];
if(count($all)!==2||(int)$first['members_total']!==12)throw new RuntimeException('Newest snapshot duplicated or missing');
$year=$read(['report'=>'year','year'=>'2025']);if((int)$year[1]['members_total']!==8)throw new RuntimeException('Year selection incorrect');
$range=$read(['report'=>'range','date_start'=>'2025-12-31','date_end'=>'2025-12-31']);if((int)$range[1]['members_total']!==8)throw new RuntimeException('Inclusive day endpoint lost');
$month=$read(['report'=>'month','period'=>'2026-09']);if($month[0]['members_total']!==null||(int)$month[1]['members_total']!==12)throw new RuntimeException('Month mixing');
$empty=$read(['report'=>'range','date_start'=>'2027-01-01','date_end'=>'2027-01-31']);if(Statistics::aggregate($empty,2)['members_total']['value']!==null)throw new RuntimeException('Out-of-range fallback leaked');
$db->exec("DELETE FROM statistics_snapshots WHERE reporting_period='2025-12'");echo "PASS: all/year/month/date selection, latest snapshot, no duplicate stock totals, empty interval\n";
