<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Test;

use Symfony\Component\HttpClient\Har\HarFile;
use Symfony\Component\HttpClient\Recorder\Matcher\DefaultMatcher;
use Symfony\Component\HttpClient\Recorder\Matcher\MatcherInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * See: https://w3c.github.io/web-performance/specs/HAR/Overview.html.
 *
 * To replay a file recorded by RecorderHttpClient, which stores redacted values, pass a matcher aware of
 * the same redaction: new HarFileResponseFactory($file, new DefaultMatcher(new DefaultRedactor())).
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class HarFileResponseFactory
{
    private readonly MatcherInterface $matcher;
    private array $consumed = [];

    public function __construct(private string $archiveFile, ?MatcherInterface $matcher = null)
    {
        $this->matcher = $matcher ?? new DefaultMatcher();
    }

    public function setArchiveFile(string $archiveFile): void
    {
        $this->archiveFile = $archiveFile;
        $this->consumed = [];
    }

    public function __invoke(string $method, string $url, array $options): ResponseInterface
    {
        return HarFile::fromFile($this->archiveFile)->findResponse($this->matcher, $method, $url, $options, $this->consumed);
    }
}
