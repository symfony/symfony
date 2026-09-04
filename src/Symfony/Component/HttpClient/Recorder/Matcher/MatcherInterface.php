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

/**
 * @psalm-import-type HarEntry from HarFile
 */
interface MatcherInterface
{
    /**
     * @psalm-param HarEntry $harEntry
     * @psalm-param array<string, mixed> $options A prepared options array as produced by HttpClientTrait::prepareRequest()
     *                                  (body is a string when it could be normalized, normalized_headers is available when called from the recorder)
     */
    public function matches(array $harEntry, string $method, string $url, array $options): bool;
}
