<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Debug;

use Symfony\Bundle\FrameworkBundle\EventDispatcher as BaseEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Debug\EventDispatcherTraceableInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * EventDispatcher extends the original EventDispatcher class to add some debugging tools.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class EventDispatcher extends BaseEventDispatcher implements EventDispatcherTraceableInterface
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
    public function notify(Event $event)
    {
        foreach ($this->getListeners($event->getName()) as $listener) {
            if (is_array($listener) && is_string($listener[0])) {
                $listener[0] = $this->container->get($listener[0]);
            }

            $this->addCall($event, $listener, 'notify');

            call_user_func($listener, $event);
        }

        return $event;
    }

    /**
     * {@inheritDoc}
     */
    public function notifyUntil(Event $event)
    {
        foreach ($this->getListeners($event->getName()) as $i => $listener) {
            if (is_array($listener) && is_string($listener[0])) {
                $listener[0] = $this->container->get($listener[0]);
            }

            $this->addCall($event, $listener, 'notifyUntil');

            if (call_user_func($listener, $event)) {
                if (null !== $this->logger) {
                    $this->logger->debug(sprintf('Listener "%s" processed the event "%s"', $this->listenerToString($listener), $event->getName()));

                    $listeners = $this->getListeners($event->getName());
                    while (++$i < count($listeners)) {
                        $this->logger->debug(sprintf('Listener "%s" was not called for event "%s"', $this->listenerToString($listeners[$i]), $event->getName()));
                    }
                }

                $event->setProcessed(true);
                break;
            }
        }

        return $event;
    }

    /**
     * {@inheritDoc}
     */
    public function filter(Event $event, $value)
    {
        foreach ($this->getListeners($event->getName()) as $listener) {
            if (is_array($listener) && is_string($listener[0])) {
                $listener[0] = $this->container->get($listener[0]);
            }

            $this->addCall($event, $listener, 'filter');

            $value = call_user_func($listener, $event, $value);
        }

        $event->setReturnValue($value);

        return $event;
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

        foreach (array_keys($this->listeners) as $name) {
            foreach ($this->getListeners($name) as $listener) {
                if (is_array($listener) && is_string($listener[0])) {
                    $listener[0] = $this->container->get($listener[0]);
                }

                $listener = $this->listenerToString($listener);

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

    protected function listenerToString($listener)
    {
        if (is_object($listener) && $listener instanceof \Closure) {
            return 'Closure';
        }

        if (is_string($listener)) {
            return $listener;
        }

        if (is_array($listener)) {
            return sprintf('%s::%s', is_object($listener[0]) ? get_class($listener[0]) : $listener[0], $listener[1]);
        }
    }

    protected function addCall(Event $event, $listener, $type)
    {
        $listener = $this->listenerToString($listener);

        if (null !== $this->logger) {
            $this->logger->debug(sprintf('Notified event "%s" to listener "%s" (%s)', $event->getName(), $listener, $type));
        }

        $this->called[$event->getName().'.'.$listener] = array(
            'event'    => $event->getName(),
            'caller'   => null !== $event->getSubject() ? get_class($event->getSubject()) : null,
            'listener' => $listener,
        );
    }
}
