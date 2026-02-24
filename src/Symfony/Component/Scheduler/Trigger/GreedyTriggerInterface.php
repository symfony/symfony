<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Trigger;

/**
 * When implemented, MessageGenerator calls getNextRunDate() *after* each yield
 * instead of before. This allows the trigger to check whether more work is
 * available once the handler has finished processing the current message.
 *
 * Returning the same time signals "process me again immediately".
 * Returning a future time or null ends the greedy burst.
 */
interface GreedyTriggerInterface extends TriggerInterface
{
}
