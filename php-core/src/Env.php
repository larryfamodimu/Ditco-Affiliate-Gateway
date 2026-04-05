<?php

declare(strict_types=1);

/**
 * Env — lightweight .env file loader.
 *
 * Parses KEY=VALUE pairs, strips surrounding quotes,
 * and populates both $_ENV and putenv().
 * No external dependencies required.
 */
class Env
{
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            throw new RuntimeException("Env file not found: {$path}");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Skip comments
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            // Must contain an '='
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // Strip surrounding single or double quotes
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            // Skip if already set (environment takes precedence over .env file)
            if (!array_key_exists($key, $_ENV) && getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        $val = $_ENV[$key] ?? getenv($key);
        return ($val !== false && $val !== null && $val !== '') ? (string) $val : $default;
    }
}
