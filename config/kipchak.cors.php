<?php

use function Kipchak\Core\env;

/**
 * Which browser applications on other sites may call this API (CORS). Read by html/index.php through
 * Api\Utils\Cors, which checks the list when the worker starts and writes the mode to the log.
 *
 * See https://kipchak.dev/docs/starter#cors
 */
return [
    // Origins allowed to call this API from a browser, comma-separated in CORS_ALLOWED_ORIGINS, for example
    // "https://app.example.com,http://localhost:5173". Each is scheme://host or scheme://host:port, compared
    // exactly: no path, no trailing slash, and no patterns such as https://*.example.com. Empty sends no CORS
    // headers. "*" on its own allows every origin, without credentials. "null" is never accepted.
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', ''))))),

    // What a browser may send in a cross-origin request.
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers' => ['Authorization', 'Content-Type', 'If-None-Match', 'Idempotency-Key'],

    // Response headers a browser application may read.
    'expose_headers' => ['ETag', 'Location', 'Retry-After'],

    // Seconds a browser may keep a preflight answer.
    'max_age' => 600,
];
