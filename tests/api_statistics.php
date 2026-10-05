<?php
// Exercise the CRM API contract against a disposable database, without modifying real CRM records.
$crmRoot=getenv('STAFF_CRM_ROOT')?:'/Users/sergiysologub/Code/staffcrm.dev/staffcrm';
require $crmRoot.'/crm_admin/classes/api/ApiStatistics.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli('localhost','root','','',0,dirname(__DIR__).'/work/runtime/mysql.sock');$name='staff_api_flow_test_'.bin2hex(random_bytes(4));$db->query('CREATE DATABASE `'.$name.'`');$db->select_db($name);$db->set_charset('utf8mb4');
try {
foreach([
"CREATE TABLE contacts(ID INT PRIMARY KEY,status_id INT,visibility VARCHAR(16),memberSince DATE,memberTo DATE,baptizeday DATE,churchComeStatus VARCHAR(8),churchLeaveStatus VARCHAR(8))",
"CREATE TABLE contacts_discipline(id INT PRIMARY KEY,contact_ID INT,discipline_type_id INT,discipline_since DATE,discipline_to DATE,discipline_visibility VARCHAR(16))",
"CREATE TABLE contacts_discipline_types(discipline_type_id INT PRIMARY KEY,discipline_visibility VARCHAR(16))",
"CREATE TABLE groups(group_status VARCHAR(16),group_season_id INT)",
"CREATE TABLE groups_seasons(group_season_id INT,group_season_status VARCHAR(16))",
"CREATE TABLE settings(name VARCHAR(100),value TEXT)",
"CREATE TABLE api_login_state(id INT,last_login_at DATETIME,tracking_started_at DATETIME)",
"INSERT INTO settings VALUES('api_url','https://crm.example.com/api/'),('installation_uuid','11111111-1111-4111-8111-111111111111'),('crm_token','fixture'),('own_church_name','Test church')",
"INSERT INTO contacts VALUES(1,2,'visible','2026-10-01',NULL,'2026-10-01','1','0'),(2,2,'visible','2026-09-01',NULL,'2026-09-01','1','0'),(3,4,'visible','2025-01-01','2026-10-02',NULL,'2','1'),(4,4,'visible',NULL,NULL,NULL,'0','0'),(5,2,'visible','2026-10-03',NULL,NULL,'4','0'),(6,2,'visible','2026-11-01',NULL,'2026-11-01','1','0'),(7,2,'hidden','2026-10-01',NULL,'2026-10-01','1','0'),(8,7,'visible',NULL,NULL,NULL,'0','0'),(9,2,'visible',NULL,NULL,NULL,'0','0')",
"INSERT INTO contacts_discipline_types VALUES(1,'visible'),(2,'visible'),(3,'visible'),(4,'visible')",
"INSERT INTO contacts_discipline VALUES(1,1,2,'2025-01-01','2025-02-01','visible'),(2,8,0,'2025-02-01','2025-01-01','visible'),(3,9,2,'2025-01-01',NULL,'visible'),(4,9,4,'2026-01-01',NULL,'visible')",
"INSERT INTO groups_seasons VALUES(1,'current')","INSERT INTO groups VALUES('visible',1)",
] as $sql)$db->query($sql);
$r=(new ApiStatistics($db))->snapshot(new DateTimeImmutable('2026-10-05T13:00:00Z'));
foreach(['discipline_current'=>1,'members_joined_year'=>3,'members_left_year'=>1,'baptized_month'=>1,'baptized_year'=>2] as $field=>$expected){if(($r[$field]??null)!==$expected)throw new RuntimeException($field.' expected '.$expected.' got '.json_encode($r[$field]??null));}
if(($r['availability']['members_left_year']['complete']??true)!==false)throw new RuntimeException('Missing departure dates must be marked incomplete');
$boundary=(new ApiStatistics($db))->snapshot(new DateTimeImmutable('2026-09-30T21:30:00Z'));
if($boundary['observed_period']!=='2026-10'||$boundary['baptized_month']!==1)throw new RuntimeException('Kyiv month boundary mismatch');
echo "PASS: CRM API discipline/dashboard parity, joined/left year, monthly/year baptism, incomplete dates\n";
}finally{$db->query('DROP DATABASE `'.$name.'`');}
