FeatureFlag Component
=====================

The FeatureFlag component allows you to split the code execution flow by
enabling features depending on context.

It provides a service that checks if a feature is enabled. Each feature is
defined by a callable function that returns a value.
The feature is enabled if the value matches the expected one (mostly a boolean
but not limited to).

**This Component is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
