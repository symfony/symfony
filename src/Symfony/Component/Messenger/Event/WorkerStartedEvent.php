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
 * Dispatched when a worker has been started.
 *
 * @author Tobias Schultze <http://tobion.de>
 */
final class WorkerStartedEvent
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
