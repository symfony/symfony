Twig Bridge
===========

Provides integration for [Twig](http://twig.sensiolabs.org/) with various
Symfony2 components.

Resources
---------

You can run the unit tests with the following command:

    phpunit -c src/Symfony/Bridge/Twig/

If you also want to run the unit tests that depend on other Symfony
Components, declare the following environment variables before running
PHPUnit:

    export TWIG=../path/to/Twig
    export SYMFONY_EVENT_DISPATCHER=../path/to/EventDispatcher
    export SYMFONY_FORM=../path/to/Form
    export SYMFONY_LOCALE=../path/to/Locale
    export SYMFONY_TEMPLATING=../path/to/Templating
    export SYMFONY_TRANSLATION=../path/to/Translation
