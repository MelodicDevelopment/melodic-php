<?php

declare(strict_types=1);

namespace Melodic\Core;

use RuntimeException;

class Configuration
{
    private array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function loadFile(string $path): void
    {
        if (!file_exists($path)) {
            throw new RuntimeException("Configuration file not found: {$path}");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Failed to read configuration file: {$path}");
        }

        $decoded = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                "Invalid JSON in configuration file '{$path}': " . json_last_error_msg()
            );
        }

        $this->merge($decoded);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $current = $this->data;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $current = &$this->data;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }

                $current = &$current[$segment];
            }
        }
    }

    public function has(string $key): bool
    {
        $segments = explode('.', $key);
        $current = $this->data;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return false;
            }

            $current = $current[$segment];
        }

        return true;
    }

    public function all(): array
    {
        return $this->data;
    }

    public function merge(array $data): void
    {
        $this->data = $this->deepMerge($this->data, $data);
    }

    private function deepMerge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (
                isset($base[$key])
                && is_array($base[$key])
                && is_array($value)
            ) {
                $base[$key] = $this->deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
