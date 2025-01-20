<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Exception;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class ReserveNotSupportedException extends \BadMethodCallException
{
    public function __construct(string $limiterClass, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('Reserving tokens is not supported by "%s".', $limiterClass), $code, $previous);
    }
}
