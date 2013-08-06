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

You can run the unit tests with the following command:

    $ cd path/to/Symfony/Component/EventDispatcher/
    $ composer.phar install --dev
    $ phpunit
