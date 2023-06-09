UPGRADE FROM 6.3 to 6.4
=======================

DependencyInjection
-------------------

 * Deprecate `ContainerAwareInterface` and `ContainerAwareTrait`, use dependency injection instead

DoctrineBridge
--------------

 * Deprecate `DbalLogger`, use a middleware instead
 * Deprecate not constructing `DoctrineDataCollector` with an instance of `DebugDataHolder`
 * Deprecate `DoctrineDataCollector::addLogger()`, use a `DebugDataHolder` instead
 * Deprecate `ContainerAwareLoader`, use dependency injection in your fixtures instead

HttpFoundation
--------------

 * Make `HeaderBag::getDate()`, `Response::getDate()`, `getExpires()` and `getLastModified()` return a `DateTimeImmutable`
