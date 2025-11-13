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

use Symfony\Component\JsonPath\Exception\InvalidArgumentException;
use Symfony\Component\JsonPath\Exception\JsonCrawlerException;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
interface JsonCrawlerInterface
{
    /**
     * @return list<array|string|float|int|bool|null>
     *
     * @throws InvalidArgumentException When the JSON string provided to the crawler cannot be decoded
     * @throws JsonCrawlerException     When a syntax error occurs in the provided JSON path
     */
    public function find(string|JsonPath $query): array;
}
