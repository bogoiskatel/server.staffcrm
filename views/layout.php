<!doctype html>
<html lang="<?=e($locale)?>">
<head>
 <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
 <title>STAFF SERVER</title>
 <link rel="stylesheet" href="/assets/staff-crm/style.min.css">
 <link rel="stylesheet" href="/assets/vendor/dataTables.bootstrap4.min.css">
 <link rel="stylesheet" href="/assets/vendor/leaflet/leaflet.css">
 <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="<?=$view==='login'?'login-screen':''?>">
<div id="main-wrapper" data-layout="vertical" data-sidebartype="overlay">
<header class="topbar"><nav class="navbar top-navbar navbar-expand navbar-light bg-white staff-navbar"><a href="<?=e(routeUrl('dashboard'))?>" class="staff-brand text-dark"><img src="/assets/staff-crm/branding/crm.svg" alt="STAFF"> STAFF SERVER</a><div class="top-actions">
<?php if($auth->loggedIn()): ?>
<ul class="navbar-nav"><li class="nav-item dropdown">
<button class="nav-link dropdown-toggle waves-effect waves-dark staff-user-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="<?=e(t('user_menu'))?>"><img src="/assets/staff-crm/branding/default.jpg" alt="" class="rounded-circle" width="36"><span class="ml-2 font-medium"><?=e(t('administrator'))?></span><span class="fas fa-angle-down ml-2" aria-hidden="true"></span></button>
<div class="dropdown-menu dropdown-menu-right user-dd animated flipInY">
<div class="d-flex no-block align-items-center p-3 mb-2 border-bottom"><img src="/assets/staff-crm/branding/default.jpg" alt="" class="rounded" width="80"><div class="ml-2"><h4 class="mb-0"><?=e($config['admin_username'])?></h4><p class="mb-0 text-muted">STAFF SERVER</p><span class="badge badge-info mt-2"><?=e(t('administrator'))?></span></div></div>
<div class="d-flex no-block align-items-center p-3 mb-2"><i class="ti-world mr-2 ml-1" aria-hidden="true"></i><select id="language_select" class="form-control mx-auto staff-language-select" aria-label="<?=e(t('language'))?>"><?php foreach(['uk'=>'Українська','en'=>'English'] as $code=>$label): $query=$_GET; $query['lang']=$code; ?><option value="/?<?=e(http_build_query($query))?>" <?=$locale===$code?'selected':''?>><?=e($label)?></option><?php endforeach; ?></select></div>
<?php if(!empty($config['phpmyadmin_url'])): ?><a class="dropdown-item" href="<?=e($config['phpmyadmin_url'])?>" target="_blank" rel="noopener"><i class="ti-server mr-1 ml-1" aria-hidden="true"></i> phpMyAdmin</a><?php endif; ?>
<div class="dropdown-divider"></div><form method="post" action="<?=e(routeUrl('logout'))?>"><input type="hidden" name="csrf" value="<?=e($auth->csrf())?>"><button class="dropdown-item" type="submit"><i class="fa fa-power-off mr-1 ml-1" aria-hidden="true"></i> <?=e(t('logout'))?></button></form>
</div></li></ul>
<?php else: ?><select class="form-control staff-language-select" aria-label="<?=e(t('language'))?>"><?php foreach(['uk'=>'Українська','en'=>'English'] as $code=>$label): $query=$_GET; $query['lang']=$code; ?><option value="/?<?=e(http_build_query($query))?>" <?=$locale===$code?'selected':''?>><?=e($label)?></option><?php endforeach; ?></select><?php endif; ?>
</div></nav></header>
<main class="<?=$view==='login'?'login-container':'workspace'?>">
<?php require __DIR__.'/'.$view.'.php'; ?>
</main>
<footer class="staff-footer text-muted">STAFF SERVER <span>© <?=date('Y')?></span></footer>
</div>
<script src="/assets/vendor/jquery.min.js"></script>
<script src="/assets/vendor/bootstrap.bundle.min.js"></script>
<script src="/assets/vendor/jquery.dataTables.min.js"></script>
<script src="/assets/vendor/dataTables.bootstrap4.min.js"></script>
<script src="/assets/vendor/leaflet/leaflet.js"></script>
<script id="app-settings" type="application/json"><?=json_encode([
 'locale'=>$locale,'tileUrl'=>$config['tile_url'],'attribution'=>$config['tile_attribution'],
 'labels'=>array_combine(['approximate','church_location','details','map_unavailable','search','length','info','zero_records','previous','next','no_data'],array_map('t',['approximate','church_location','details','map_unavailable','search','length','info','zero_records','previous','next','no_data']))
],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR)?></script>
<script src="/assets/app.js"></script>
</body></html>
