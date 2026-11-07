<?php

declare(strict_types=1);

namespace Api\Utils;

use Kipchak\Core\OpenApi\Envelope;
use OpenApi\Annotations\OpenApi as Document;
use OpenApi\Generator;
use OpenApi\Utils\Pipeline;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Stringable;

/**
 * Builds the OpenAPI document from the attributes in api/. The same code serves /openapi.json and writes the file
 * in bin/openapi, so both always agree.
 *
 * Envelope documents each response as Kipchak sends it: a JSON success declared as its data alone is stated inside
 * {code, status, data}, and a 4xx or 5xx that declares no content gets the error envelope of kipchak/middleware-error.
 */
final class OpenApi
{
    /**
     * @param LoggerInterface|null $logger Receives a warning for each problem found while validating the document.
     */
    public static function generate(?LoggerInterface $logger = null): Document
    {
        $document = new Generator(self::problemsOnly($logger))
            ->withProcessorPipeline(fn (Pipeline $pipeline) => $pipeline->add(new Envelope(errors: true)))
            ->generate([dirname(__DIR__)]);
        if ($document === null) {
            throw new RuntimeException('No OpenAPI attributes were found in api/.');
        }

        return $document;
    }

    /**
     * The generator also logs each file it reads, at debug level; only warnings and above are problems.
     */
    private static function problemsOnly(?LoggerInterface $logger): LoggerInterface
    {
        return new class ($logger) extends AbstractLogger {
            public function __construct(private readonly ?LoggerInterface $logger)
            {
            }

            public function log($level, string|Stringable $message, array $context = []): void
            {
                if (!in_array($level, [LogLevel::DEBUG, LogLevel::INFO, LogLevel::NOTICE], true)) {
                    $this->logger?->log($level, $message, $context);
                }
            }
        };
    }
}
