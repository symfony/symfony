CHANGELOG
=========

4.0.0
-----

 * support for the `testLegacy` prefix in method names to mark a test as legacy
   has been dropped, use the `@group legacy` notation instead
 * support for the `Legacy` prefix in class names to mark tests as legacy has
   been dropped, use the `@group legacy` notation instead
 * support for passing an array of mocked namespaces not indexed by the mock
   feature to the constructor of the `SymfonyTestsListenerTrait` class was
   dropped
