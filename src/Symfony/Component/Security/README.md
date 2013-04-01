Security Component
==================

Security provides an infrastructure for sophisticated authorization systems,
which makes it possible to easily separate the actual authorization logic from
so called user providers that hold the users credentials. It is inspired by
the Java Spring framework.

Resources
---------

Documentation:

http://symfony.com/doc/2.1/book/security.html

Resources
---------

You can run the unit tests with the following command:

    phpunit

If you also want to run the unit tests that depend on other Symfony
Components, install dev dependencies before running PHPUnit:

    php composer.phar install --dev
