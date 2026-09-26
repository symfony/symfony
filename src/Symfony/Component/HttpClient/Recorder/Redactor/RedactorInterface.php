<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Recorder\Redactor;

/**
 * Strips sensitive data before it is written to (or matched against) a recorded HAR file.
 *
 * The same transformation is applied both when recording and when replaying, so a
 * deterministic redaction never breaks request matching.
 */
interface RedactorInterface
{
    public function redactUrl(string $url): string;

    /**
     * Implementations may also rewrite header values that embed URLs (e.g. Location).
     *
     * @param array<string, string[]> $headers
     *
     * @return array<string, string[]>
     */
    public function redactHeaders(array $headers): array;

    public function redactBody(?string $body): ?string;
}
