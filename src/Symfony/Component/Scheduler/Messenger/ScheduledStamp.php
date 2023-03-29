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

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;
use Symfony\Component\Scheduler\RecurringMessage;

/**
 * @experimental
 */
final class ScheduledStamp implements NonSendableStampInterface
{
    public function __construct(public readonly RecurringMessage $recurringMessage)
    {
    }
}
