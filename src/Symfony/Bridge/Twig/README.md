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

    export HTTP_TWIG=../path/to/Twig
    export HTTP_FORM=../path/to/Form
    export HTTP_TRANSLATION=../path/to/Translation
    export HTTP_EVENT_DISPATCHER=../path/to/EventDispatcher
    export HTTP_LOCALE=../path/to/Locale
