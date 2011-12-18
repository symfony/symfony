EventDispatcher Component
=========================

EventDispatcher implements a lightweight version of the Observer design
pattern.

    use Symfony\Component\EventDispatcher\EventDispatcher;
    use Symfony\Component\EventDispatcher\Event;

    $dispatcher = new EventDispatcher();

    $dispatcher->addListener('event_name', function (Event $event) {
        // ...
    });

    $dispatcher->dispatch('event_name');

Resources
---------

Unit tests:

https://github.com/symfony/symfony/tree/master/tests/Symfony/Tests/Component/EventDispatcher
