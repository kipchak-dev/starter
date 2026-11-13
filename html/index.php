<?php

// Prevent worker script termination when a client connection is interrupted
ignore_user_abort(true);

require_once (realpath(__DIR__ . '/../vendor/autoload.php'));

use Kipchak\Core\Api;
//use Doctrine\DBAL\Exception\ConnectionException;
//use Doctrine\DBAL\Exception\ConnectionLost;
//use Kipchak\Driver\Doctrine\Doctrine;

// Instantiate Slim, load dependencies and middlewares
$app = Api::boot();

// Add middleware once, outside the loop
$app->addBodyParsingMiddleware();

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
$handler = static function () use ($app) {
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

    /**
     * Tweak these headers as needed, especially if you are going to use this API from a client side SPA
     * without a Backend for Frontend
     */
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Authorization, origin, if-none-match');
    header('Access-Control-Expose-Headers: *');

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
