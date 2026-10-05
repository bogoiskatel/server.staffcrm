<!doctype html>
<html lang="<?=e($locale)?>">
<head>
 <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
 <title>STAFF SERVER</title>
 <link rel="stylesheet" href="/assets/vendor/bootstrap.min.css">
 <link rel="stylesheet" href="/assets/vendor/dataTables.bootstrap4.min.css">
 <link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.css">
 <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="<?=$view==='login'?'login-screen':''?>">
<header class="topbar"><a href="<?=e(routeUrl('dashboard'))?>" class="brand"><span class="brand-icon">S</span> STAFF <strong>SERVER</strong></a><div class="top-actions">
<?php foreach(['uk'=>'UA','en'=>'EN'] as $code=>$label): $query=$_GET; $query['lang']=$code; ?>
 <a class="language <?=$locale===$code?'selected':''?>" href="/?<?=e(http_build_query($query))?>"><?=$label?></a>
<?php endforeach; ?>
<?php if($auth->loggedIn()): ?><form method="post" action="<?=e(routeUrl('logout'))?>"><input type="hidden" name="csrf" value="<?=e($auth->csrf())?>"><button class="logout-btn"><?=e(t('logout'))?></button></form><?php endif; ?>
</div></header>
<main class="<?=$view==='login'?'login-container':'workspace'?>">
<?php require __DIR__.'/'.$view.'.php'; ?>
</main>
<footer>STAFF SERVER <span>© <?=date('Y')?></span></footer>
<script src="/assets/vendor/jquery.min.js"></script>
<script src="/assets/vendor/jquery.dataTables.min.js"></script>
<script src="/assets/vendor/dataTables.bootstrap4.min.js"></script>
<script src="/assets/vendor/leaflet/leaflet.js"></script>
<script id="app-settings" type="application/json"><?=json_encode([
 'locale'=>$locale,'tileUrl'=>$config['tile_url'],'attribution'=>$config['tile_attribution'],
 'labels'=>array_combine(['approximate','church_location','details','map_unavailable','search','length','info','zero_records','previous','next','no_data'],array_map('t',['approximate','church_location','details','map_unavailable','search','length','info','zero_records','previous','next','no_data']))
],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR)?></script>
<script src="/assets/app.js"></script>
</body></html>
