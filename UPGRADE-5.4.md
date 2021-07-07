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

 * Deprecate `SecurityFactoryInterface` and `SecurityExtension::addSecurityListenerFactory()` in favor of
   `AuthenticatorFactoryInterface` and `SecurityExtension::addAuthenticatorFactory()`

 * Add `AuthenticatorFactoryInterface::getPriority()` which replaces `SecurityFactoryInterface::getPosition()`.
   Previous positions are mapped to the following priorities:

    | Position    | Constant                                              | Priority |
    | ----------- | ----------------------------------------------------- | -------- |
    | pre_auth    | `RemoteUserFactory::PRIORITY`/`X509Factory::PRIORITY` | -10      |
    | form        | `FormLoginFactory::PRIORITY`                          | -30      |
    | http        | `HttpBasicFactory::PRIORITY`                          | -50      |
    | remember_me | `RememberMeFactory::PRIORITY`                         | -60      |
    | anonymous   | n/a                                                   | -70      |

 * Deprecate passing an array of arrays as 1st argument to `MainConfiguration`, pass a sorted flat array of
   factories instead.
