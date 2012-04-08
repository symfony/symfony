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

    phpunit -c src/Symfony/Component/EventDispatcher/

If you also want to run the unit tests that depend on other Symfony
Components, declare the following environment variables before running
PHPUnit:

    export SYMFONY_DEPENDENCY_INJECTION=../path/to/DependencyInjection
    export SYMFONY_HTTP_KERNEL=../path/to/HttpKernel
