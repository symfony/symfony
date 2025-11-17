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
 * Thrown when a string passed as an input is not a valid JSON string, e.g. in {@see JsonCrawler}.
 *
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
class InvalidJsonStringInputException extends InvalidArgumentException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('Invalid JSON input: %s.', $message), previous: $previous);
    }
}
