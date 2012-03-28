Propel Bridge
=============

Provides integration for Propel with various Symfony2 components.

Resources
---------

You can run the unit tests with the following command:

    phpunit -c src/Symfony/Bridge/Propel/

If you also want to run the unit tests that depend on other Symfony
Components, declare the following environment variables before running
PHPUnit:

    export PROPEL1=../path/to/Propel
    export HTTP_FOUNDATION=../path/to/HttpFoundation
    export HTTP_KERNEL=../path/to/HttpKernel
    export HTTP_FORM=../path/to/Form
