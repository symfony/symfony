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

SecurityBundle
--------------

 * Deprecate the `always_authenticate_before_granting` option

Security
--------

 * Deprecate setting the 4th argument (`$alwaysAuthenticate`) to `true` and not setting the
   5th argument (`$exceptionOnNoToken`) to `false` of `AuthorizationChecker` (this is the default
   behavior when using `enable_authenticator_manager: true`)
 * Deprecate not setting the 5th argument (`$exceptionOnNoToken`) of `AccessListener` to `false`
   (this is the default behavior when using `enable_authenticator_manager: true`)
