CHANGELOG
=========

3.3.0
-----

 * using the `testLegacy` prefix in method names to mark a test as legacy is
   deprecated, use the `@group legacy` notation instead
 * using the `Legacy` prefix in class names to mark a test as legacy is deprecated,
   use the `@group legacy` notation instead

3.1.0
-----

 * passing a numerically indexed array to the constructor of the `SymfonyTestsListenerTrait`
   is deprecated, pass an array of namespaces indexed by the mocked feature instead
