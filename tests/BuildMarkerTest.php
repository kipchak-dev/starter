<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The build marker: every response carries X-Build when the image was built with BUILD_REF, and no X-Build at all
 * when it was not. A project built without the argument therefore sends exactly what it sent before.
 *
 * A deploy job can read the header back from the public hostname, so these rules decide whether that check can prove
 * a deploy. They read the Dockerfile and the Caddyfiles as written:
 *
 * - The header line is conditional. An unconditional `header X-Build {env.BUILD_REF}` sends an empty header when the
 *   variable is unset, and a build without the argument must send nothing.
 * - The line is in the :80 site block, which serves the API, and not in the metrics listener.
 * - BUILD_REF is declared in the final stage of the Dockerfile, empty by default, limited to 1 to 64 characters from a
 *   safe set by a case pattern and a length test, and passed to the server as an environment variable.
 *
 * Each rule has a negative control: a copy with that one rule broken must be refused. The running image is not probed
 * here; build it with and without the argument and request any path to see the header.
 */
final class BuildMarkerTest extends TestCase
{
    private const string MATCHER = '@build expression `{env.BUILD_REF} != ""`';
    private const string HEADER = 'header @build X-Build {env.BUILD_REF}';
    private const string CHARSET = "*[!A-Za-z0-9._+-]*) ";
    private const string LENGTH = '[ "${#BUILD_REF}" -gt 64 ]';

    /** @return array<string, array{string}> */
    public static function caddyfiles(): array
    {
        return [
            'Caddyfile' => ['etc/caddy/Caddyfile'],
            'Caddyfile.nonworker' => ['etc/caddy/Caddyfile.nonworker'],
        ];
    }

    #[DataProvider('caddyfiles')]
    public function testTheShippedCaddyfileSendsTheHeaderOnlyWhenBuildRefIsSet(string $file): void
    {
        self::assertSame([], self::caddyfileProblems(self::read($file)));
    }

    public function testTheShippedDockerfileDeclaresAndChecksBuildRefInTheFinalStage(): void
    {
        self::assertSame([], self::dockerfileProblems(self::read('Dockerfile')));
    }

    /** @return array<string, array{\Closure(string): string, string}> */
    public static function brokenCaddyfiles(): array
    {
        return [
            'the header made unconditional' => [
                static fn (string $c): string => self::replaceOnce(self::HEADER, 'header X-Build {env.BUILD_REF}', self::replaceOnce(self::MATCHER . "\n", '', $c)),
                'unconditional',
            ],
            'the matcher removed' => [static fn (string $c): string => self::replaceOnce(self::MATCHER . "\n", '', $c), 'matcher'],
            'the header line removed' => [static fn (string $c): string => self::replaceOnce(self::HEADER . "\n", '', $c), 'header line'],
            'the line moved to the metrics listener' => [
                static function (string $c): string {
                    $without = self::replaceOnce("\t" . self::MATCHER . "\n\t" . self::HEADER . "\n", '', $c);

                    return self::replaceOnce(":{\$METRICS_PORT:2019} {\n", ":{\$METRICS_PORT:2019} {\n\t" . self::MATCHER . "\n\t" . self::HEADER . "\n", $without);
                },
                ':80 site block',
            ],
        ];
    }

    /** @param \Closure(string): string $break */
    #[DataProvider('brokenCaddyfiles')]
    public function testABrokenCaddyfileIsRefused(\Closure $break, string $reason): void
    {
        foreach (self::caddyfiles() as [$file]) {
            $problems = self::caddyfileProblems($break(self::read($file)));
            self::assertNotSame([], $problems, "control: $file with \"$reason\" is not refused");
            self::assertStringContainsString($reason, implode("\n", $problems), "$file: the refusal does not name the fault");
        }
    }

    /** @return array<string, array{\Closure(string): string, string}> */
    public static function brokenDockerfiles(): array
    {
        return [
            'a default value' => [static fn (string $d): string => self::replaceOnce("ARG BUILD_REF=\n", "ARG BUILD_REF=unknown\n", $d), 'empty by default'],
            'the character check pointed at another value' => [static fn (string $d): string => self::replaceOnce('case "$BUILD_REF" in', 'case "" in', $d), 'character check'],
            'the character set widened to a space' => [static fn (string $d): string => self::replaceOnce(self::CHARSET, "*[!A-Za-z0-9._+ -]*) ", $d), 'character check'],
            'the length test removed' => [static fn (string $d): string => self::replaceOnce(self::LENGTH, 'false', $d), 'length test'],
            'the environment variable removed' => [static fn (string $d): string => self::replaceOnce("ENV BUILD_REF=\$BUILD_REF\n", '', $d), 'ENV BUILD_REF'],
            // A project that adds a production stage after this one must declare BUILD_REF there too.
            'declared in an earlier stage only' => [
                static fn (string $d): string => $d . "\nFROM 1x.ax/mamluk/php/8.5:franken AS production\nCOPY --from=0 /var/www /var/www\n",
                'final stage',
            ],
        ];
    }

    /** @param \Closure(string): string $break */
    #[DataProvider('brokenDockerfiles')]
    public function testABrokenDockerfileIsRefused(\Closure $break, string $reason): void
    {
        $problems = self::dockerfileProblems($break(self::read('Dockerfile')));
        self::assertNotSame([], $problems, "control: the Dockerfile with \"$reason\" is not refused");
        self::assertStringContainsString($reason, implode("\n", $problems), 'the refusal does not name the fault');
    }

    /** @return list<string> */
    public static function caddyfileProblems(string $caddyfile): array
    {
        $lines = array_map(static fn (string $l): string => trim((string) preg_replace('/(^|\s)#.*$/', '', $l)), explode("\n", $caddyfile));
        $problems = [];

        foreach ($lines as $i => $line) {
            if (preg_match('/^header\s+X-Build\b/i', $line) === 1) {
                $problems[] = 'line ' . ($i + 1) . ' sends X-Build unconditionally, so a build without BUILD_REF sends an empty header';
            }
        }

        $site = self::block($lines, ':80 {');
        if ($site === null) {
            return [...$problems, 'the file has no :80 site block'];
        }
        if (!in_array(self::MATCHER, $site, true)) {
            $problems[] = 'the :80 site block has no @build matcher on a non-empty BUILD_REF';
        }
        if (!in_array(self::HEADER, $site, true)) {
            $problems[] = 'the :80 site block has no conditional X-Build header line';
        }

        $metrics = self::block($lines, ':{$METRICS_PORT:2019} {') ?? [];
        if (preg_grep('/X-Build|@build/', $metrics) !== []) {
            $problems[] = 'the build marker belongs in the :80 site block, not in the metrics listener';
        }

        return $problems;
    }

    /** @return list<string> */
    public static function dockerfileProblems(string $dockerfile): array
    {
        $stages = preg_split('/^FROM\s/m', $dockerfile);
        $final = (string) end($stages);
        $problems = [];

        if (preg_match('/^ARG BUILD_REF(=.*)?$/m', $final, $arg) !== 1) {
            $problems[] = 'BUILD_REF is not declared in the final stage, where the server runs';
        } elseif (($arg[1] ?? '') !== '=') {
            $problems[] = 'BUILD_REF must be empty by default, so that a build without it sends no header';
        }
        if (!str_contains($final, 'case "$BUILD_REF" in') || !str_contains($final, self::CHARSET)) {
            $problems[] = 'the character check on BUILD_REF is missing or allows more than letters, digits, ".", "-", "+" and "_"';
        }
        if (!str_contains($final, self::LENGTH)) {
            $problems[] = 'the length test on BUILD_REF (at most 64 characters) is missing';
        }
        if (preg_match('/^ENV BUILD_REF=\$BUILD_REF$/m', $final) !== 1) {
            $problems[] = 'ENV BUILD_REF=$BUILD_REF is missing, so the server cannot read the value';
        }

        return $problems;
    }

    /**
     * The lines inside the block that opens with $opening, at the top level of the file.
     *
     * @param list<string> $lines
     * @return list<string>|null
     */
    private static function block(array $lines, string $opening): ?array
    {
        $start = array_search($opening, $lines, true);
        if ($start === false) {
            return null;
        }
        $depth = 0;
        for ($j = $start; $j < count($lines); $j++) {
            // A placeholder such as {$VAR} or {env.VAR} opens and closes on the same line, so it adds nothing.
            $depth += substr_count($lines[$j], '{') - substr_count($lines[$j], '}');
            if ($depth === 0) {
                return array_slice($lines, $start + 1, $j - $start - 1);
            }
        }

        return null;
    }

    private static function replaceOnce(string $from, string $to, string $subject): string
    {
        self::assertSame(1, substr_count($subject, $from), "the control's text is not found exactly once: it would test nothing\n$from");

        return str_replace($from, $to, $subject);
    }

    private static function read(string $file): string
    {
        $contents = file_get_contents(dirname(__DIR__) . '/' . $file);
        self::assertIsString($contents, "$file cannot be read");

        return $contents;
    }
}
