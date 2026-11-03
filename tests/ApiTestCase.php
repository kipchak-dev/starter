<?php

declare(strict_types=1);

namespace Tests;

use Kipchak\Core\Api;
use Kipchak\Core\Components\App;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Boots the API once, as a worker does, and sends requests to it without a web server.
 */
abstract class ApiTestCase extends TestCase
{
    private static ?App $app = null;

    protected function request(string $method, string $path): ResponseInterface
    {
        // The route files are loaded with require_once, so the app can only be booted once per process.
        self::$app ??= Api::boot();
        $request = new ServerRequestFactory()->createServerRequest($method, $path)
            ->withHeader('Accept', 'application/json');

        return self::$app->handle($request);
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
