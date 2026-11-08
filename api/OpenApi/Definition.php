<?php

declare(strict_types=1);

namespace Api\OpenApi;

use OpenApi\Attributes as OA;

/**
 * The top of the OpenAPI document: the API's title and version, and where it is served. Operations live on
 * controllers and schemas on DTOs; Api\Utils\OpenApi wraps their responses in the envelope.
 */
#[OA\Info(
    version: '1.0.0',
    description: 'An API built with Kipchak.',
    title: 'Kipchak Starter API',
)]
#[OA\Server(url: 'http://localhost:8080', description: 'Local development')]
final class Definition
{
}
