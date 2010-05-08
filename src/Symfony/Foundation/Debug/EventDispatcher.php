<?php

namespace Symfony\Foundation\Debug;

use Symfony\Foundation\EventDispatcher as BaseEventDispatcher;
use Symfony\Components\EventDispatcher\EventDispatcherInterface;
use Symfony\Components\EventDispatcher\Event;
use Symfony\Foundation\LoggerInterface;
use Symfony\Components\DependencyInjection\ContainerInterface;

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
 * @package    Symfony
 * @subpackage Foundation
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class EventDispatcher extends BaseEventDispatcher
{
    protected $logger;

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger A LoggerInterface instance
     */
    public function __construct(ContainerInterface $container, LoggerInterface $logger = null)
    {
        $this->logger = $logger;

        parent::__construct($container);
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
            if (null !== $this->logger) {
                $this->logger->debug(sprintf('Notifying event "%s" to listener "%s"', $event->getName(), $this->listenerToString($listener)));
            }

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
        foreach ($this->getListeners($event->getName()) as $listener) {
            if (null !== $this->logger) {
                $this->logger->debug(sprintf('Notifying (until) event "%s" to listener "%s"', $event->getName(), $this->listenerToString($listener)));
            }

            if (call_user_func($listener, $event)) {
                if (null !== $this->logger) {
                    $this->logger->debug(sprintf('Listener "%s" processed the event "%s"', $this->listenerToString($listener), $event->getName()));
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
            if (null !== $this->logger) {
                $this->logger->debug(sprintf('Notifying (filter) event "%s" to listener "%s"', $event->getName(), $this->listenerToString($listener)));
            }

            $value = call_user_func($listener, $event, $value);
        }

        $event->setReturnValue($value);

        return $event;
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
            return sprintf('(%s, %s)', is_object($listener[0]) ? get_class($listener[0]) : $listener[0], $listener[1]);
        }
    }
}
