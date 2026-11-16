<?php

declare(strict_types=1);

namespace Api\Utils;

use Slim\Interfaces\RouteResolverInterface;
use Slim\Routing\RoutingResults;

/**
 * Which browser applications on other sites may call this API (CORS), from config/kipchak.cors.php.
 *
 * The list is checked once, when the worker starts, and a bad entry stops it with a message naming the entry: an
 * origin that is matched exactly but written wrongly would otherwise allow nothing, or the wrong thing, without any
 * sign. Everything else is computed per request from that request alone, so nothing about one caller's origin is
 * kept for the next request in worker mode.
 *
 * See https://kipchak.dev/docs/starter#cors
 */
final readonly class Cors
{
    private const VARIABLE = 'CORS_ALLOWED_ORIGINS';

    /**
     * @param list<string> $origins normalised named origins
     * @param list<string> $methods
     * @param list<string> $headers
     * @param list<string> $expose
     */
    private function __construct(
        private array $origins,
        private bool $any,
        private array $methods,
        private array $headers,
        private array $expose,
        private int $maxAge,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws \InvalidArgumentException naming the entry that is not allowed
     */
    public static function fromConfig(array $config): self
    {
        $entries = $config['allowed_origins'] ?? [];
        if (!is_array($entries) || !array_is_list($entries)) {
            throw new \InvalidArgumentException("kipchak.cors: 'allowed_origins' must be a list.");
        }

        $any = false;
        $origins = [];
        foreach ($entries as $entry) {
            if (!is_string($entry)) {
                throw new \InvalidArgumentException('kipchak.cors: every allowed origin must be a string.');
            }
            if ($entry === '*') {
                $any = true;
                continue;
            }
            if (strtolower($entry) === 'null') {
                throw new \InvalidArgumentException('kipchak.cors: ' . self::VARIABLE . ' contains "null". Sandboxed frames and file:// pages send that origin, so allowing it would let any of them call this API. Remove it.');
            }
            if (str_contains($entry, '*')) {
                throw new \InvalidArgumentException("kipchak.cors: " . self::VARIABLE . " contains \"{$entry}\". Patterns are not supported: list each origin in full, such as https://app.example.com.");
            }
            $origin = self::normalise($entry);
            if ($origin === null) {
                throw new \InvalidArgumentException("kipchak.cors: " . self::VARIABLE . " contains \"{$entry}\", which is not an origin. Write scheme://host or scheme://host:port with http or https, and no path or trailing slash, such as https://app.example.com.");
            }
            $origins[] = $origin;
        }
        if ($any && $origins !== []) {
            throw new \InvalidArgumentException('kipchak.cors: ' . self::VARIABLE . ' contains "*" together with named origins. Use "*" alone to allow every origin without credentials, or list the origins.');
        }

        return new self(
            array_values(array_unique($origins)),
            $any,
            self::stringList($config, 'allowed_methods'),
            self::stringList($config, 'allowed_headers'),
            self::stringList($config, 'expose_headers'),
            (int) ($config['max_age'] ?? 600),
        );
    }

    /** One line for the worker's log at start, so that a developer meeting a CORS error finds the reason. */
    public function describe(): string
    {
        if ($this->any) {
            return 'CORS: every origin allowed, without credentials.';
        }
        if ($this->origins === []) {
            return 'CORS: no origins listed, browser calls from other sites are refused. Set ' . self::VARIABLE . ' to allow them.';
        }

        return sprintf('CORS: %d %s allowed: %s', count($this->origins), count($this->origins) === 1 ? 'origin' : 'origins', implode(', ', $this->origins));
    }

    /**
     * What html/index.php does for one request, before the router:
     *
     * - a route that answers OPTIONS itself, such as /mcp, owns its CORS completely: no status and no headers here,
     *   so that route's own origin list decides;
     * - a preflight (an OPTIONS request carrying Access-Control-Request-Method) to a path that has routes, but none
     *   for OPTIONS, is answered here with 204 and the preflight headers;
     * - every other request goes to the router with the response headers. A path with no route still answers 404.
     *
     * @return array{0: ?int, 1: array<string, string>} the status to answer with now, or null to run the router; and
     *                                                  the headers to send
     */
    public function forRequest(RouteResolverInterface $routes, string $method, string $path, ?string $requestMethod, ?string $origin): array
    {
        $optionsRoute = $routes->computeRoutingResults($path, 'OPTIONS')->getRouteStatus();
        if ($optionsRoute === RoutingResults::FOUND) {
            return [null, []];
        }
        if (strtoupper($method) === 'OPTIONS' && $requestMethod !== null && $requestMethod !== '' && $optionsRoute === RoutingResults::METHOD_NOT_ALLOWED) {
            return [204, $this->preflightHeaders($origin)];
        }

        return [null, $this->responseHeaders($origin)];
    }

    /**
     * The CORS headers for an ordinary response.
     *
     * @return array<string, string>
     */
    public function responseHeaders(?string $origin): array
    {
        $headers = $this->allowOrigin($origin);
        if ($headers !== [] && $this->expose !== []) {
            $headers['Access-Control-Expose-Headers'] = implode(', ', $this->expose);
        }
        if ($this->origins !== []) {
            $headers['Vary'] = 'Origin';
        }

        return $headers;
    }

    /**
     * The CORS headers for a preflight this API answers.
     *
     * @return array<string, string>
     */
    public function preflightHeaders(?string $origin): array
    {
        $headers = $this->allowOrigin($origin);
        if ($headers !== []) {
            $headers['Access-Control-Allow-Methods'] = implode(', ', $this->methods);
            $headers['Access-Control-Allow-Headers'] = implode(', ', $this->headers);
            $headers['Access-Control-Max-Age'] = (string) $this->maxAge;
        }
        if ($this->origins !== []) {
            $headers['Vary'] = 'Origin, Access-Control-Request-Method, Access-Control-Request-Headers';
        }

        return $headers;
    }

    /** @return array<string, string> */
    private function allowOrigin(?string $origin): array
    {
        if ($origin === null || strtolower($origin) === 'null') {
            return [];
        }
        if ($this->any) {
            // Never with credentials: browsers refuse that pairing, and it would promise what * prevents.
            return ['Access-Control-Allow-Origin' => '*'];
        }
        $normalised = self::normalise($origin);
        if ($normalised === null || !in_array($normalised, $this->origins, true)) {
            return [];
        }

        return ['Access-Control-Allow-Origin' => $origin, 'Access-Control-Allow-Credentials' => 'true'];
    }

    /** scheme://host[:port] in lower case, without a default port; null for anything else. */
    private static function normalise(string $origin): ?string
    {
        if (preg_match('#^(https?)://([^/:?\#@\s]+)(?::(\d{1,5}))?$#i', $origin, $m) !== 1) {
            return null;
        }
        $scheme = strtolower($m[1]);
        $host = strtolower($m[2]);
        $port = isset($m[3]) ? (int) $m[3] : null;
        if ($port !== null && ($port < 1 || $port > 65535)) {
            return null;
        }
        if (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80)) {
            $port = null;
        }

        return $scheme . '://' . $host . ($port === null ? '' : ':' . $port);
    }

    /** @return list<string> */
    private static function stringList(array $config, string $key): array
    {
        $value = $config[$key] ?? [];
        if (!is_array($value) || array_filter($value, static fn ($v): bool => !is_string($v) || $v === '') !== []) {
            throw new \InvalidArgumentException("kipchak.cors: '{$key}' must be a list of names.");
        }

        return array_values($value);
    }
}
