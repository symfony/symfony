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
 */
final readonly class MessageContext
{
    public function __construct(
        public string $name,
        public string $id,
        public TriggerInterface $trigger,
        public \DateTimeImmutable $triggeredAt,
        public ?\DateTimeImmutable $nextTriggerAt = null,
    ) {
    }
}
