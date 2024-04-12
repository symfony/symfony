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

Getting Started
---------------

```bash
composer require symfony/feature-flag
```

```php
use Symfony\Component\FeatureFlag\FeatureChecker;
use Symfony\Component\FeatureFlag\FeatureRegistry;

// Declare features
final class XmasFeature
{
    public function __invoke(): bool
    {
        return date('m-d') === '12-25';
    }
}

$features = new FeatureRegistry([
    'weekend' => fn() => date('N') >= 6,
    'xmas' => new XmasFeature(), // could be any callable
    'universe' => fn() => 42,
    'random' => fn() => random_int(1, 3),
];

// Create the feature checker
$featureChecker = new FeatureChecker($features);

// Check if a feature is enabled
$featureChecker->isEnabled('weekend'); // returns true on weekend
$featureChecker->isDisabled('weekend'); // returns true from monday to friday

// Check a not existing feature
$featureChecker->isEnabled('not_a_feature'); // returns false

// Check if a feature is enabled using an expected value
$featureChecker->isEnabled('universe'); // returns false
$featureChecker->isEnabled('universe', 7); // returns false
$featureChecker->isEnabled('universe', 42); // returns true

// Retrieve a feature value
$featureChecker->getValue('random'); // returns 1, 2 or 3
$featureChecker->getValue('random'); // returns the same value as above
```

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
