<?php
require dirname(__DIR__).'/app/Orders.php';
$p=['contact'=>'Test <script>','email'=>'demo@example.com','phone'=>'00123','city'=>'City','church'=>'Church','members'=>'50_150','message'=>'Message','ip'=>'127.0.0.1','order_date'=>'2026-10-02'];
$r=Orders::validate($p);if($r['phone']!=='00123'||$r['members']!=='50_150')throw new RuntimeException('Strings lost');
foreach(['email'=>'bad','order_date'=>'2026-02-31','contact'=>[],'ip'=>'bad'] as $k=>$v){$bad=$p;$bad[$k]=$v;try{Orders::validate($bad);throw new RuntimeException('Invalid input accepted');}catch(InvalidArgumentException $ok){}}
foreach(['waiting','issued','paid','installed'] as $s)Orders::validateUpdate($s,'Note');
try{Orders::validateUpdate('forged','');throw new RuntimeException('Invalid status accepted');}catch(InvalidArgumentException $ok){}
echo "PASS: order validation, status whitelist, phone and member-range preservation\n";
