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

use Symfony\Component\Messenger\Worker;

/**
 * Dispatched after the execution strategy of the worker completed messages that were started earlier.
 *
 * Only the strategies that start messages before the previous ones complete,
 * the ones implementing InFlightMessageCountAwareInterface, trigger it.
 *
 * @author Jonathan H. Wage
 */
final class WorkerMessagesCompletedEvent
{
    public function __construct(
        private Worker $worker,
    ) {
    }

    public function getWorker(): Worker
    {
        return $this->worker;
    }
}
