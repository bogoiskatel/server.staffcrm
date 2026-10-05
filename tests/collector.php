<?php
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/Collector.php';
function checkCollector($condition,$message) { if(!$condition)throw new RuntimeException($message); }
$payload=['version'=>1,'installation_uuid'=>'11111111-1111-4111-8111-111111111111','church_name'=>'Church','observed_period'=>'2026-10','generated_at'=>'2026-10-05T13:00:00Z','timezone'=>'Europe/Kyiv','year'=>2026,'members_current'=>12,'members_joined_year'=>2,'members_left_year'=>null,'baptized_year'=>3,'home_groups_current_season'=>0,'discipline_current'=>1,'last_login_at'=>null,'country_iso'=>'UA','postcode'=>'00123','location'=>['latitude'=>0,'longitude'=>0]];
$row=Collector::normalize($payload);
checkCollector($row['members_total']===12&&$row['baptized_this_month']===null&&$row['baptized_this_year']===3,'Annual baptism count must never become monthly');
checkCollector($row['left_this_year']===null&&$row['groups_current_season']===0,'Unknown/zero must remain distinct');
checkCollector($row['church_latitude']===0.0&&$row['postcode']==='00123','Zero coordinate/postcode lost');
foreach(['version'=>2,'installation_uuid'=>'bad','members_current'=>-1,'generated_at'=>'2026-99-99T13:00:00Z','observed_period'=>'2026-13'] as $key=>$value) { $bad=$payload;$bad[$key]=$value;try{Collector::normalize($bad);throw new RuntimeException('Invalid payload accepted: '.$key);}catch(InvalidArgumentException $expected){} }
Collector::validateSource(['url'=>'https://127.0.0.1:8766/api/','bearer'=>str_repeat('a',24).'.'.str_repeat('b',64),'local'=>true]);
try{Collector::validateSource(['url'=>'http://169.254.169.254/api/','bearer'=>'bad']);throw new RuntimeException('Unsafe URL accepted');}catch(InvalidArgumentException $expected){}
echo "PASS: collector contract validation, mapping, unknown/zero, local endpoint guard\n";
