<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Generator;

use Symfony\Component\Scheduler\Trigger\TriggerInterface;

/**
 * @author Tugdual Saunier <tugdual@saunier.tech>
 *
 * @experimental
 */
final class MessageContext
{
    public function __construct(
        public readonly TriggerInterface $trigger,
        public readonly \DateTimeImmutable $triggeredAt,
        public readonly \DateTimeImmutable|null $nextTriggerAt = null,
    ) {
    }
}
