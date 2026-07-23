<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
interface JsonPathCrawlerInterface
{
    /**
     * @param resource|string $raw
     */
    public function crawl(mixed $raw): JsonCrawlerInterface;
}
