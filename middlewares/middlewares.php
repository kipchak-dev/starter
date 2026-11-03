<?php

use Kipchak\Middleware\Error\Error;
use Slim\App;

/**
 * Middleware initialised here applies to every request. Slim runs the last one added first, so initialise Error
 * first: it then wraps every other middleware and turns any exception into a JSON error response. The middleware
 * available for Kipchak is listed on https://kipchak.dev/docs/middleware.
 *
 * @var App $app
 */

Error::initialise($app);
