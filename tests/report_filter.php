<?php
require dirname(__DIR__).'/app/Statistics.php';require dirname(__DIR__).'/app/ReportFilter.php';
$now=new DateTimeImmutable('2026-10-05T00:00:00Z');
foreach(['all','year','month','range'] as $mode){$f=ReportFilter::fromQuery(['report'=>$mode,'year'=>'2026','period'=>'2026-09','date_start'=>'2026-09-01','date_end'=>'2026-09-30'],'2026-10',$now);if($f->mode!==$mode)throw new RuntimeException('Wrong mode');}
if(ReportFilter::fromQuery(['period'=>'current'],null,$now)->period!=='2026-10')throw new RuntimeException('Current month mismatch');
if(ReportFilter::fromQuery(['period'=>'current'],null,new DateTimeImmutable('2026-09-30T21:30:00Z'))->period!=='2026-10')throw new RuntimeException('Kyiv current month boundary mismatch');
$r=ReportFilter::fromQuery(['report'=>'range','date_start'=>'2026-09-01','date_end'=>'2026-09-30'],null,$now);if($r->endExclusive!=='2026-10-01 00:00:00')throw new RuntimeException('Inclusive end date lost');
foreach([['report'=>'bad'],['report'=>'year','year'=>'abc'],['report'=>'month','period'=>'2026-13'],['report'=>'range','date_start'=>'2026-02-31','date_end'=>'2026-03-05'],['report'=>'range','date_start'=>'2026-10-05','date_end'=>'2026-10-01'],['period'=>[]]] as $q){try{ReportFilter::fromQuery($q,null,$now);throw new RuntimeException('Invalid report accepted');}catch(InvalidArgumentException $expected){}}
echo "PASS: report modes, current month, strict date/year validation and inclusive interval\n";
