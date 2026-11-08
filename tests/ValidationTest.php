<?php

declare(strict_types=1);

namespace Tests;

use Api\DataTransferObjects\v1\MamlukSultan;
use CuyZ\Valinor\Mapper\Source\Source;
use CuyZ\Valinor\MapperBuilder;
use Kipchak\Core\Components\Dto\Input;
use Kipchak\Core\Components\Dto\InvalidInput;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * A DTO's Field rules: stated once, in the OpenAPI document, enforced on request bodies and not on stored rows.
 */
final class ValidationTest extends ApiTestCase
{
    private function body(string $json): ServerRequestInterface
    {
        return new ServerRequestFactory()->createServerRequest('POST', '/v1/sultans')
            ->withBody(new StreamFactory()->createStream($json));
    }

    public function testTheRulesAreInTheDocument(): void
    {
        $properties = $this->json($this->request('GET', '/openapi.json'))['components']['schemas']['MamlukSultan']['properties'];

        self::assertSame(100, $properties['name']['maxLength']);
        self::assertSame(1250, $properties['reignFrom']['minimum']);
    }

    public function testEveryProblemInARequestBodyIsReportedAtOnce(): void
    {
        try {
            Input::from($this->body('{"name":"","reignFrom":1000}'), MamlukSultan::class, ['id' => 0]);
            self::fail('The body is not valid.');
        } catch (InvalidInput $invalid) {
            self::assertSame(['name', 'reignFrom', 'reignTo'], array_keys($invalid->errors));
        }
    }

    public function testAStoredRowIsNotCheckedAgainstTheRules(): void
    {
        $row = ['id' => 1, 'name' => '', 'reignFrom' => 1000, 'reignTo' => 1000];

        $sultan = new MapperBuilder()->mapper()->map(MamlukSultan::class, Source::array($row));

        self::assertSame(1000, $sultan->reignFrom);
    }
}
