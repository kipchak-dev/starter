<?php

// Prevent worker script termination when a client connection is interrupted
ignore_user_abort(true);

require_once (realpath(__DIR__ . '/../vendor/autoload.php'));

use Api\Utils\Cors;
use Kipchak\Core\Api;
use Kipchak\Driver\Config\Config;
use Kipchak\Driver\Logger\Logger;
//use Doctrine\DBAL\Exception\ConnectionException;
//use Doctrine\DBAL\Exception\ConnectionLost;
//use Kipchak\Driver\Doctrine\Doctrine;

// Instantiate Slim, load dependencies and middlewares
$app = Api::boot();

// Add middleware once, outside the loop
$app->addBodyParsingMiddleware();

// Which browser applications on other sites may call this API, from CORS_ALLOWED_ORIGINS (config/kipchak.cors.php).
// The list is checked here, once: a bad entry stops the worker with a message naming it. The mode is written to the
// log, so a CORS error in a browser can be traced to this line. See https://kipchak.dev/docs/starter#cors
$cors = Cors::fromConfig(Config::get('kipchak.cors'));
Logger::get()->info($cors->describe());

// If using Doctrine with MySQL, give web requests a short read timeout. PHP's MySQL client (mysqlnd) otherwise waits
// up to 86,400 seconds for a database that stopped answering, and every request on the worker hangs with it. PDO has
// no read-timeout attribute for MySQL (pdo_mysql only has a connect timeout, \PDO::ATTR_TIMEOUT, which you can set in
// the connection's driverOptions in config/kipchak.doctrine.php), so set it here, in the web worker only. It is read
// when a connection opens: Api::boot() above opens none. Long-running commands, such as bin/doctrine migrations:up,
// do not load this file and keep the default.
// See https://kipchak.dev/docs/driver-doctrine, and for a worked example the ledger bridge's
// api/Utils/Database/WorkerRequest.php.
// ini_set('mysqlnd.net_read_timeout', '5');

// Paths that must answer without the database: the liveness probe, which would otherwise wait on a database that is
// down and get the service restarted for an outage it cannot fix, and the starter's health check. Keep the list short.
// const NO_DATABASE_PATHS = ['/livez', '/status'];

// Handler outside the loop for better performance (doing less work)
$handler = static function () use ($app, $cors) {
    // If using Doctrine, please add the following for each Entity Manager to allow clearing of Singleton Objects and to deal
    // with connectiont timeouts caused by FrankenPHP's long running process
    // $em = Doctrine::get('entitymanager.primary');

    // FrankenPHP worker mode holds one EM/Connection across many requests. Probe the
    // connection so a stale socket (MySQL primary failover, mysqld restart, idle reap)
    // is replaced with a fresh one *before* request handling — otherwise every request
    // on this worker fails until max_consecutive_failures triggers a recycle.
    // The probe is skipped for NO_DATABASE_PATHS. With the read timeout above, a route that
    // needs the database fails after that timeout, instead of hanging, while MySQL is down.
    // $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    // if (!in_array($path, NO_DATABASE_PATHS, true)) {
    //     $conn = $em->getConnection();
    //     try {
    //         $conn->executeQuery('SELECT 1')->fetchOne();
    //     } catch (ConnectionLost | ConnectionException) {
    //         $conn->close(); // DBAL is lazy; the next query opens a fresh socket.
    //     }
    // }

    // Reset Doctrine identity map on every request (FrankenPHP worker mode keeps one EM per process)
    // $em->clear();

    // CORS, from this request alone. A browser asks before a cross-origin call that carries a token or a JSON body
    // (a preflight); it is answered here, before the router, when the path has routes but none for OPTIONS. A route
    // that answers OPTIONS itself, such as /mcp, and a path with no route, go to the router as before.
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

    try {
        // Called when a request is received,
        // superglobals, php://input and the like are reset
        $app->run();
    } catch (\Throwable $exception) {
        // `set_exception_handler` is called only when the worker script ends,
        // which may not be what you expect, so catch and handle exceptions here
        http_response_code(500);
        echo $exception->getMessage();
    }
};

$maxRequests = (int)($_SERVER['MAX_REQUESTS'] ?? 10000);
for ($nbRequests = 0; !$maxRequests || $nbRequests < $maxRequests; ++$nbRequests) {
    $keepRunning = \frankenphp_handle_request($handler);

    // Work registered with $app->onTerminate(), such as exporting telemetry, runs here, after the response has
    // been sent
    $app->terminate();

    // Call the garbage collector to reduce the chances of it being triggered in the middle of a page generation
    gc_collect_cycles();

    if (!$keepRunning) break;
}

// Cleanup
//$myApp->shutdown();
