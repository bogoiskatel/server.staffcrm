<?php
// Escape every untrusted value rendered into HTML.
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function t(string $key): string
{
    global $locale;
    static $translations;
    if ($translations === null) { $translations = require __DIR__.'/i18n.php'; }
    return $translations[$locale][$key] ?? $translations['uk'][$key] ?? $key;
}
function numberValue($value): string { return $value === null ? '—' : number_format((int)$value, 0, '.', ' '); }
function routeUrl(string $route, array $params = []): string { return '/?' . http_build_query(array_merge(['route'=>$route], $params)); }
// Wrap all screens with local assets and consistent navigation.
function render(string $view, array $data = []): void
{
    global $locale, $auth, $config;
    extract($data, EXTR_SKIP);
    require dirname(__DIR__).'/views/layout.php';
}
