Form Component
==============

Form provides tools for defining forms, rendering and binding request data to
related models. Furthermore it provides integration with the Validation
component.

Resources
---------

Silex integration:

https://github.com/fabpot/Silex/blob/master/src/Silex/Provider/FormServiceProvider.php

Documentation:

http://symfony.com/doc/2.0/book/forms.html

Resources
---------

You can run the unit tests with the following command:

    phpunit -c src/Symfony/Component/Form/

If you also want to run the unit tests that depend on other Symfony
Components, declare the following environment variables before running
PHPUnit:

    export SYMFONY_EVENT_DISPATCHER=../path/to/EventDispatcher
    export SYMFONY_LOCALE=../path/to/Locale
    export SYMFONY_VALIDATOR=../path/to/Validator
    export SYMFONY_HTTP_FOUNDATION=../path/to/HttpFoundation
