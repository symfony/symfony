<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Amqp\Transport;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Apply this stamp to set the per-message priority on an AMQP queue.
 *
 * The queue MUST be declared with the "x-max-priority" argument for the broker to honour this value.
 *
 * @author Valentin Nazarov <i.kozlice@protonmail.com>
 */
final class AmqpPriorityStamp implements StampInterface
{
    public function __construct(
        public readonly int $priority,
    ) {
    }
}
