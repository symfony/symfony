BrowserKit Component
====================

BrowserKit simulates the behavior of a web browser.

The component only provide an abstract client and does not provide any
"default" backend for the HTTP layer.

Resources
---------

For a simple implementation of a browser based on an HTTP layer, have a look
at [Goutte](https://github.com/fabpot/Goutte).

For an implementation based on HttpKernelInterface, have a look at the
[Client](https://github.com/symfony/symfony/blob/master/src/Symfony/Component/HttpKernel/Client.php)
provided by the HttpKernel component.

You can run the unit tests with the following command:

    phpunit

If you also want to run the unit tests that depend on other Symfony
Components, install dev dependencies before running PHPUnit:

    php composer.phar install --dev
