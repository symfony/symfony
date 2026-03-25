<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Profiler;

/**
 * Redacts sensitive values from profiler JSON output.
 *
 * @internal
 */
final class ProfilerJsonRedactor
{
    private const REDACTED = '***REDACTED***';

    private const SENSITIVE_HEADERS = [
        'authorization',
        'cookie',
        'proxy-authorization',
        'set-cookie',
        'x-api-key',
        'x-auth-token',
    ];

    private const SENSITIVE_ENV_PATTERNS = [
        'SECRET',
        'KEY',
        'PASSWORD',
        'PASSPHRASE',
        'TOKEN',
        'BEARER',
        'AUTH',
        'CREDENTIAL',
        'PRIVATE',
        'DSN',
    ];

    /**
     * Redacts sensitive values from a headers array.
     *
     * @param array<string, mixed> $headers
     *
     * @return array<string, mixed>
     */
    public static function redactHeaders(array $headers): array
    {
        foreach ($headers as $name => $value) {
            if (\in_array(strtolower($name), self::SENSITIVE_HEADERS, true)) {
                $headers[$name] = self::REDACTED;
            }
        }

        return $headers;
    }

    /**
     * Redacts all values in an array (for cookies, session attributes).
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    public static function redactAll(array $data): array
    {
        return array_fill_keys(array_keys($data), self::REDACTED);
    }

    /**
     * Redacts values whose keys match sensitive patterns (for server vars, dotenv).
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function redactByKeyPattern(array $data): array
    {
        foreach ($data as $key => $value) {
            $upperKey = strtoupper($key);
            foreach (self::SENSITIVE_ENV_PATTERNS as $pattern) {
                if (str_contains($upperKey, $pattern)) {
                    $data[$key] = self::REDACTED;
                    continue 2;
                }
            }

            if (\is_string($value) && self::looksLikeConnectionString($value)) {
                $data[$key] = self::REDACTED;
            } elseif (\is_array($value)) {
                $data[$key] = self::redactByKeyPattern($value);
            }
        }

        return $data;
    }

    /**
     * Detects connection strings containing credentials (e.g., mysql://user:pass@host/db).
     */
    private static function looksLikeConnectionString(string $value): bool
    {
        return (bool) preg_match('#^[a-z][a-z+\-\.]+://[^/:]+:[^@]+@#i', $value);
    }
}
