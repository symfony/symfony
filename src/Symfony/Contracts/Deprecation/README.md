Symfony Deprecation Contracts
=============================

A generic function and convention to trigger deprecation notices.

This package provides a single global function named `deprecated()`.
Its purpose is to trigger deprecations in a way that can be silenced on production environments
by using the `zend.assertions` ini setting and that can be caught during development to generate reports.

The function requires at least 3 arguments:
 - the name of the Composer package that is triggering the deprecation
 - the version of the package that introduced the deprecation
 - the message of the deprecation
 - more arguments can be provided: they will be inserted in the message using `printf()` formatting

Example:
```php
deprecated('symfony/blockchain', 8.9, 'Using "%s" is deprecated, use "%s" instead.', 'bitcoin', 'fabcoin');
```

This will generate the following message:
`Since symfony/blockchain 8.9: Using "bitcoin" is deprecated, use "fabcoin" instead.`
