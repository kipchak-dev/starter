<?php

use function Kipchak\Core\env;

return [
    'name' => 'kipchak-starter', // Hyphen or underscore separated string, used in logs
    'debug' => filter_var(env('DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN), // On only for DEBUG=true, 1, on or yes
    'logExceptions' => true,
    'logExceptionDetails' => true,
    // Write exceptions through the Logger driver (and so through its processors) instead of PHP's error_log().
    // 'logExceptionsTo' => 'logger',
    // If debug is enabled, loglevel is debug. Otherwise, it is info. Overwrite it by specifying it below.
    // 'loglevel' => \Monolog\Level::Debug
];
