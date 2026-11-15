<?php

declare(strict_types=1);

namespace Tests;

use Api\Utils\OpenApi;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * The MCP endpoint at /mcp, through the whole API as a worker serves it.
 */
final class McpTest extends ApiTestCase
{
    public function testAnAssistantCanListAndCallTheStatusTool(): void
    {
        $init = $this->mcp(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => [
            'protocolVersion' => '2025-11-25',
            'capabilities' => new \stdClass(),
            'clientInfo' => ['name' => 'starter-test', 'version' => '1.0'],
        ]]);
        self::assertSame(200, $init->getStatusCode());
        self::assertFalse($init->hasHeader('Mcp-Session-Id'), 'The starter keeps no sessions');

        $tools = $this->json($this->mcp(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']));
        self::assertSame(['status'], array_column($tools['result']['tools'], 'name'));
        self::assertTrue($tools['result']['tools'][0]['annotations']['readOnlyHint']);

        $call = $this->json($this->mcp(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'status', 'arguments' => new \stdClass()]]));
        self::assertFalse($call['result']['isError']);
        self::assertSame(['status' => 'alive'], json_decode($call['result']['content'][0]['text'], true));
    }

    public function testAnotherHostIsRefused(): void
    {
        self::assertSame(403, $this->mcp(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], 'evil.example')->getStatusCode());
    }

    /**
     * T8: the MCP route adds nothing to the OpenAPI document, and the document is the same on every generation.
     */
    public function testTheOpenApiDocumentIsUnchangedByTheMcpRoute(): void
    {
        $first = json_encode(OpenApi::generate(new NullLogger()));
        $second = json_encode(OpenApi::generate(new NullLogger()));

        self::assertSame($first, $second);
        self::assertStringNotContainsString('/mcp', $first);
        self::assertStringNotContainsString('StatusTools', $first);
    }

    /**
     * @param array<string, mixed> $message
     */
    private function mcp(array $message, string $host = 'localhost'): ResponseInterface
    {
        $request = new ServerRequestFactory()->createServerRequest('POST', "http://{$host}/mcp")
            ->withHeader('Host', $host)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json, text/event-stream')
            ->withHeader('MCP-Protocol-Version', '2025-11-25')
            ->withBody(new StreamFactory()->createStream(json_encode($message, JSON_THROW_ON_ERROR)));

        return self::app()->handle($request);
    }
}
