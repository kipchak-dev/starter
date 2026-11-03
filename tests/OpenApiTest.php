<?php

declare(strict_types=1);

namespace Tests;

use Api\Utils\OpenApi;
use Psr\Log\AbstractLogger;

final class OpenApiTest extends ApiTestCase
{
    public function testTheDocumentDescribesEveryRoute(): void
    {
        $response = $this->request('GET', '/openapi.json');
        $document = $this->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame(['/status', '/v1/sultans', '/v1/sultans/{id}'], array_keys($document['paths']));
        self::assertSame(
            ['id', 'name', 'reignFrom', 'reignTo'],
            array_keys($document['components']['schemas']['MamlukSultan']['properties']),
        );
    }

    public function testTheDocumentIsValid(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $problems = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->problems[] = (string) $message;
            }
        };

        OpenApi::generate($logger);

        self::assertSame([], $logger->problems);
    }
}
