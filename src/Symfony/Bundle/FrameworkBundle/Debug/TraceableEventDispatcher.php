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
     *
     * @throws \RuntimeException if the listener method is not callable
     */
    public function addListener($eventName, $listener, $priority = 0)
    {
        if (!is_callable($listener)) {
            if (is_string($listener)) {
                $typeDefinition = '[string] '.$listener;
            } elseif (is_array($listener)) {
                $typeDefinition = '[array] '.(is_object($listener[0]) ? get_class($listener[0]) : $listener[0]).'::'.$listener[1];
            } elseif (is_object($listener)) {
                $typeDefinition = '[object] '.get_class($listener);
            } else {
                $typeDefinition = '[?] '.var_export($listener, true);
            }

            throw new \RuntimeException(sprintf('The given callback (%s) for event "%s" is not callable.', $typeDefinition, $eventName));
        }

        parent::addListener($eventName, $listener, $priority);
    }

    /**
     * {@inheritDoc}
     */
    protected function doDispatch($listeners, $eventName, Event $event)
    {
        foreach ($listeners as $listener) {
            $info = $this->getListenerInfo($listener, $eventName);

            if (null !== $this->logger) {
                $this->logger->debug(sprintf('Notified event "%s" to listener "%s".', $eventName, $info['pretty']));
            }

            $this->called[$eventName.'.'.$info['pretty']] = $info;

            call_user_func($listener, $event);

            if ($event->isPropagationStopped()) {
                if (null !== $this->logger) {
                    $this->logger->debug(sprintf('Listener "%s" stopped propagation of the event "%s".', $info['pretty'], $eventName));

                    $skippedListeners = $this->getListeners($eventName);
                    $skipped = false;

                    foreach ($skippedListeners as $skippedListener) {
                        if ($skipped) {
                            if (is_object($skippedListener)) {
                                $typeDefinition = get_class($skippedListener);
                            } elseif (is_array($skippedListener)) {
                                if (is_object($skippedListener[0])) {
                                    $typeDefinition = get_class($skippedListener[0]);
                                } else {
                                    $typeDefinition = implode('::', $skippedListener);
                                }
                            } else {
                                $typeDefinition = $skippedListener;
                            }
                            $this->logger->debug(sprintf('Listener "%s" was not called for event "%s".', $typeDefinition, $eventName));
                        }

                        if ($skippedListener === $listener) {
                            $skipped = true;
                        }
                    }
                }

                break;
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

        foreach ($this->getListeners() as $name => $listeners) {
            foreach ($listeners as $listener) {
                $info = $this->getListenerInfo($listener, $name);
                if (!isset($this->called[$name.'.'.$info['pretty']])) {
                    $notCalled[$name.'.'.$info['pretty']] = $info;
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
            $info += array(
                'type' => 'Closure',
                'pretty' => 'closure'
            );
        } elseif (is_string($listener)) {
            try {
                $r = new \ReflectionFunction($listener);
                $file = $r->getFileName();
                $line = $r->getStartLine();
            } catch (\ReflectionException $e) {
                $file = null;
                $line = null;
            }
            $info += array(
                'type'  => 'Function',
                'function' => $listener,
                'file'  => $file,
                'line'  => $line,
                'pretty' => $listener,
            );
        } elseif (is_array($listener) || (is_object($listener) && is_callable($listener))) {
            if (!is_array($listener)) {
                $listener = array($listener, '__invoke');
            }
            $class = get_class($listener[0]);
            try {
                $r = new \ReflectionMethod($class, $listener[1]);
                $file = $r->getFileName();
                $line = $r->getStartLine();
            } catch (\ReflectionException $e) {
                $file = null;
                $line = null;
            }
            $info += array(
                'type'  => 'Method',
                'class' => $class,
                'method' => $listener[1],
                'file'  => $file,
                'line'  => $line,
                'pretty' => $class.'::'.$listener[1],
            );
        }

        return $info;
    }
}
