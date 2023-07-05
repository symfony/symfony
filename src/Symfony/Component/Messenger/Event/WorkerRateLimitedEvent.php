<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Event;

use Symfony\Component\RateLimiter\LimiterInterface;

/**
 * Dispatched after the worker has been blocked due to a configured rate limiter.
 * Can be used to reset the rate limiter.
 *
 * @author Bob van de Vijver
 */
final class WorkerRateLimitedEvent
{
    public function __construct(private LimiterInterface $limiter, private string $transportName)
    {
    }

    public function getLimiter(): LimiterInterface
    {
        return $this->limiter;
    }

    public function getTransportName(): string
    {
        return $this->transportName;
    }
}
