<?php

declare(strict_types=1);

namespace Melodic\Log;

/**
 * Use any PSR-3 logger (Monolog, etc.) as the framework's logger:
 *
 *     $container->singleton(LoggerInterface::class,
 *         fn() => new PsrLoggerAdapter($monolog));
 *
 * Requires the `psr/log` package (installed with any PSR-3 logger).
 */
class PsrLoggerAdapter implements LoggerInterface
{
    public function __construct(
        private readonly \Psr\Log\LoggerInterface $logger,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function emergency(string $message, array $context = []): void
    {
        $this->logger->emergency($message, $context);
    }

    /** @param array<string, mixed> $context */
    public function alert(string $message, array $context = []): void
    {
        $this->logger->alert($message, $context);
    }

    /** @param array<string, mixed> $context */
    public function critical(string $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    /** @param array<string, mixed> $context */
    public function notice(string $message, array $context = []): void
    {
        $this->logger->notice($message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    /** @param array<string, mixed> $context */
    public function log(LogLevel $level, string $message, array $context = []): void
    {
        // LogLevel's backing values are exactly the PSR-3 level names.
        $this->logger->log($level->value, $message, $context);
    }
}
