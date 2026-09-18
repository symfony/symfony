<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Recorder\Matcher;

use Symfony\Component\HttpClient\Har\HarFile;
use Symfony\Component\HttpClient\Recorder\Redactor\RedactorInterface;

/**
 * Matches HAR entries by comparing method, URL and body.
 * When a redactor is provided, it is applied to the live request URL and body
 * before comparing them to the recorded (already redacted) entry.
 *
 * @psalm-import-type HarEntry from HarFile
 */
final class DefaultMatcher implements MatcherInterface
{
    public function __construct(private readonly ?RedactorInterface $redactor = null)
    {
    }

    /**
     * @psalm-param HarEntry $harEntry
     */
    public function matches(array $harEntry, string $method, string $url, array $options): bool
    {
        if (($harEntry['request']['method'] ?? null) !== $method) {
            return false;
        }

        // the recorded entry holds redacted values, so the live request is redacted the same way before comparing
        if (($harEntry['request']['url'] ?? null) !== ($this->redactor?->redactUrl($url) ?? $url)) {
            return false;
        }

        $body = $options['body'] ?? null;

        if (!\is_string($body)) {
            // closures and resources cannot be compared; match on method and URL only
            return true;
        }

        return ($this->redactor?->redactBody($body) ?? $body) === HarFile::decodeContent($harEntry['request']['postData'] ?? []);
    }
}
