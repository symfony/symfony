<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Profiler;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcherInterface;
use Symfony\Component\Profiler\DataCollector\LateDataCollectorInterface;

/**
 * EventDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class EventDataCollector implements LateDataCollectorInterface
{
    private $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher = null)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function getCollectedData()
    {
        if ($this->dispatcher instanceof TraceableEventDispatcherInterface) {
            return new EventData($this->dispatcher->getCalledListeners(), $this->dispatcher->getNotCalledListeners());
        }
    }
}
