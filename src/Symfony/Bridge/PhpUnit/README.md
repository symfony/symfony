PHPUnit Bridge
==============

Provides utilities for PHPUnit, especially user deprecation notices management.

It comes with the following features:

 * disable the garbage collector;
 * auto-register `class_exists` to load Doctrine annotations;
 * print a user deprecation notices summary at the end of the test suite.

By default any non-legacy-tagged deprecation notice will make tests fail.
This can be changed by setting the SYMFONY_DEPRECATIONS_HELPER environment
variable to `weak`. This will make the bridge ignore deprecation notices and
is useful to projects that must use deprecated interfaces for backward
compatibility reasons.

A summary of deprecation notices is displayed at the end of the test suite:

 * **Legacy** deprecation notices denote tests that explicitly test some legacy
   interfaces. There are four ways to mark a test as legacy:
    - make its class start with the `Legacy` prefix;
    - make its method start with `testLegacy`;
    - make its data provider start with `provideLegacy` or `getLegacy`;
    - add the `@group legacy` annotation to its class or method.
 * **Remaining/Other** deprecation notices are all other (non-legacy)
   notices, grouped by message, test class and method.

Usage
-----

Add this bridge to the `require-dev` section of your composer.json file
(not in `require`) with e.g. `composer require --dev "symfony/phpunit-bridge"`.

When running `phpunit`, you will see a summary of deprecation notices at the end
of the test suite.

Deprecation notices in the **Remaining/Other** section need some thought.
You have to decide either to:

 * update your code to not use deprecated interfaces anymore, thus gaining better
   forward compatibility;
 * or move them to the **Legacy** section (by using one of the above way).
