CHANGELOG
=========

3.0.0
-----

 * removed `ReferenceDumper` class
 * [BC BREAK] Any `callable` is now an allowed argument for methods `ExprBuilder::always()`,
   `ExprBuilder::ifTrue()`, `ExprBuilder::then()`, `NormalizationBuilder::before()` and
   `ValidationBuilder::rule()` instead of just closures. The BC break only affects custom
   implementations of these methods in extended builders, where the method argument type hint
   needs to be changed from `\Closure` to `callable`.

2.7.0
-----

 * added `ConfigCacheInterface`, `ConfigCacheFactoryInterface` and a basic `ConfigCacheFactory`
   implementation to delegate creation of ConfigCache instances
   
2.2.0
-----

 * added `ArrayNodeDefinition::canBeEnabled()` and `ArrayNodeDefinition::canBeDisabled()`
   to ease configuration when some sections are respectively disabled / enabled
   by default.
 * added a `normalizeKeys()` method for array nodes (to avoid key normalization)
 * added numerical type handling for config definitions
 * added convenience methods for optional configuration sections to `ArrayNodeDefinition`
 * added a utils class for XML manipulations

2.1.0
-----

 * added a way to add documentation on configuration
 * implemented `Serializable` on resources
 * `LoaderResolverInterface` is now used instead of `LoaderResolver` for type
   hinting
