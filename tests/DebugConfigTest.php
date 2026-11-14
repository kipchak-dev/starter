<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/vendor/kipchak/core/src/Functions.php';

/**
 * Debug mode (Debug-level logging) is off unless DEBUG asks for it, so an API deployed as the starter ships does not
 * log at Debug level in production.
 */
final class DebugConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('DEBUG');
    }

    /**
     * @return array<string, array{?string, bool}>
     */
    public static function values(): array
    {
        return [
            'unset' => [null, false],
            'empty' => ['', false],
            'false' => ['false', false],
            '0' => ['0', false],
            'off' => ['off', false],
            'no' => ['no', false],
            'true' => ['true', true],
            '1' => ['1', true],
            'on' => ['on', true],
            'yes' => ['yes', true],
        ];
    }

    #[DataProvider('values')]
    public function testDebugFollowsTheDebugVariable(?string $value, bool $debug): void
    {
        putenv($value === null ? 'DEBUG' : "DEBUG={$value}");
        // A copy, because core's config() includes each file once: requiring the real file here would leave the other
        // tests' API without its configuration.
        $base = tempnam(sys_get_temp_dir(), 'kipchak-api-');
        $copy = $base . '.php';
        copy(dirname(__DIR__) . '/config/kipchak.api.php', $copy);
        try {
            $config = require $copy;
        } finally {
            unlink($copy);
            unlink($base);
        }

        self::assertSame($debug, $config['debug']);
    }
}
