<?php

declare(strict_types=1);

namespace Tests;

use Api\Utils\Cors;
use Kipchak\Driver\Config\Config;

/**
 * CORS (starter 4.11): the origin list from CORS_ALLOWED_ORIGINS, checked at start, and the preflight answer.
 * The worker and a real browser were tested when this shipped: see the starter 4.11 release.
 */
final class CorsTest extends ApiTestCase
{
    private const LISTED = 'https://app.example.com';

    protected function setUp(): void
    {
        self::app(); // boots the API, which initialises the Config driver
    }

    /** @param list<string> $origins */
    private static function cors(array $origins): Cors
    {
        return Cors::fromConfig(['allowed_origins' => $origins] + Config::get('kipchak.cors'));
    }

    /** T1: a listed origin is echoed, with credentials, the exposed headers and Vary: Origin. */
    public function testAListedOriginIsEchoedWithCredentials(): void
    {
        $headers = self::cors([self::LISTED, 'http://localhost:5173'])->responseHeaders(self::LISTED);

        self::assertSame(self::LISTED, $headers['Access-Control-Allow-Origin'] ?? null);
        self::assertSame('true', $headers['Access-Control-Allow-Credentials'] ?? null);
        self::assertSame('ETag, Location, Retry-After', $headers['Access-Control-Expose-Headers'] ?? null);
        self::assertSame('Origin', $headers['Vary'] ?? null);
    }

    /** T2: an origin not listed, and a request with no Origin, get no CORS headers; Vary still tells caches. */
    public function testAnOriginNotListedGetsNoCorsHeaders(): void
    {
        $cors = self::cors([self::LISTED]);

        self::assertSame(['Vary' => 'Origin'], $cors->responseHeaders('https://evil.example.com'));
        self::assertSame(['Vary' => 'Origin'], $cors->responseHeaders(null));
        self::assertSame(['Vary' => 'Origin'], $cors->responseHeaders('https://app.example.com.evil.example'));
        self::assertSame(['Vary' => 'Origin, Access-Control-Request-Method, Access-Control-Request-Headers'], $cors->preflightHeaders('https://evil.example.com'));
    }

    /** T3: "*" sends * and never credentials; no combination ever pairs the two. */
    public function testAWildcardNeverCarriesCredentials(): void
    {
        $any = self::cors(['*']);
        self::assertSame(['Access-Control-Allow-Origin' => '*', 'Access-Control-Expose-Headers' => 'ETag, Location, Retry-After'], $any->responseHeaders(self::LISTED));

        foreach ([[], ['*'], [self::LISTED], [self::LISTED, 'http://localhost:5173']] as $list) {
            foreach ([null, self::LISTED, 'https://evil.example.com', 'null'] as $origin) {
                $cors = self::cors($list);
                foreach ([$cors->responseHeaders($origin), $cors->preflightHeaders($origin)] as $headers) {
                    self::assertFalse(($headers['Access-Control-Allow-Origin'] ?? '') === '*' && isset($headers['Access-Control-Allow-Credentials']),
                        'Allow-Origin * with credentials for ' . json_encode([$list, $origin]));
                }
            }
        }
    }

    /** T4: the default, an empty list, sends no CORS headers at all. */
    public function testTheDefaultSendsNoCorsHeaders(): void
    {
        $default = Cors::fromConfig(Config::get('kipchak.cors'));

        self::assertSame([], $default->responseHeaders(self::LISTED));
        self::assertSame([], $default->preflightHeaders(self::LISTED));
        self::assertSame([], Config::get('kipchak.cors')['allowed_origins'], 'CORS_ALLOWED_ORIGINS is empty in the test environment');
    }

    /** T4b (architect X2): each bad entry stops the worker at start, naming it. */
    public function testABadEntryIsRefusedAtStartNamingIt(): void
    {
        $bad = [
            'https://app.example.com/' => 'not an origin',
            'https://app.example.com/path' => 'not an origin',
            'app.example.com' => 'not an origin',
            'ftp://app.example.com' => 'not an origin',
            'https://app.example.com:99999' => 'not an origin',
            'null' => 'contains "null"',
            'NULL' => 'contains "null"',
            'https://*.example.com' => 'Patterns are not supported',
        ];
        foreach ($bad as $entry => $message) {
            try {
                self::cors([$entry]);
                self::fail("{$entry} was accepted");
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString($message, $e->getMessage(), $entry);
                self::assertStringContainsString('CORS_ALLOWED_ORIGINS', $e->getMessage(), $entry);
            }
        }

        $this->expectExceptionMessage('"*" together with named origins');
        self::cors(['*', self::LISTED]);
    }

    /** T4b: Origin: null never gets CORS headers, even with the start check bypassed; case does not matter. */
    public function testNullNeverMatchesAndCaseIsIgnored(): void
    {
        $bypassed = (new \ReflectionClass(Cors::class))->newInstanceWithoutConstructor();
        foreach (['origins' => ['null'], 'any' => false, 'methods' => [], 'headers' => [], 'expose' => [], 'maxAge' => 600] as $property => $value) {
            (new \ReflectionProperty(Cors::class, $property))->setValue($bypassed, $value);
        }
        self::assertArrayNotHasKey('Access-Control-Allow-Origin', $bypassed->responseHeaders('null'));
        self::assertArrayNotHasKey('Access-Control-Allow-Origin', self::cors(['*'])->responseHeaders('null'));

        $cors = self::cors(['HTTPS://App.Example.COM', 'https://api.example.com:443']);
        self::assertSame('https://APP.example.com', $cors->responseHeaders('https://APP.example.com')['Access-Control-Allow-Origin'] ?? null);
        self::assertSame('https://api.example.com', $cors->responseHeaders('https://api.example.com')['Access-Control-Allow-Origin'] ?? null, 'a default port in the list still matches');
    }

    /** T4c (product manager P1): the line written at start names the mode. */
    public function testTheStartLineNamesTheMode(): void
    {
        self::assertSame('CORS: no origins listed, browser calls from other sites are refused. Set CORS_ALLOWED_ORIGINS to allow them.', self::cors([])->describe());
        self::assertSame('CORS: 2 origins allowed: https://app.example.com, http://localhost:5173', self::cors([self::LISTED, 'http://localhost:5173'])->describe());
        self::assertSame('CORS: 1 origin allowed: https://app.example.com', self::cors([self::LISTED])->describe());
        self::assertSame('CORS: every origin allowed, without credentials.', self::cors(['*'])->describe());
    }

    /** A preflight from a listed origin carries the methods, headers and lifetime. */
    public function testAPreflightFromAListedOriginAllowsTheMethodsAndHeaders(): void
    {
        $headers = self::cors([self::LISTED])->preflightHeaders(self::LISTED);

        self::assertSame(self::LISTED, $headers['Access-Control-Allow-Origin']);
        self::assertSame('GET, POST, PUT, PATCH, DELETE, OPTIONS', $headers['Access-Control-Allow-Methods']);
        self::assertSame('Authorization, Content-Type, If-None-Match, Idempotency-Key', $headers['Access-Control-Allow-Headers']);
        self::assertSame('600', $headers['Access-Control-Max-Age']);
    }

    /** Option A: only a path with routes but none for OPTIONS is answered before the router, using the starter's own routes. */
    public function testOnlyAPathWithoutItsOwnOptionsRouteIsAnsweredBeforeTheRouter(): void
    {
        $routes = self::app()->getRouteResolver();

        self::assertTrue(Cors::answersPreflight($routes, 'OPTIONS', '/v1/sultans', 'POST'), 'a known path');
        self::assertTrue(Cors::answersPreflight($routes, 'OPTIONS', '/status', 'GET'));
        self::assertFalse(Cors::answersPreflight($routes, 'OPTIONS', '/mcp', 'POST'), '/mcp answers OPTIONS itself');
        self::assertFalse(Cors::answersPreflight($routes, 'OPTIONS', '/no/such/path', 'GET'), 'an unknown path goes to the router: 404');
        self::assertFalse(Cors::answersPreflight($routes, 'OPTIONS', '/v1/sultans', null), 'an OPTIONS that is not a preflight goes to the router');
        self::assertFalse(Cors::answersPreflight($routes, 'GET', '/v1/sultans', 'POST'), 'only OPTIONS');
    }
}
