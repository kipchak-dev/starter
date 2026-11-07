<?php

declare(strict_types=1);

namespace Tests;

use Api\Utils\OpenApi;
use Psr\Log\AbstractLogger;

final class OpenApiTest extends ApiTestCase
{
    /**
     * Routes that are deliberately not in the document. The document does not describe itself.
     */
    private const array UNDOCUMENTED = ['GET /openapi.json'];

    public function testTheDocumentDescribesEveryRouteAndNoOther(): void
    {
        $response = $this->request('GET', '/openapi.json');
        $document = $this->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $documented = [];
        foreach ($document['paths'] as $path => $operations) {
            foreach (array_keys($operations) as $method) {
                $documented[] = strtoupper($method) . ' ' . $path;
            }
        }

        $registered = [];
        foreach (self::app()->getRouteCollector()->getRoutes() as $route) {
            // The document writes a path parameter as {id}; the route may constrain it, as {id:[0-9]+}.
            $path = preg_replace('/\{(\w+):[^}]+\}/', '{$1}', $route->getPattern());
            foreach ($route->getMethods() as $method) {
                $registered[] = "{$method} {$path}";
            }
        }
        $registered = array_values(array_diff($registered, self::UNDOCUMENTED));

        sort($documented);
        sort($registered);
        self::assertSame($registered, $documented, 'Every route must have an operation in the OpenAPI document, and every operation a route.');
    }

    public function testTheSultanSchemaHasItsFields(): void
    {
        $document = $this->json($this->request('GET', '/openapi.json'));

        self::assertSame(
            ['id', 'name', 'reignFrom', 'reignTo'],
            array_keys($document['components']['schemas']['MamlukSultan']['properties']),
        );
    }

    /**
     * Controllers declare only the data; the document must state the envelope that the API actually sends.
     */
    public function testResponsesAreDocumentedInTheEnvelopeTheApiSends(): void
    {
        $document = $this->json($this->request('GET', '/openapi.json'));
        $responses = $document['paths']['/v1/sultans/{id}']['get']['responses'];

        $success = $responses['200']['content']['application/json']['schema']['allOf'];
        self::assertSame('#/components/schemas/Success', $success[0]['$ref']);
        self::assertSame(['$ref' => '#/components/schemas/MamlukSultan'], $success[1]['properties']['data']);
        self::assertSame(['OK'], $success[1]['properties']['status']['enum']);

        $error = $responses['404']['content']['application/json']['schema']['allOf'];
        self::assertSame('#/components/schemas/Error', $error[0]['$ref']);
        self::assertSame(['RESOURCE_NOT_FOUND'], $error[1]['properties']['status']['enum']);

        $sent = $this->json($this->request('GET', '/v1/sultans/4'));
        self::assertSame($document['components']['schemas']['Success']['required'], array_keys($sent));
        self::assertSame([200, 'OK'], [$sent['code'], $sent['status']]);

        $missing = $this->json($this->request('GET', '/v1/sultans/9999'));
        self::assertSame([404, 'RESOURCE_NOT_FOUND'], [$missing['code'], $missing['status']]);
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
