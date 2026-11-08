<?php

declare(strict_types=1);

namespace Api\DataTransferObjects\v1;

use Kipchak\Core\Components\Dto\Field;
use OpenApi\Attributes as OA;

/**
 * A DTO is the contract for one shape of data. The same class describes the response and, without the fields the
 * server sets, the request body; its attributes become a schema in the OpenAPI document.
 * A Field also takes rules, such as maxLength: they appear in the document and are enforced on a request body read
 * with Dto\Input, but not on stored rows, so tightening a rule leaves old rows readable.
 */
#[OA\Schema(schema: 'MamlukSultan', description: 'A sultan of the Mamluk Sultanate.')]
final readonly class MamlukSultan
{
    public function __construct(
        #[OA\Property(description: 'Identifier of the sultan.', example: 4)]
        public int $id,

        #[Field(description: 'Name by which the sultan is known.', maxLength: 100, minLength: 1, example: 'Baybars')]
        public string $name,

        #[Field(description: 'Year the reign began (CE).', minimum: 1250, example: 1260)]
        public int $reignFrom,

        #[Field(description: 'Year the reign ended (CE).', minimum: 1250, example: 1277)]
        public int $reignTo,
    ) {}
}
