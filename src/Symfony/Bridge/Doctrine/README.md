Doctrine Bridge
===============

Provides integration for [Doctrine](http://www.doctrine-project.org/) with
various Symfony2 components.

Resources
---------

You can run the unit tests with the following command:

    phpunit -c src/Symfony/Bridge/Doctrine/

If you also want to run the unit tests that depend on other Symfony
Components, declare the following environment variables before running
PHPUnit:

    export DOCTRINE_COMMON=../path/to/doctrine-common
    export DOCTRINE_DBAL=../path/to/doctrine-dbal
    export DOCTRINE_ORM=../path/to/doctrine
    export DOCTRINE_FIXTURES=../path/to/doctrine-fixtures
    export SYMFONY_HTTP_FOUNDATION=../path/to/HttpFoundation
    export SYMFONY_DEPENDENCY_INJECTION=../path/to/DependencyInjection
    export SYMFONY_FORM=../path/to/Form
    export SYMFONY_SECURITY=../path/to/Security
    export SYMFONY_VALIDATOR=../path/to/Validator
    export SYMFONY_HTTP_KERNEL=../path/to/HttpKernel
    export SYMFONY_EVENT_DISPATCHER=../path/to/EventDispatcher
