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
 * Deprecate `get()`, `has()`, `getDoctrine()`, and `dispatchMessage()` in `AbstractController`, use method/constructor injection instead

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

 * Deprecate not setting `$authenticatorManagerEnabled` to `true` in `SecurityDataCollector` and `DebugFirewallCommand`
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
 * Deprecate the `always_authenticate_before_granting` option

Security
--------

 * Deprecate the `$authManager` argument of `AccessListener`
 * Deprecate the `$authenticationManager` argument of the `AuthorizationChecker` constructor
 * Deprecate not setting `$authenticatorManagerEnabled` to `true` in `SecurityDataCollector` and `DebugFirewallCommand`
   (this is the default behavior when using `enable_authenticator_manager: true`)
 * Deprecate setting the `$alwaysAuthenticate` argument to `true` and not setting the
   `$exceptionOnNoToken argument to `false` of `AuthorizationChecker` (this is the default
   behavior when using `enable_authenticator_manager: true`)
 * Deprecate not setting the `$exceptionOnNoToken` argument of `AccessListener` to `false`
   (this is the default behavior when using `enable_authenticator_manager: true`)
 * Deprecate `TokenInterface:isAuthenticated()` and `setAuthenticated()` methods without replacement.
   Security tokens won't have an "authenticated" flag anymore, so they will always be considered authenticated
 * Deprecate `DeauthenticatedEvent`, use `TokenDeauthenticatedEvent` instead
 * Deprecate `CookieClearingLogoutHandler`, `SessionLogoutHandler` and `CsrfTokenClearingLogoutHandler`.
   Use `CookieClearingLogoutListener`, `SessionLogoutListener` and `CsrfTokenClearingLogoutListener` instead
 * Deprecate `AuthenticatorInterface::createAuthenticatedToken()`, use `AuthenticatorInterface::createToken()` instead
 * Deprecate `PassportInterface` and `UserPassportInterface`, use `Passport` instead.
   As such, the return type declaration of `AuthenticatorInterface::authenticate()` will change to `Passport` in 6.0

   Before:
   ```php
   class MyAuthenticator implements AuthenticatorInterface
   {
       public function authenticate(Request $request): PassportInterface
       {
       }
   }
   ```

   After:
   ```php
   class MyAuthenticator implements AuthenticatorInterface
   {
       public function authenticate(Request $request): Passport
       {
       }
   }
   ```
