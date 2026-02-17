<?php

declare(strict_types=1);

namespace Melodic\Log;

class FileLogger implements LoggerInterface
{
    private readonly int $minSeverity;

    public function __construct(
        private readonly string $logDirectory,
        LogLevel $minLevel = LogLevel::DEBUG,
    ) {
        $this->minSeverity = $minLevel->severity();
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log(LogLevel $level, string $message, array $context = []): void
    {
        if ($level->severity() > $this->minSeverity) {
            return;
        }

        $entry = $this->formatEntry($level, $message, $context);
        $this->write($entry);
    }

    private function formatEntry(LogLevel $level, string $message, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $levelName = strtoupper($level->value);
        $interpolated = $this->interpolate($message, $context);

        $entry = "[{$timestamp}] {$levelName}: {$interpolated}";

        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $e = $context['exception'];
            $entry .= "\n  Exception: " . get_class($e);
            $entry .= "\n  Message: {$e->getMessage()}";
            $entry .= "\n  At: {$e->getFile()}:{$e->getLine()}";
            $entry .= "\n  Trace:\n    " . str_replace("\n", "\n    ", $e->getTraceAsString());
        }

        return $entry . "\n";
    }

    private function interpolate(string $message, array $context): string
    {
        $replacements = [];

        foreach ($context as $key => $value) {
            if ($key === 'exception') {
                continue;
            }

            if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $replacements['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replacements);
    }

    private function write(string $entry): void
    {
        try {
            if (!is_dir($this->logDirectory)) {
                mkdir($this->logDirectory, 0755, true);
            }

            $filename = 'melodic-' . date('Y-m-d') . '.log';
            $path = $this->logDirectory . '/' . $filename;

            file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            // Logger must never crash the app
        }
    }
}
