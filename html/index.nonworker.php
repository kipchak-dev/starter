<?php

require_once (realpath(__DIR__ . '/../vendor/autoload.php'));

use Api\Utils\Cors;
use Kipchak\Core\Api;
use Kipchak\Driver\Config\Config;

// Instantiate Slim, load dependencies and middlewares
$app = Api::boot();

$app->addBodyParsingMiddleware();

// CORS, as in html/index.php: the list from CORS_ALLOWED_ORIGINS is checked, and a preflight to a path with routes
// but none for OPTIONS is answered here. See https://kipchak.dev/docs/starter-project#cors
$cors = Cors::fromConfig(Config::get('kipchak.cors'));
[$status, $headers] = $cors->forRequest(
    $app->getRouteResolver(),
    (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
    parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/',
    $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? null,
    $_SERVER['HTTP_ORIGIN'] ?? null,
);
foreach ($headers as $name => $value) {
    header("{$name}: {$value}");
}
if ($status !== null) {
    http_response_code($status);

    return;
}

$app->run();

// Send the response before running work registered with $app->onTerminate(), such as exporting telemetry
if (function_exists('frankenphp_finish_request')) {
    frankenphp_finish_request();
} elseif (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}
$app->terminate();
