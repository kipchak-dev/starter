<?php

declare(strict_types=1);

namespace Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * What the installed Kipchak CLI scaffolds must fit this project: a resource made with make:resource leaves the
 * OpenAPI document valid and its own tests passing. The CLI's templates refer to parts of the document, such as
 * #/components/responses/NotFound, so removing one of those breaks every project that scaffolds afterwards.
 *
 * The CLI writes into a copy of the project, never into this one.
 */
final class ScaffoldTest extends TestCase
{
    private string $copy = '';

    protected function setUp(): void
    {
        $root = dirname(__DIR__);
        if (!is_file($root . '/vendor/bin/kipchak')) {
            self::markTestSkipped('kipchak/cli is not installed.');
        }

        $this->copy = sys_get_temp_dir() . '/kipchak-scaffold-' . bin2hex(random_bytes(4));
        $this->copyDirectory($root, $this->copy);
    }

    protected function tearDown(): void
    {
        if ($this->copy !== '' && is_dir($this->copy)) {
            $this->removeDirectory($this->copy);
        }
    }

    public function testAScaffoldedResourceLeavesTheDocumentValidAndItsTestsPassing(): void
    {
        [$code, $output] = $this->inCopy(['vendor/bin/kipchak', 'make:resource', 'Caliphs', '--fields', 'id:int,name:string', '--no-interaction']);
        self::assertSame(0, $code, $output);

        [$code, $output] = $this->inCopy(['vendor/bin/kipchak', 'openapi:validate', '--strict']);
        self::assertSame(0, $code, $output);

        [$code, $output] = $this->inCopy([PHP_BINARY, 'vendor/bin/phpunit', '--colors=never', 'tests/CaliphsTest.php']);
        self::assertSame(0, $code, $output);
    }

    /**
     * @param list<string> $command
     * @return array{0: int, 1: string} exit code, and standard output and error together
     */
    private function inCopy(array $command): array
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, $this->copy);
        self::assertIsResource($process);
        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        return [proc_close($process), $output];
    }

    private function copyDirectory(string $from, string $to): void
    {
        mkdir($to, 0777, true);
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($items as $item) {
            $relative = substr($item->getPathname(), strlen($from) + 1);
            if (preg_match('#^(\.git|\.phpunit\.cache)(/|$)#', $relative) === 1) {
                continue;
            }
            $target = $to . '/' . $relative;
            if ($item->isLink()) {
                symlink((string) readlink($item->getPathname()), $target);
            } elseif ($item->isDir()) {
                mkdir($target);
            } else {
                copy($item->getPathname(), $target);
                chmod($target, $item->getPerms() & 0777);
            }
        }
    }

    private function removeDirectory(string $directory): void
    {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}
