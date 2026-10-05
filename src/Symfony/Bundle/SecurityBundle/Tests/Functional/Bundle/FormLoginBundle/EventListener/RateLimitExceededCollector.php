<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\FormLoginBundle\EventListener;

use Symfony\Component\RateLimiter\Event\RateLimitExceededEvent;

final class RateLimitExceededCollector
{
    /** @var RateLimitExceededEvent[] */
    public array $events = [];

    public function onRateLimitExceeded(RateLimitExceededEvent $event): void
    {
        $this->events[] = $event;
    }
}
