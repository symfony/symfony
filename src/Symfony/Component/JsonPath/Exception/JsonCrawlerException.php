<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Exception;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
class JsonCrawlerException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(string $path, string $message, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('Error while crawling JSON with JSON path "%s": %s.', $path, $message), previous: $previous);
    }
}
