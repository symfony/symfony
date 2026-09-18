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
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * See: https://w3c.github.io/web-performance/specs/HAR/Overview.html.
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class HarFileResponseFactory
{
    private array $consumed = [];

    public function __construct(private string $archiveFile)
    {
    }

    public function setArchiveFile(string $archiveFile): void
    {
        $this->archiveFile = $archiveFile;
        $this->consumed = [];
    }

    public function __invoke(string $method, string $url, array $options): ResponseInterface
    {
        $har = HarFile::fromFile($this->archiveFile);
        $this->consumed[] = $index = $har->findEntryIndex($method, $url, \is_string($options['body'] ?? null) ? $options['body'] : null, $this->consumed);

        return $har->createResponse($index);
    }
}
