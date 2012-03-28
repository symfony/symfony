Security Component
==================

Security provides an infrastructure for sophisticated authorization systems,
which makes it possible to easily separate the actual authorization logic from
so called user providers that hold the users credentials. It is inspired by
the Java Spring framework.

Resources
---------

Documentation:

http://symfony.com/doc/2.0/book/security.html

Resources
---------

You can run the unit tests with the following command:

    phpunit -c src/Symfony/Component/Security/

If you also want to run the unit tests that depend on other Symfony
Components, declare the following environment variables before running
PHPUnit:

    export SYMFONY_HTTP_FOUNDATION=../path/to/HttpFoundation
    export SYMFONY_HTTP_KERNEL=../path/to/HttpKernel
    export SYMFONY_EVENT_DISPATCHER=../path/to/EventDispatcher
    export SYMFONY_FORM=../path/to/Form
    export SYMFONY_ROUTING=../path/to/Routing
    export DOCTRINE_DBAL=../path/to/doctrine-dbal
    export DOCTRINE_COMMON=../path/to/doctrine-common
