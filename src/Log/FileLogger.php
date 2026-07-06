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

    /** @param array<string, mixed> $context */
    public function emergency(string $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function alert(string $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function notice(string $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function log(LogLevel $level, string $message, array $context = []): void
    {
        if ($level->severity() > $this->minSeverity) {
            return;
        }

        $entry = $this->formatEntry($level, $message, $context);
        $this->write($entry);
    }

    /** @param array<string, mixed> $context */
    private function formatEntry(LogLevel $level, string $message, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $levelName = strtoupper($level->value);
        // Sanitize the whole interpolated line, not just context values —
        // attacker data embedded directly in the message string must not be
        // able to forge extra log lines either. The exception block below
        // formats its own (intentional) multi-line output.
        $interpolated = $this->sanitize($this->interpolate($message, $context));

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

    /** @param array<string, mixed> $context */
    private function interpolate(string $message, array $context): string
    {
        $replacements = [];

        foreach ($context as $key => $value) {
            if ($key === 'exception') {
                continue;
            }

            if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $replacements['{' . $key . '}'] = $this->sanitize((string) $value);
            }
        }

        return strtr($message, $replacements);
    }

    /**
     * Collapse CR/LF in interpolated values so attacker-controlled context can't
     * forge additional log lines (log injection). The exception block formats its
     * own multi-line output separately and is not passed through here.
     */
    private function sanitize(string $value): string
    {
        return str_replace(["\r\n", "\r", "\n"], ' ', $value);
    }

    private function write(string $entry): void
    {
        try {
            // Logs routinely carry internal detail (paths, exception traces),
            // so keep them out of reach of other local users: 0750 directory,
            // 0640 files. Failures are checked via return values — mkdir and
            // file_put_contents emit warnings, not exceptions, so the
            // try/catch alone would not notice them.
            if (
                !is_dir($this->logDirectory)
                && !@mkdir($this->logDirectory, 0750, true)
                && !is_dir($this->logDirectory)
            ) {
                return;
            }

            $filename = 'melodic-' . date('Y-m-d') . '.log';
            $path = $this->logDirectory . '/' . $filename;
            $isNewFile = !file_exists($path);

            if (@file_put_contents($path, $entry, FILE_APPEND | LOCK_EX) === false) {
                return;
            }

            if ($isNewFile) {
                @chmod($path, 0640);
            }
        } catch (\Throwable) {
            // Logger must never crash the app
        }
    }
}
