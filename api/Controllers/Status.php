<?php

declare(strict_types=1);

namespace Api\Controllers;

use Api\DataTransferObjects\ApiStatus;
use Kipchak\Core\Components\Controllers;
use Kipchak\Core\Components\Http;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A health check for load balancers and liveness probes.
 */
final class Status extends Controllers\Base
{
    #[OA\Get(
        path: '/status',
        operationId: 'getStatus',
        summary: 'Check that the API is running',
        tags: ['Status'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The API is running.',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiStatus'),
            ),
        ],
    )]
    public function get(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return Http\Response::json($response, new ApiStatus(), 200);
    }
}
