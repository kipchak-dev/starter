<?php

declare(strict_types=1);

namespace Tests;

use Kipchak\Core\Api;
use Kipchak\Core\Components\App;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use ReflectionProperty;
use Slim\Middleware\ErrorMiddleware;

/**
 * Slim runs the middleware added last first. The Error middleware must therefore be initialised last in
 * middlewares/middlewares.php (as well as first), so that it is the outermost middleware: an exception thrown by a
 * middleware added after it would reach the client as a bare 500 instead of a JSON error response.
 */
final class MiddlewareOrderTest extends TestCase
{
    public function testTheErrorMiddlewareIsTheOutermost(): void
    {
        self::assertInstanceOf(
            ErrorMiddleware::class,
            $this->outermostMiddleware(ApiTestCase::app()),
            'Initialise the Error middleware last in middlewares/middlewares.php, as well as first, so that it '
            . 'handles exceptions thrown by every other middleware.',
        );
    }

    /**
     * Slim keeps the middleware stack as a chain of handlers; the last middleware added is at its tip.
     */
    private function outermostMiddleware(App $app): mixed
    {
        $dispatcher = new ReflectionProperty(\Slim\App::class, 'middlewareDispatcher')->getValue($app);
        $tip = new ReflectionProperty($dispatcher, 'tip')->getValue($dispatcher);
        $handler = new ReflectionObject($tip);

        return $handler->hasProperty('middleware') ? $handler->getProperty('middleware')->getValue($tip) : null;
    }
}
