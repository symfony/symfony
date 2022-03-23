UPGRADE FROM 5.3 to 5.4
=======================

Cache
-----

 * Deprecate `DoctrineProvider` and `DoctrineAdapter` because these classes have been added to the `doctrine/cache` package
 * Deprecate usage of `PdoAdapter` with a `Doctrine\DBAL\Connection` or a DBAL URL. Use the new `DoctrineDbalAdapter` instead

Console
-------

 * Deprecate `HelperSet::setCommand()` and `getCommand()` without replacement

DoctrineBridge
--------------

 * Add argument `$bundleDir` to `AbstractDoctrineExtension::getMappingDriverBundleConfigDefaults()`
 * Add argument `$bundleDir` to `AbstractDoctrineExtension::getMappingResourceConfigDirectory()`

Finder
------

 * Deprecate `Comparator::setTarget()` and `Comparator::setOperator()`
 * Add a constructor to `Comparator` that allows setting target and operator

Form
------

 * Deprecate calling `FormErrorIterator::children()` if the current element is not iterable.

FrameworkBundle
---------------

 * Deprecate the `framework.translator.enabled_locales` config option, use `framework.enabled_locales` instead
 * Deprecate the `AdapterInterface` autowiring alias, use `CacheItemPoolInterface` instead
 * Deprecate the public `profiler` service to private
 * Deprecate `get()`, `has()`, `getDoctrine()`, and `dispatchMessage()` in `AbstractController`, use method/constructor injection instead
 * Deprecate the `cache.adapter.doctrine` service: The Doctrine Cache library is deprecated. Either switch to Symfony Cache or use the PSR-6 adapters provided by Doctrine Cache.
 * In `framework.cache` configuration, using `cache.adapter.pdo` adapter with a Doctrine DBAL connection is deprecated, use `cache.adapter.doctrine_dbal` instead.
 * Deprecate not setting the `framework.messenger.reset_on_message` config option, its default value will change to `true` in 6.0

HttpKernel
----------

 * Deprecate `AbstractTestSessionListener` and `TestSessionListener`, use `AbstractSessionListener` and `SessionListener` instead

HttpFoundation
--------------

 * Deprecate passing `null` as `$requestIp` to `IpUtils::checkIp()`, `IpUtils::checkIp4()` or `IpUtils::checkIp6()`, pass an empty string instead.
 * Mark `Request::get()` internal, use explicit input sources instead
 * Deprecate `upload_progress.*` and `url_rewriter.tags` session options

Ldap
----

 * Deprecate `LdapAuthenticator::createAuthenticatedToken()`, use `LdapAuthenticator::createToken()` instead

Lock
----

 * Deprecate usage of `PdoStore` with a `Doctrine\DBAL\Connection` or a DBAL url, use the new `DoctrineDbalStore` instead
 * Deprecate usage of `PostgreSqlStore` with a `Doctrine\DBAL\Connection` or a DBAL url, use the new `DoctrineDbalPostgreSqlStore` instead

Messenger
---------

 * Deprecate not setting the `delete_after_ack` config option (or DSN parameter) using the Redis transport,
   its default value will change to `true` in 6.0

Monolog
-------

 * Deprecate `ResetLoggersWorkerSubscriber` to reset buffered logs in messenger
   workers, use `framework.messenger.reset_on_message` option in FrameworkBundle messenger configuration instead.

SecurityBundle
--------------

 * Deprecate `FirewallConfig::getListeners()`, use `FirewallConfig::getAuthenticators()` instead
 * Deprecate `security.authentication.basic_entry_point` and `security.authentication.retry_entry_point` services, the logic is moved into the
   `HttpBasicAuthenticator` and `ChannelListener` respectively
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

 * Deprecate `AuthenticationEvents::AUTHENTICATION_FAILURE`, use the `LoginFailureEvent` instead
 * Deprecate the `$authenticationEntryPoint` argument of `ChannelListener`, and add `$httpPort` and `$httpsPort` arguments
 * Deprecate `RetryAuthenticationEntryPoint`, this code is now inlined in the `ChannelListener`
 * Deprecate `FormAuthenticationEntryPoint` and `BasicAuthenticationEntryPoint`, in the new system the `FormLoginAuthenticator`
   and `HttpBasicAuthenticator` should be used instead
 * Deprecate `AbstractRememberMeServices`, `PersistentTokenBasedRememberMeServices`, `RememberMeServicesInterface`,
   `TokenBasedRememberMeServices`, use the remember me handler alternatives instead
 * Deprecate `AnonymousToken`, as the related authenticator was deprecated in 5.3
 * Deprecate `Token::getCredentials()`, tokens should no longer contain credentials (as they represent authenticated sessions)
 * Deprecate not returning an `UserInterface` from `Token::getUser()`
 * Deprecate `AuthenticatedVoter::IS_AUTHENTICATED_ANONYMOUSLY` and `AuthenticatedVoter::IS_ANONYMOUS`,
   use `AuthenticatedVoter::PUBLIC_ACCESS` instead.

   Before:
   ```yaml
   # config/packages/security.yaml
   security:
       # ...
       access_control:
           - { path: ^/login, roles: IS_AUTHENTICATED_ANONYMOUSLY }
   ```

   After:
   ```yaml
   # config/packages/security.yaml
   security:
       # ...
       access_control:
           - { path: ^/login, roles: PUBLIC_ACCESS }
   ```

 * Deprecate `AuthenticationTrustResolverInterface::isAnonymous()` and the `is_anonymous()` expression function
   as anonymous no longer exists in version 6, use the `isFullFledged()` or the new `isAuthenticated()` instead
   if you want to check if the request is (fully) authenticated.
 * Deprecate the `$authManager` argument of `AccessListener`, the argument will be removed
 * Deprecate the `$authenticationManager` argument of the `AuthorizationChecker` constructor, the argument will be removed
 * Deprecate setting the `$alwaysAuthenticate` argument to `true` and not setting the
   `$exceptionOnNoToken` argument to `false` of `AuthorizationChecker` (this is the default
   behavior when using `enable_authenticator_manager: true`)
 * Deprecate not setting the `$exceptionOnNoToken` argument of `AccessListener` to `false`
   (this is the default behavior when using `enable_authenticator_manager: true`)
 * Deprecate `TokenInterface:isAuthenticated()` and `setAuthenticated()` methods,
   return `null` from `getUser()` instead when a token is not authenticated
 * Deprecate `DeauthenticatedEvent`, use `TokenDeauthenticatedEvent` instead
 * Deprecate `CookieClearingLogoutHandler`, `SessionLogoutHandler` and `CsrfTokenClearingLogoutHandler`.
   Use `CookieClearingLogoutListener`, `SessionLogoutListener` and `CsrfTokenClearingLogoutListener` instead
 * Deprecate `AuthenticatorInterface::createAuthenticatedToken()`, use `AuthenticatorInterface::createToken()` instead
 * Deprecate `PassportInterface`, `UserPassportInterface` and `PassportTrait`, use `Passport` instead.
   As such, the return type declaration of `AuthenticatorInterface::authenticate()` will change to `Passport` in 6.0
 * Deprecate not configuring explicitly a provider for custom_authenticators when there is more than one registered provider

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
 * Deprecate passing the strategy as string to `AccessDecisionManager`,
   pass an instance of `AccessDecisionStrategyInterface` instead
 * Flag `AccessDecisionManager` as `@final`
 * Deprecate passing `$credentials` to `PreAuthenticatedToken`,
   `SwitchUserToken` and `UsernamePasswordToken`:

   Before:
   ```php
   $token = new UsernamePasswordToken($user, $credentials, $firewallName, $roles);
   $token = new PreAuthenticatedToken($user, $credentials, $firewallName, $roles);
   $token = new SwitchUserToken($user, $credentials, $firewallName, $roles, $originalToken);
   ```

   After:
   ```php
   $token = new UsernamePasswordToken($user, $firewallName, $roles);
   $token = new PreAuthenticatedToken($user, $firewallName, $roles);
   $token = new SwitchUserToken($user, $firewallName, $roles, $originalToken);
   ```
