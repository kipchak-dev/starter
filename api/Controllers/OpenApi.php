<?php

declare(strict_types=1);

namespace Api\Controllers;

use Api\Utils\OpenApi as Generator;
use Kipchak\Core\Components\Controllers;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Serves the OpenAPI document. It is built from the code's attributes once per worker and kept: the attributes
 * cannot change while the worker runs, and a worker restarts when the code changes.
 */
final class OpenApi extends Controllers\Base
{
    private static ?string $document = null;

    public function get(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        self::$document ??= Generator::generate($this->logger)->toJson();
        $response->getBody()->write(self::$document);

        return $response->withHeader('Content-Type', 'application/json');
    }
}
