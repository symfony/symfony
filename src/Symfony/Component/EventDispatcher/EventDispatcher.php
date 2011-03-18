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
 */
class EventDispatcher implements EventDispatcherInterface
{
    /**
     * Map of registered listeners.
     * <event> => (<objecthash> => <listener>)
     *
     * @var array
     */
    private $listeners = array();

    /**
     * Map of priorities by the object hashes of their listeners.
     * <event> => (<objecthash> => <priority>)
     *
     * This property is used for listener sorting.
     *
     * @var array
     */
    private $priorities = array();

    /**
     * Stores which event listener lists are currently sorted.
     * <event> => <sorted>
     *
     * @var array
     */
    private $sorted = array();

    /**
     * @see EventDispatcherInterface::dispatch
     */
    public function dispatch($eventName, Event $event = null)
    {
        if (isset($this->listeners[$eventName])) {
            if (null === $event) {
                $event = new Event();
            }

            $this->sortListeners($eventName);

            foreach ($this->listeners[$eventName] as $listener) {
                $this->triggerListener($listener, $eventName, $event);

                if ($event->isPropagationStopped()) {
                    break;
                }
            }
        }
    }

    /**
     * @see EventDispatcherInterface::getListeners
     */
    public function getListeners($eventName = null)
    {
        if ($eventName) {
            $this->sortListeners($eventName);

            return $this->listeners[$eventName];
        }

        foreach ($this->listeners as $eventName => $listeners) {
            $this->sortListeners($eventName);
        }

        return $this->listeners;
    }

    /**
     * @see EventDispatcherInterface::hasListeners
     */
    public function hasListeners($eventName)
    {
        return isset($this->listeners[$eventName]) && $this->listeners[$eventName];
    }

    /**
     * @see EventDispatcherInterface::addListener
     */
    public function addListener($eventNames, $listener, $priority = 0)
    {
        // Picks the hash code related to that listener
        $hash = spl_object_hash($listener);

        foreach ((array) $eventNames as $eventName) {
            if (!isset($this->listeners[$eventName])) {
                $this->listeners[$eventName] = array();
                $this->priorities[$eventName] = array();
            }

            // Prevents duplicate listeners on same event (same instance only)
            $this->listeners[$eventName][$hash] = $listener;
            $this->priorities[$eventName][$hash] = $priority;
            $this->sorted[$eventName] = false;
        }
    }

    /**
     * @see EventDispatcherInterface::removeListener
     */
    public function removeListener($eventNames, $listener)
    {
        // Picks the hash code related to that listener
        $hash = spl_object_hash($listener);

        foreach ((array) $eventNames as $eventName) {
            // Check if actually have this listener associated
            if (isset($this->listeners[$eventName][$hash])) {
                unset($this->listeners[$eventName][$hash]);
                unset($this->priorities[$eventName][$hash]);
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
     * Triggers the listener method for an event.
     *
     * This method can be overridden to add functionality that is executed
     * for each listener.
     *
     * @param object $listener The event listener on which to invoke the listener method.
     * @param string $eventName The name of the event to dispatch. The name of the event is
     *                          the name of the method that is invoked on listeners.
     * @param Event $event The event arguments to pass to the event handlers/listeners.
     */
    protected function triggerListener($listener, $eventName, Event $event)
    {
        if ($listener instanceof \Closure) {
            $listener->__invoke($event);
        } else {
            $listener->$eventName($event);
        }
    }

    /**
     * Sorts the internal list of listeners for the given event by priority.
     *
     * Calling this method multiple times will not cause overhead unless you
     * add new listeners. As long as no listener is added, the list for an
     * event name won't be sorted twice.
     *
     * @param string $event The name of the event.
     */
    private function sortListeners($eventName)
    {
        if (!$this->sorted[$eventName]) {
            $p = $this->priorities[$eventName];

            uasort($this->listeners[$eventName], function ($a, $b) use ($p) {
                $order = $p[spl_object_hash($b)] - $p[spl_object_hash($a)];

                // for the same priority, force the first registered one to stay first
                return 0 === $order ? 1 : $order;
            });

            $this->sorted[$eventName] = true;
        }
    }
}
 