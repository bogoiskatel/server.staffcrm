<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store');
try {
    $config = configuration();
    if (!$config['local_http'] && ($_SERVER['HTTPS'] ?? '') !== 'on') {
        http_response_code(400); exit('HTTPS is required.');
    }
    $db = database($config); $repo = new Repository($db); $auth = new Auth($db,$config); $auth->start();
    $locale = $_GET['lang'] ?? ($_SESSION['locale'] ?? $config['locale']);
    if (!is_string($locale) || !in_array($locale,['uk','en'],true)) { $locale='uk'; }
    $_SESSION['locale']=$locale;
    $route = $_GET['route'] ?? 'dashboard';
    if (!is_string($route)) { http_response_code(400); exit(t('bad_request')); }
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'POST' && !$auth->validCsrf($_POST['csrf'] ?? null)) { http_response_code(403); exit('Invalid CSRF token'); }
    if ($route === 'login') {
        if ($auth->loggedIn()) { header('Location: '.routeUrl('dashboard')); exit; }
        $error = null;
        if ($method === 'POST') {
            $username = $_POST['username'] ?? ''; $password = $_POST['password'] ?? '';
            if (!is_string($username) || !is_string($password) || strlen($username)>200 || strlen($password)>4096) { http_response_code(400); exit(t('bad_request')); }
            $result = $auth->login($username,$password,$_SERVER['REMOTE_ADDR'] ?? 'unknown');
            if ($result === 'success') { header('Location: '.routeUrl('dashboard'),true,303); exit; }
            if ($result === 'limited') { http_response_code(429); header('Retry-After: '.$config['login_window']); }
            $error = t($result);
        }
        render('login',['error'=>$error]); exit;
    }
    if (!$auth->loggedIn()) { header('Location: '.routeUrl('login')); exit; }
    if ($route === 'logout') {
        if ($method !== 'POST') { http_response_code(405); header('Allow: POST'); exit('POST required'); }
        $auth->logout(); header('Location: '.routeUrl('login'),true,303); exit;
    }
    if ($method !== 'GET') { http_response_code(405); header('Allow: GET'); exit('GET required'); }
    $periods = $repo->periods(); $period = $_GET['period'] ?? ($periods[0] ?? null);
    if ($period !== null && (!is_string($period) || !Statistics::validPeriod($period))) { http_response_code(400); exit(t('bad_request')); }
    if ($route === 'dashboard' || $route === 'map') {
        $rows = $repo->installations($period);
        if ($route === 'map') {
            $points=[];
            foreach ($rows as $row) {
                $coords=Statistics::coordinates($row);
                if ($coords) { $points[]=['uuid'=>$row['uuid'],'name'=>$row['name'],'lat'=>$coords[0],'lng'=>$coords[1],'source'=>$coords[2],'url'=>routeUrl('installation',['uuid'=>$row['uuid']])]; }
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['points'=>$points], JSON_THROW_ON_ERROR); exit;
        }
        $tab = ($_GET['tab'] ?? '') === 'statistics' ? 'statistics' : 'network';
        render('dashboard',['rows'=>$rows,'period'=>$period,'periods'=>$periods,'totals'=>Statistics::aggregate($rows,count($rows)),'tab'=>$tab]); exit;
    }
    if ($route === 'installation') {
        $uuid = $_GET['uuid'] ?? '';
        $installation=is_string($uuid) ? $repo->detail($uuid) : null;
        if (!$installation) { http_response_code(404); exit(t('not_found')); }
        render('detail',['installation'=>$installation]); exit;
    }
    http_response_code(404); echo t('not_found');
} catch (Throwable $error) {
    // Avoid exposing credentials, SQL or contact data to the browser and logs.
    error_log('STAFF SERVER request failed: '.get_class($error));
    http_response_code(503); echo 'STAFF SERVER — сервіс тимчасово недоступний / service temporarily unavailable.';
}
