<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Debug;

use Symfony\Bundle\FrameworkBundle\ContainerAwareEventDispatcher;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Extends the ContainerAwareEventDispatcher to add some debugging tools.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TraceableEventDispatcher extends ContainerAwareEventDispatcher implements TraceableEventDispatcherInterface
{
    protected $logger;
    protected $called;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     * @param LoggerInterface    $logger    A LoggerInterface instance
     */
    public function __construct(ContainerInterface $container, LoggerInterface $logger = null)
    {
        parent::__construct($container);

        $this->logger = $logger;
        $this->called = array();
    }

    /**
     * {@inheritDoc}
     */
    protected function triggerListener($listener, $eventName, Event $event)
    {
        parent::triggerListener($listener, $eventName, $event);

        $listenerString = $this->listenerToString($listener, $eventName);

        if (null !== $this->logger) {
            $this->logger->debug(sprintf('Notified event "%s" to listener "%s"', $eventName, $listenerString));
        }

        $this->called[$eventName.'.'.$listenerString] = array(
            'event'    => $eventName,
            'listener' => $listenerString,
        );

        if ($event->isPropagationStopped() && null !== $this->logger) {
            $this->logger->debug(sprintf('Listener "%s" stopped propagation of the event "%s"', $this->listenerToString($listener, $eventName), $eventName));

            $skippedListeners = $this->getListeners($eventName);
            $skipped = false;

            foreach ($skippedListeners as $skippedListener) {
                if ($skipped) {
                    $this->logger->debug(sprintf('Listener "%s" was not called for event "%s"', $this->listenerToString($skippedListener, $eventName), $eventName));
                }

                if ($skippedListener === $listener) {
                    $skipped = false;
                }
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getCalledListeners()
    {
        return $this->called;
    }

    /**
     * {@inheritDoc}
     */
    public function getNotCalledListeners()
    {
        $notCalled = array();

        foreach (array_keys($this->getListeners()) as $name) {
            foreach ($this->getListeners($name) as $listener) {
                $listener = $this->listenerToString($listener, $name);

                if (!isset($this->called[$name.'.'.$listener])) {
                    $notCalled[] = array(
                        'event'    => $name,
                        'listener' => $listener,
                    );
                }
            }
        }

        return $notCalled;
    }

    protected function listenerToString($listener, $eventName)
    {
        if (is_object($listener)) {
            if ($listener instanceof \Closure) {
                return 'Closure';
            }

            return get_class($listener).'::'.$eventName;
        }

        if (is_array($listener)) {
            return is_object($listener[0]) ? sprintf('%s::%s', get_class($listener[0]), $listener[1]) : implode('::', $listener);
        }
    }
}
