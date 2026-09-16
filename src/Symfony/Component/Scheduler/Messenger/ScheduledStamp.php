<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Trigger\SerializedTrigger;

final class ScheduledStamp implements StampInterface
{
    public function __construct(public readonly MessageContext $messageContext)
    {
    }

    /**
     * Triggers can hold closures or any other non-serializable state, so only their description crosses transports.
     */
    public function __serialize(): array
    {
        $context = $this->messageContext;
        $trigger = new SerializedTrigger((string) $context->trigger);

        return ['messageContext' => new MessageContext($context->name, $context->id, $trigger, $context->triggeredAt, $context->nextTriggerAt)];
    }
}
