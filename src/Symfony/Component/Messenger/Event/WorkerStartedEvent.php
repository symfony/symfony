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
    private $worker;

    public function __construct(Worker $worker)
    {
        $this->worker = $worker;
    }

    public function getWorker(): Worker
    {
        return $this->worker;
    }
}
