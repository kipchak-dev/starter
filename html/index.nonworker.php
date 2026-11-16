<?php

require_once (realpath(__DIR__ . '/../vendor/autoload.php'));

use Api\Utils\Cors;
use Kipchak\Core\Api;
use Kipchak\Driver\Config\Config;

// Instantiate Slim, load dependencies and middlewares
$app = Api::boot();

$app->addBodyParsingMiddleware();

// CORS, as in html/index.php: the list from CORS_ALLOWED_ORIGINS is checked, and a preflight to a path with routes
// but none for OPTIONS is answered here. See https://kipchak.dev/docs/starter#cors
$cors = Cors::fromConfig(Config::get('kipchak.cors'));
$origin = $_SERVER['HTTP_ORIGIN'] ?? null;
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
if (Cors::answersPreflight($app->getRouteResolver(), (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), $path, $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? null)) {
    http_response_code(204);
    foreach ($cors->preflightHeaders($origin) as $name => $value) {
        header("{$name}: {$value}");
    }

    return;
}
foreach ($cors->responseHeaders($origin) as $name => $value) {
    header("{$name}: {$value}");
}

$app->run();

// Send the response before running work registered with $app->onTerminate(), such as exporting telemetry
if (function_exists('frankenphp_finish_request')) {
    frankenphp_finish_request();
} elseif (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}
$app->terminate();
