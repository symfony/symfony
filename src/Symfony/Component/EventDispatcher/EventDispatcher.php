<?php

/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Symfony\Component\EventDispatcher;

/**
 * The EventDispatcherInterface is the central point of Symfony's event listener system.
 *
 * Listeners are registered on the manager and events are dispatched through the
 * manager.
 *
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link    www.doctrine-project.org
 * @since   2.0
 * @version $Revision: 3938 $
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 * @author  Bernhard Schussek <bschussek@gmail.com>
 * @author  Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class EventDispatcher implements EventDispatcherInterface
{
    private $listeners = array();
    private $sorted = array();

    /**
     * @see EventDispatcherInterface::dispatch
     *
     * @api
     */
    public function dispatch($eventName, Event $event = null)
    {
        if (!isset($this->listeners[$eventName])) {
            return;
        }

        if (null === $event) {
            $event = new Event();
        }

        foreach ($this->getListeners($eventName) as $listener) {
            $this->triggerListener($listener, $event);

            if ($event->isPropagationStopped()) {
                break;
            }
        }
    }

    /**
     * @see EventDispatcherInterface::getListeners
     *
     * @api
     */
    public function getListeners($eventName = null)
    {
        if (null !== $eventName) {
            if (!isset($this->sorted[$eventName])) {
                $this->sortListeners($eventName);
            }

            return $this->sorted[$eventName];
        }

        $sorted = array();
        foreach (array_keys($this->listeners) as $eventName) {
            if (!isset($this->sorted[$eventName])) {
                $this->sortListeners($eventName);
            }

            if ($this->sorted[$eventName]) {
                $sorted[$eventName] = $this->sorted[$eventName];
            }
        }

        return $sorted;
    }

    /**
     * @see EventDispatcherInterface::hasListeners
     *
     * @api
     */
    public function hasListeners($eventName = null)
    {
        return (Boolean) count($this->getListeners($eventName));
    }

    /**
     * @see EventDispatcherInterface::addListener
     *
     * @api
     */
    public function addListener($eventNames, $listener, $priority = 0)
    {
        list($listenerId, $isObject) = $this->getListenerInfo($listener);

        foreach ((array) $eventNames as $eventName) {
            if (!isset($this->listeners[$eventName][$priority])) {
                if (!isset($this->listeners[$eventName])) {
                    $this->listeners[$eventName] = array();
                }
                $this->listeners[$eventName][$priority] = array();
            }

            if ($isObject) {
                $cListener = array($listener, $eventName);
                $cListenerId = $listenerId.'#'.$eventName;
            } else {
                $cListener = $listener;
                $cListenerId = $listenerId;
            }

            $this->listeners[$eventName][$priority][$cListenerId] = $cListener;
            unset($this->sorted[$eventName]);
        }
    }

    /**
     * @see EventDispatcherInterface::removeListener
     */
    public function removeListener($eventNames, $listener)
    {
        list($listenerId, $isObject) = $this->getListenerInfo($listener);

        foreach ((array) $eventNames as $eventName) {
            if (!isset($this->listeners[$eventName])) {
                continue;
            }

            if ($isObject) {
                $cListenerId = $listenerId.'#'.$eventName;
            } else {
                $cListenerId = $listenerId;
            }

            foreach ($this->listeners[$eventName] as $priority => $listeners) {
                if (isset($listeners[$cListenerId])) {
                    unset($this->listeners[$eventName][$priority][$cListenerId], $this->sorted[$eventName]);
                }
            }
        }
    }

    /**
     * @see EventDispatcherInterface::addSubscriber
     */
    public function addSubscriber(EventSubscriberInterface $subscriber, $priority = 0)
    {
        $this->addListener($subscriber->getSubscribedEvents(), $subscriber, $priority);
    }

    /**
     * @see EventDispatcherInterface::removeSubscriber
     */
    public function removeSubscriber(EventSubscriberInterface $subscriber)
    {
        $this->removeListener($subscriber->getSubscribedEvents(), $subscriber);
    }

    /**
     * Triggers the listener method for an event.
     *
     * This method can be overridden to add functionality that is executed
     * for each listener.
     *
     * @param object $listener The event listener on which to invoke the listener method.
     * @param Event $event The event arguments to pass to the event handlers/listeners.
     */
    protected function triggerListener($listener, Event $event)
    {
        call_user_func($listener, $event);
    }

    private function getListenerInfo($listener)
    {
        if (is_object($listener)) {
            return array(spl_object_hash($listener), !is_callable($listener));
        }

        if (!is_callable($listener)) {
            throw new \InvalidArgumentException('$listener must be an object, or a valid callable.');
        }

        if (is_array($listener)) {
            if (is_object($listener[0])) {
                return array(spl_object_hash($listener[0]).'#'.$listener[1], false);
            } else {
                return array($listener[0].'#'.$listener[1], false);
            }
        }

        // function name
        return array($listener, false);
    }

    /**
     * Sorts the internal list of listeners for the given event by priority.
     *
     * @param string $eventName The name of the event.
     */
    private function sortListeners($eventName)
    {
        $this->sorted[$eventName] = array();
        if (isset($this->listeners[$eventName])) {
            krsort($this->listeners[$eventName]);
            foreach ($this->listeners[$eventName] as $listeners) {
                foreach ($listeners as $listener) {
                    $this->sorted[$eventName][] = $listener;
                }
            }
        }
    }
}
