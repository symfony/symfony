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
    private $logger;
    private $called;
    private $container;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     * @param LoggerInterface    $logger    A LoggerInterface instance
     */
    public function __construct(ContainerInterface $container, LoggerInterface $logger = null)
    {
        parent::__construct($container);

        $this->container = $container;
        $this->logger = $logger;
        $this->called = array();
    }

    /**
     * {@inheritDoc}
     *
     * @throws \RuntimeException if the listener method is not callable
     */
    public function addListener($eventNames, $listener, $priority = 0)
    {
        if (!$listener instanceof \Closure) {
            foreach ((array) $eventNames as $method) {
                if (!is_callable(array($listener, $method))) {
                    $msg = sprintf('The event method "%s()" is not callable on the class "%s".', $method, get_class($listener));
                    if (null !== $this->logger) {
                        $this->logger->err($msg);
                    }
                    throw new \RuntimeException($msg);
                }
            }
        }

        parent::addListener($eventNames, $listener, $priority);
    }

    /**
     * {@inheritDoc}
     */
    protected function triggerListener($listener, $eventName, Event $event)
    {
        parent::triggerListener($listener, $eventName, $event);

        if (null !== $this->logger) {
            $this->logger->debug(sprintf('Notified event "%s" to listener "%s".', $eventName, get_class($listener)));
        }

        $this->called[$eventName.'.'.get_class($listener)] = $this->getListenerInfo($listener, $eventName);

        if ($event->isPropagationStopped() && null !== $this->logger) {
            $this->logger->debug(sprintf('Listener "%s" stopped propagation of the event "%s".', get_class($listener), $eventName));

            $skippedListeners = $this->getListeners($eventName);
            $skipped = false;

            foreach ($skippedListeners as $skippedListener) {
                if ($skipped) {
                    $this->logger->debug(sprintf('Listener "%s" was not called for event "%s".', get_class($skippedListener), $eventName));
                }

                if ($skippedListener === $listener) {
                    $skipped = true;
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
                if (!isset($this->called[$name.'.'.get_class($listener)])) {
                    $notCalled[$name.'.'.get_class($listener)] = $this->getListenerInfo($listener, $name);
                }
            }
        }

        return $notCalled;
    }

    /**
     * Returns information about the listener
     *
     * @param object $listener  The listener
     * @param string $eventName The event name
     *
     * @return array Informations about the listener
     */
    private function getListenerInfo($listener, $eventName)
    {
        $info = array('event' => $eventName);
        if ($listener instanceof \Closure) {
            $info += array('type' => 'Closure');
        } else {
            $class = get_class($listener);
            try {
                $r = new \ReflectionMethod($class, $eventName);
                $file = $r->getFileName();
                $line = $r->getStartLine();
            } catch (\ReflectionException $e) {
                $file = null;
                $line = null;
            }
            $info += array(
                'type'  => 'Method',
                'class' => $class,
                'file'  => $file,
                'line'  => $line
            );
        }

        return $info;
    }
}
