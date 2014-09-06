<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Debug;

use Symfony\Component\Stopwatch\Stopwatch;
use Psr\Log\LoggerInterface;

use Symfony\Component\EventDispatcher\ContainerAwareEventDispatcherInterface;

/**
 * Collects some data about event listeners.
 *
 * This event dispatcher delegates the dispatching to another one.
 *
 * @author Tristan Darricau <tristan@darricau.eu>
 */
class TraceableContainerAwareEventDispatcher extends TraceableEventDispatcher implements ContainerAwareEventDispatcherInterface
{
    /**
     * Constructor.
     *
     * @param ContainerAwareEventDispatcherInterface $dispatcher An EventDispatcherInterface instance
     * @param Stopwatch                              $stopwatch  A Stopwatch instance
     * @param LoggerInterface                        $logger     A LoggerInterface instance
     */
    public function __construct(ContainerAwareEventDispatcherInterface $dispatcher, Stopwatch $stopwatch, LoggerInterface $logger = null)
    {
        parent::__construct($dispatcher, $stopwatch, $logger);
    }

    /**
     * {@inheritdoc}
     */
    public function addListenerService($eventName, $callback, $priority = 0)
    {
        $this->dispatcher->addListenerService($eventName, $callback, $priority);
    }

    /**
     * {@inheritdoc}
     */
    public function addSubscriberService($serviceId, $class)
    {
        $this->dispatcher->addSubscriberService($serviceId, $class);
    }
}
