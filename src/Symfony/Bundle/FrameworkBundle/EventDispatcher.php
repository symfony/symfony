<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle;

use Symfony\Component\EventDispatcher\EventDispatcher as BaseEventDispatcher;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * This EventDispatcher automatically gets the kernel listeners injected
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class EventDispatcher extends BaseEventDispatcher
{
    protected $container;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function registerKernelListeners(array $listeners)
    {
        $this->listeners = $listeners;
    }

    /**
     * {@inheritdoc}
     */
    public function notify(Event $event)
    {
        foreach ($this->getListeners($event->getName()) as $listener) {
            if (is_array($listener) && is_string($listener[0])) {
                $listener[0] = $this->container->get($listener[0]);
            }
            call_user_func($listener, $event);
        }

        return $event;
    }

    /**
     * {@inheritdoc}
     */
    public function notifyUntil(Event $event)
    {
        foreach ($this->getListeners($event->getName()) as $listener) {
            if (is_array($listener) && is_string($listener[0])) {
                $listener[0] = $this->container->get($listener[0]);
            }
            if (call_user_func($listener, $event)) {
                $event->setProcessed(true);
                break;
            }
        }

        return $event;
    }

    /**
     * {@inheritdoc}
     */
    public function filter(Event $event, $value)
    {
        foreach ($this->getListeners($event->getName()) as $listener) {
            if (is_array($listener) && is_string($listener[0])) {
                $listener[0] = $this->container->get($listener[0]);
            }
            $value = call_user_func($listener, $event, $value);
        }

        $event->setReturnValue($value);

        return $event;
    }
}
