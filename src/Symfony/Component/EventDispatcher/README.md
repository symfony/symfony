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

    phpunit

If you also want to run the unit tests that depend on other Symfony
Components, install dev dependencies before running PHPUnit:

    php composer.phar install --dev
