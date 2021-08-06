UPGRADE FROM 5.3 to 5.4
=======================

Cache
-----

 * Deprecate `DoctrineProvider` because this class has been added to the `doctrine/cache` package`

Finder
------

 * Deprecate `Comparator::setTarget()` and `Comparator::setOperator()`
 * Add a constructor to `Comparator` that allows setting target and operator

FrameworkBundle
---------------

 * Deprecate the `AdapterInterface` autowiring alias, use `CacheItemPoolInterface` instead
 * Deprecate the public `profiler` service to private

HttpKernel
----------

 * Deprecate `AbstractTestSessionListener::getSession` inject a session in the request instead

HttpFoundation
--------------

 * Mark `Request::get()` internal, use explicit input sources instead

Messenger
---------

 * Deprecate not setting the `delete_after_ack` config option (or DSN parameter) using the Redis transport,
   its default value will change to `true` in 6.0

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
 * Deprecate `TokenInterface:isAuthenticated()` and `setAuthenticated()` methods without replacement.
   Security tokens won't have an "authenticated" flag anymore, so they will always be considered authenticated
 * Deprecate `DeauthenticatedEvent`, use `TokenDeauthenticatedEvent` instead
 * Deprecate `CookieClearingLogoutHandler`, `SessionLogoutHandler` and `CsrfTokenClearingLogoutHandler`.
   Use `CookieClearingLogoutListener`, `SessionLogoutListener` and `CsrfTokenClearingLogoutListener` instead
