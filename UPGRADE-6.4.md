UPGRADE FROM 6.3 to 6.4
=======================

DoctrineBridge
--------------

 * Deprecate `DbalLogger`, use a middleware instead
 * Deprecate not constructing `DoctrineDataCollector` with an instance of `DebugDataHolder`
 * Deprecate `DoctrineDataCollector::addLogger()`, use a `DebugDataHolder` instead

HttpFoundation
--------------

 * Make `HeaderBag::getDate()`, `Response::getDate()`, `getExpires()` and `getLastModified()` return a `DateTimeImmutable`
