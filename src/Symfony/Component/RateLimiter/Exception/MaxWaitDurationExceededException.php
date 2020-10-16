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

use Symfony\Component\RateLimiter\Limit;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @experimental in 5.2
 */
class MaxWaitDurationExceededException extends \RuntimeException
{
    private $limit;

    public function __construct(string $message, Limit $limit, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->limit = $limit;
    }

    public function getLimit(): Limit
    {
        return $this->limit;
    }
}
