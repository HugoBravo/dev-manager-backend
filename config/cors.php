<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| Here you may configure your settings for cross-origin resource sharing
| or "CORS". This determines what cross-origin operations may execute
| in web browsers. You are free to adjust these settings as needed.
|
| To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
|
*/

$statefulDomains = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', 'localhost,localhost:4200'))
)));

$statefulOrigins = array_map(static function (string $host): string {
    return str_starts_with($host, 'http') ? $host : 'http://'.$host;
}, $statefulDomains);

return [

    'paths' => ['sanctum/csrf-cookie', 'api/*', 'api/auth/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $statefulOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
