<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Authorization, origin, Content-Type');

require_once (realpath(__DIR__ . '/../vendor/autoload.php'));

use Kipchak\Core\Api;

// Instantiate Slim, load dependencies and middlewares
$app = Api::boot();

$app->addBodyParsingMiddleware();

$app->run();

// Send the response before running work registered with $app->onTerminate(), such as exporting telemetry
if (function_exists('frankenphp_finish_request')) {
    frankenphp_finish_request();
} elseif (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}
$app->terminate();
