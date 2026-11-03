<?php

use function Kipchak\Core\env;

return [
    'name' => 'kipchak-starter', // Hyphen or underscore separated string, used in logs
    'debug' => env('DEBUG', true) == true,
    'logExceptions' => true,
    'logExceptionDetails' => true,
    // Write exceptions through the Logger driver (and so through its processors) instead of PHP's error_log().
    // 'logExceptionsTo' => 'logger',
    // If debug is enabled, loglevel is debug. Otherwise, it is info. Overwrite it by specifying it below.
    // 'loglevel' => \Monolog\Level::Debug
];
