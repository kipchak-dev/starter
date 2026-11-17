<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Caddy's administration API stays off, and Prometheus metrics are served on port 2019 by a listener that answers
 * /metrics only.
 *
 * The administration API has no authentication: it returns the running configuration and replaces it. A running
 * API never needs it. These checks read the Caddyfiles as written, because a mistake here does not show at run time:
 *
 * - Caddy lets listeners share a port, so an `admin :2019` left beside the metrics listener does not stop the server.
 *   Metrics keep working while some connections still reach the administration API.
 * - Deleting the admin line gives Caddy's default, localhost:2019, which shares the port in the same way.
 * - Caddy takes the last admin option it reads, so `admin off` must come after the CADDY_GLOBAL_OPTIONS placeholder.
 *   Otherwise an environment value such as "admin :2020" opens the API again.
 *
 * Each rule has a negative control: a copy of the shipped file with that one rule broken must be refused.
 */
final class CaddyfileTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function shipped(): array
    {
        return [
            'Caddyfile' => ['etc/caddy/Caddyfile'],
            'Caddyfile.nonworker' => ['etc/caddy/Caddyfile.nonworker'],
        ];
    }

    #[DataProvider('shipped')]
    public function testTheShippedCaddyfileKeepsTheAdministrationApiOffAndServesMetricsOn2019(string $file): void
    {
        self::assertSame([], self::problems(self::read($file)));
    }

    /**
     * @return array<string, array{\Closure(string): string, string}>
     */
    public static function brokenCopies(): array
    {
        $line = static fn (string $from, string $to): \Closure => static function (string $caddyfile) use ($from, $to): string {
            $broken = preg_replace($from, $to, $caddyfile, 1, $count);
            self::assertSame(1, $count, "the control's pattern $from matches nothing: it would test nothing");

            return (string) $broken;
        };

        return [
            'admin :2019 restored' => [$line('/^(\s*)admin off$/m', '$1admin :2019'), 'admin :2019'],
            'the admin line deleted' => [$line('/^\s*admin off\n/m', ''), 'no admin line'],
            'admin off before the placeholder' => [
                static function (string $caddyfile) use ($line): string {
                    $without = $line('/^\s*admin off\n/m', '')($caddyfile);

                    return $line('/^(\s*)(\{\$CADDY_GLOBAL_OPTIONS\})$/m', "\$1admin off\n\$1\$2")($without);
                },
                'before {$CADDY_GLOBAL_OPTIONS}',
            ],
            'admin off before the frankenphp block' => [
                static function (string $caddyfile) use ($line): string {
                    $without = $line('/^\s*admin off\n/m', '')($caddyfile);

                    return $line('/^(\s*)(frankenphp \{)$/m', "\$1admin off\n\$1\$2")($without);
                },
                'before {$FRANKENPHP_CONFIG}',
            ],
            'a second admin line' => [$line('/^(\s*)admin off$/m', "\$1admin off\n\$1admin :2020"), 'exactly one'],
            'the global metrics option removed' => [$line('/^ {4}metrics\n/m', ''), 'global metrics option'],
            'the metrics listener removed' => [$line('/^:\{\$METRICS_PORT:2019\} \{.*?^\}\n/ms', ''), 'metrics listener'],
            'the listener answers every path' => [$line('/^\thandle \{\n\t\trespond 404\n\t\}\n/m', ''), 'respond 404'],
        ];
    }

    /**
     * @param \Closure(string): string $break
     */
    #[DataProvider('brokenCopies')]
    public function testABrokenCopyIsRefused(\Closure $break, string $reason): void
    {
        foreach (self::shipped() as [$file]) {
            $problems = self::problems($break(self::read($file)));
            self::assertNotSame([], $problems, "control: $file with \"$reason\" is not refused");
            self::assertStringContainsString($reason, implode("\n", $problems), "$file: the refusal does not name the fault");
        }
    }

    /**
     * What is wrong with a Caddyfile, as sentences. An empty list means it keeps the administration API off and
     * serves metrics on 2019 from a listener that answers /metrics only.
     *
     * @return list<string>
     */
    public static function problems(string $caddyfile): array
    {
        // Comments go first. Caddy substitutes {$VAR} placeholders even inside comments, but a comment is not an
        // option, so it neither counts as an admin line nor as a placeholder the admin line must follow.
        $lines = array_map(static fn (string $l): string => rtrim((string) preg_replace('/(^|\s)#.*$/', '', $l)), explode("\n", $caddyfile));

        $global = self::globalBlock($lines);
        if ($global === null) {
            return ['the file has no global options block'];
        }
        [$start, $end] = $global;

        $problems = [];
        $admin = array_keys(array_filter($lines, static fn (string $l): bool => preg_match('/^\s*admin(\s|$)/', $l) === 1));
        if ($admin === []) {
            $problems[] = 'no admin line: Caddy would start its default administration API on localhost:2019';
        } elseif (count($admin) > 1) {
            $problems[] = 'there must be exactly one admin line, and it is admin off; found ' . count($admin);
        }
        foreach ($admin as $i) {
            $value = trim((string) preg_replace('/^\s*admin/', '', $lines[$i]));
            if ($value !== 'off') {
                $problems[] = "admin {$value} on line " . ($i + 1) . ': the administration API must be off';
            } elseif ($i < $start || $i > $end) {
                $problems[] = 'admin off on line ' . ($i + 1) . ' is outside the global options block';
            }
        }
        foreach (range($start, $end) as $i) {
            if (str_contains($lines[$i], '{$') && $admin !== [] && max($admin) < $i) {
                $problems[] = 'admin off comes before ' . trim($lines[$i]) . ' on line ' . ($i + 1)
                    . ': Caddy takes the last admin option it reads, so an environment value could open the API';
            }
        }

        $globalLines = array_slice($lines, $start, $end - $start + 1);
        if (preg_grep('/^\s*metrics$/', $globalLines) === []) {
            $problems[] = 'the global metrics option is missing, so the metrics listener would serve no FrankenPHP metrics';
        }

        $text = implode("\n", $lines);
        if (preg_match('/^:\{\$METRICS_PORT:2019\} \{\n(.*?)^\}$/ms', $text, $listener) !== 1) {
            $problems[] = 'the metrics listener :{$METRICS_PORT:2019} is missing';
        } else {
            if (preg_match('/^\s*handle \/metrics \{\n\s*metrics\n\s*\}$/m', $listener[1]) !== 1) {
                $problems[] = 'the metrics listener does not serve metrics at handle /metrics';
            }
            if (preg_match('/^\s*handle \{\n\s*respond 404\n\s*\}$/m', $listener[1]) !== 1) {
                $problems[] = 'the metrics listener does not respond 404 to every other path';
            }
        }

        return $problems;
    }

    /**
     * The first and last line of the global options block: the block that opens with a bare "{" before any site.
     *
     * @param list<string> $lines
     * @return array{int, int}|null
     */
    private static function globalBlock(array $lines): ?array
    {
        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            if (trim($line) !== '{') {
                return null;
            }
            $depth = 0;
            for ($j = $i; $j < count($lines); $j++) {
                // A placeholder such as {$VAR} or {$VAR:default} opens and closes on the same line, so it adds nothing.
                $depth += substr_count($lines[$j], '{') - substr_count($lines[$j], '}');
                if ($depth === 0) {
                    return [$i, $j];
                }
            }

            return null;
        }

        return null;
    }

    private static function read(string $file): string
    {
        $contents = file_get_contents(dirname(__DIR__) . '/' . $file);
        self::assertIsString($contents, "$file cannot be read");

        return $contents;
    }
}
