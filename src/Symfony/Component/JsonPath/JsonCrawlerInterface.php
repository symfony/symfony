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
 *
 * @experimental
 */
interface JsonCrawlerInterface
{
    /**
     * @param resource|string|array $data
     */
    public function fromJson(mixed $data): CrawlerInterface;
}
