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

use Symfony\Bundle\FrameworkBundle\ContainerAwareEventManager;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Debug\TraceableEventManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\Common\EventArgs;

/**
 * Extends the ContainerAwareEventManager to add some debugging tools.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TraceableEventManager extends ContainerAwareEventManager implements TraceableEventManagerInterface
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
    public function dispatchEvent($eventName, EventArgs $eventArgs = null)
    {
        parent::dispatchEvent($eventName, $eventArgs);
    }

    /**
     * {@inheritDoc}
     */
    public function notify(EventInterface $event)
    {
        foreach ($this->getListeners($event->getName()) as $listener) {
            if (is_array($listener) && is_string($listener[0])) {
                $listener[0] = $this->container->get($listener[0]);
            }

            $this->addCall($event, $listener, 'notify');

            call_user_func($listener, $event);
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

    protected function addCall(EventInterface $event, $listener, $type)
    {
        $listener = $this->listenerToString($listener);

        if (null !== $this->logger) {
            $this->logger->debug(sprintf('Notified event "%s" to listener "%s" (%s)', $event->getName(), $listener, $type));
        }

        $this->called[$event->getName().'.'.$listener] = array(
            'event'    => $event->getName(),
            'listener' => $listener,
        );
    }
}
