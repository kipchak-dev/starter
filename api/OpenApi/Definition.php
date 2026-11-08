<?php

declare(strict_types=1);

namespace Api\OpenApi;

use OpenApi\Attributes as OA;

/**
 * The top of the OpenAPI document: the API's title and version, where it is served, and the responses that
 * operations share. Operations live on controllers and schemas on DTOs.
 */
#[OA\Info(
    version: '1.0.0',
    description: 'An API built with Kipchak.',
    title: 'Kipchak Starter API',
)]
#[OA\Server(url: 'http://localhost:8080', description: 'Local development')]
#[OA\Response(
    response: 'NotFound',
    description: 'The resource does not exist.',
    content: new OA\JsonContent(properties: [
        new OA\Property(property: 'code', type: 'integer', example: 404),
        new OA\Property(property: 'status', type: 'string', example: 'RESOURCE_NOT_FOUND'),
        new OA\Property(property: 'data', type: 'string', example: 'No sultan has the ID 99.'),
    ]),
)]
final class Definition
{
}
