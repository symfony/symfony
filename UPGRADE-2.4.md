UPGRADE FROM 2.3 to 2.4
=======================

Form
----

 * The constructor parameter `$precision` in `IntegerToLocalizedStringTransformer`
   is now ignored completely, because a precision does not make sense for
   integers.

EventDispatcher
----------------

 * The `getDispatcher()` and `getName()` methods from `Symfony\Component\EventDispatcher\Event`
   are deprecated, the event dispatcher instance and event name can be received in the listener call instead.

    Before:

    ```php
    use Symfony\Component\EventDispatcher\Event;

    class Foo
    {
        public function myFooListener(Event $event)
        {
            $dispatcher = $event->getDispatcher();
            $eventName = $event->getName();
            $dispatcher->dispatch('log', $event);

            // ... more code
       }
    }
    ```

    After:

    ```php
    use Symfony\Component\EventDispatcher\Event;
    use Symfony\Component\EventDispatcher\EventDispatcherInterface;

    class Foo
    {
        public function myFooListener(Event $event, $eventName, EventDispatcherInterface $dispatcher)
        {
            $dispatcher->dispatch('log', $event);

            // ... more code
        }
    }
    ```
