<?php

declare(strict_types=1);

namespace Api\DataTransferObjects;

use OpenApi\Attributes as OA;

/**
 * What /status answers with. It is not versioned, like the route, so that probes keep working across API versions.
 */
#[OA\Schema(schema: 'ApiStatus', description: 'Whether the API is running.')]
final readonly class ApiStatus
{
    public function __construct(
        #[OA\Property(description: 'Always alive while the API answers.', example: 'alive')]
        public string $status = 'alive',
    ) {}
}
