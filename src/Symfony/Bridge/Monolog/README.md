Monolog Bridge
==============

Provides integration for Monolog with various Symfony2 components.

Resources
---------

You can run the unit tests with the following command:

    phpunit -c src/Symfony/Bridge/Monolog/

If you also want to run the unit tests that depend on other Symfony
Components, declare the following environment variables before running
PHPUnit:

    export MONOLOG=../path/to/Monolog
    export SYMFONY_HTTP_FOUNDATION=../path/to/HttpFoundation
