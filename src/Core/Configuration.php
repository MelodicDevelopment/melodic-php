<?php

declare(strict_types=1);

namespace Melodic\Core;

use RuntimeException;

/**
 * JSON-backed configuration with dot-notation access.
 *
 * Note: keys are split on "." for nesting, so a dot is always a path separator.
 * A JSON key that itself contains a literal dot (e.g. "app.name" as a single
 * key) is not addressable via get()/has(); nest such values instead.
 */
class Configuration
{
    /** @var array<string, mixed> */
    private array $data;

    /** @param array<string, mixed> $data */
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

        if (!is_array($decoded)) {
            throw new RuntimeException("Configuration file '{$path}' must contain a JSON object.");
        }

        $this->merge(self::resolveEnvironment($decoded));
    }

    /**
     * Replace `${VAR}` / `${VAR:-default}` placeholders in string values with
     * environment variables, so secrets can live in the environment instead
     * of in JSON files:
     *
     *     "database": { "password": "${DB_PASSWORD}" }
     *
     * A placeholder whose variable is unset and has no default is left
     * untouched (so a literal "${...}" in existing config keeps its value).
     * The default also applies when the variable is set but empty. Names
     * starting with HTTP_ are never resolved (they are request headers under a
     * web server) and behave as unset. Values are
     * strings, except that a value consisting of a single placeholder that
     * resolves to "true" or "false" becomes a boolean.
     *
     * @param array<mixed> $data
     * @return array<mixed>
     */
    private static function resolveEnvironment(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::resolveEnvironment($value);
            } elseif (is_string($value) && str_contains($value, '${')) {
                // A value that is exactly one placeholder resolving to
                // "true"/"false" becomes a real boolean — otherwise
                // `"debug": "${APP_DEBUG}"` with APP_DEBUG=false would be the
                // truthy string "false".
                $wholeValue = preg_match('/^\$\{[^}]+\}$/', $value) === 1;

                $data[$key] = preg_replace_callback(
                    '/\$\{([A-Za-z_][A-Za-z0-9_]*)(?::-([^}]*))?\}/',
                    static function (array $m): string {
                        // Under a web SAPI, HTTP_* names are request headers
                        // (getenv() returns them too), so a client could set
                        // the value. They never resolve; $_SERVER is not
                        // consulted for the same reason.
                        $env = stripos($m[1], 'HTTP_') === 0 ? null : getenv($m[1]);

                        if ($env === false) {
                            $env = $_ENV[$m[1]] ?? null;
                        }

                        // ":-" follows shell semantics: the default applies
                        // when the variable is unset *or empty*.
                        if (is_string($env) && ($env !== '' || $m[2] === null)) {
                            return $env;
                        }

                        return $m[2] ?? $m[0];
                    },
                    $value,
                    flags: PREG_UNMATCHED_AS_NULL,
                );

                if ($wholeValue && is_string($data[$key]) && in_array(strtolower($data[$key]), ['true', 'false'], true)) {
                    $data[$key] = strtolower($data[$key]) === 'true';
                }
            }
        }

        return $data;
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

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->data;
    }

    /** @param array<string, mixed> $data */
    public function merge(array $data): void
    {
        $this->data = $this->deepMerge($this->data, $data);
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function deepMerge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (
                isset($base[$key])
                && is_array($base[$key])
                && is_array($value)
                // Lists replace wholesale — recursing by index would leave
                // stale trailing base elements when the override is shorter
                // (e.g. base allowedOrigins [a, b] overridden by [qa] must
                // yield [qa], not [qa, b]). Only associative maps merge.
                // Empty arrays are ambiguous in decoded JSON ({} === []), so
                // they count as maps: an empty override never clobbers a map.
                && !($base[$key] !== [] && array_is_list($base[$key]))
                && !($value !== [] && array_is_list($value))
            ) {
                $base[$key] = $this->deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
