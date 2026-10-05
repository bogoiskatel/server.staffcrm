<?php
// Serve only known public assets; every other URL goes through authentication.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_string($path) && strpos($path,'/assets/')===0) {
    $file=realpath(dirname(__DIR__).'/public'.$path);
    $root=realpath(dirname(__DIR__).'/public/assets');
    if ($file && strpos($file,$root.DIRECTORY_SEPARATOR)===0 && is_file($file) && in_array(strtolower(pathinfo($file,PATHINFO_EXTENSION)),['css','js','png','jpg','jpeg','svg','woff','woff2','ttf','eot'],true)) { return false; }
}
require dirname(__DIR__).'/public/index.php';
