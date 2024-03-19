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
 * Dispatched after the worker processed a message.
 *
 * @author Jeroen Spee <https://github.com/Jeroeny>
 */
final class WorkerBusyEvent extends WorkerRunningEvent
{
    public function __construct(Worker $worker)
    {
        parent::__construct($worker, false);
    }
}
