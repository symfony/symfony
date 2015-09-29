Security Component - CSRF
=========================

The Security CSRF (cross-site request forgery) component provides a class
`CsrfTokenManager` for generating and validating CSRF tokens.

Resources
---------

Documentation:

https://symfony.com/doc/2.8/book/security.html

Tests
-----

You can run the unit tests with the following command:

    $ cd path/to/Symfony/Component/Security/Csrf/
    $ composer.phar install --dev
    $ phpunit
