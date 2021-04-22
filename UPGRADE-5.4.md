UPGRADE FROM 5.3 to 5.4
=======================

Cache
-----

 * Deprecate `DoctrineProvider` because this class has been added to the `doctrine/cache` package`

FrameworkBundle
---------------

 * Deprecate the `AdapterInterface` autowiring alias, use `CacheItemPoolInterface` instead
 * Deprecate the public `profiler` service to private

HttpKernel
----------

 * Deprecate `AbstractTestSessionListener::getSession` inject a session in the request instead
