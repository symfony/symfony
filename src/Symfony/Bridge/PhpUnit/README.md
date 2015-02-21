PHPUnit Bridge
==============

Provides utilities for PHPUnit, especially user deprecation notices management.

It comes with the following features:

 * disable the garbage collector;
 * auto-register `class_exists` to load Doctrine annotations;
 * print a user deprecation notices summary at the end of the test suite.

Handling user deprecation notices is sensitive to the SYMFONY_DEPRECATIONS_HELPER
environment variable. This env var configures 3 behaviors depending on its value:

 * when set to `strict`, all but legacy-tagged deprecation notices will make tests
   fail. This is the recommended mode for best forward compatibility.
 * `weak` on the contrary will make tests ignore all deprecation notices.
   This is the recommended mode for legacy projects that must use deprecated
   interfaces for backward compatibility reasons.
 * any other value will respect the current error reporting level.

All three modes will display a summary of deprecation notices at the end of the
test suite, split in two groups:

 * **Legacy** deprecation notices denote tests that explicitly test some legacy
   interfaces. In all 3 modes, deprecation notices triggered in a legacy-tagged
   test do never make a test fail. There are four ways to mark a test as legacy:
    - make its class start with the `Legacy` prefix;
    - make its method start with `testLegacy`;
    - make its data provider start with `provideLegacy` or `getLegacy`;
    - add the `@group legacy` annotation to its class or method.
 * **Remaining/Other** deprecation notices are all other (non-legacy)
   notices, grouped by message, test class and method.

Usage
-----

Add this bridge to the `require-dev` section of your composer.json file
(not in `require`) with e.g.
`composer require --dev "symfony/phpunit-bridge"`.

When running `phpunit`, you will see a summary of deprecation notices at the end
of the test suite.

Deprecation notices in the **Remaining/Other** section need some thought.
You have to decide either to:

 * update your code to not use deprecated interfaces anymore, thus gaining better
   forward compatibility;
 * or move them to the **Legacy** section (by using one of the above way).

After reviewing them, you should silence deprecations in the **Legacy** section
if you think they are triggered by tests dedicated to testing deprecated
interfaces. To do so, add the following line at the beginning of your legacy
test case or in the `setUp()` method of your legacy test class:
`$this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);`

Last but not least, you should then configure your C.I. to the reporting mode
that is appropriated to your project by setting SYMFONY_DEPRECATIONS_HELPER to
`strict`, `weak` or empty. It is recommended to start with `weak` mode, upgrade
your code as described above, then when the *Remaining/Other* sections are empty,
move to `strict` to keep forward compatibility on the long run.
