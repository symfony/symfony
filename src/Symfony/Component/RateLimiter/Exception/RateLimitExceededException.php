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
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @experimental in 5.2
 */
class RateLimitExceededException extends \RuntimeException
{
    private $limit;

    public function __construct(Limit $limit, $code = 0, \Throwable $previous = null)
    {
        parent::__construct('Rate Limit Exceeded', $code, $previous);

        $this->limit = $limit;
    }

    public function getLimit(): Limit
    {
        return $this->limit;
    }

    public function getRetryAfter(): \DateTimeImmutable
    {
        return $this->limit->getRetryAfter();
    }

    public function getRemainingTokens(): int
    {
        return $this->limit->getRemainingTokens();
    }
}
