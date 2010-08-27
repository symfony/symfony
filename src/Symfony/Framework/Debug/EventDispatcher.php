<?php

namespace Symfony\Framework\Debug;

use Symfony\Framework\EventDispatcher as BaseEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpKernel\Log\LoggerInterface;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * EventDispatcher extends the original EventDispatcher class to add some debugging tools.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class EventDispatcher extends BaseEventDispatcher implements EventDispatcherTraceableInterface
{
    protected $logger;
    protected $called;

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger A LoggerInterface instance
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        $this->called = array();
    }

    /**
     * Notifies all listeners of a given event.
     *
     * @param Event $event A Event instance
     *
     * @return Event The Event instance
     */
    public function notify(Event $event)
    {
        foreach ($this->getListeners($event->getName()) as $listener) {
            $this->addCall($event, $listener, 'notify');

            call_user_func($listener, $event);
        }

        return $event;
    }

    /**
     * Notifies all listeners of a given event until one returns a non null value.
     *
     * @param  Event $event A Event instance
     *
     * @return Event The Event instance
     */
    public function notifyUntil(Event $event)
    {
        foreach ($this->getListeners($event->getName()) as $i => $listener) {
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
     * Filters a value by calling all listeners of a given event.
     *
     * @param  Event  $event   A Event instance
     * @param  mixed    $value   The value to be filtered
     *
     * @return Event The Event instance
     */
    public function filter(Event $event, $value)
    {
        foreach ($this->getListeners($event->getName()) as $listener) {
            $this->addCall($event, $listener, 'filter');

            $value = call_user_func($listener, $event, $value);
        }

        $event->setReturnValue($value);

        return $event;
    }

    public function getCalledEvents()
    {
        return $this->called;
    }

    public function getNotCalledEvents()
    {
        $notCalled = array();

        foreach (array_keys($this->listeners) as $name) {
            foreach ($this->getListeners($name) as $listener) {
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
