CHANGELOG
=========

The CHANGELOG for version 5.4 and newer can be found in the security sub-packages (e.g. `Http/`).

5.3
---

 * Deprecate the Guard component
 * Deprecate `AuthenticationManagerInterface`, `AuthenticationProviderManager`, `AnonymousAuthenticationProvider`,
  `AuthenticationProviderInterface`, `DaoAuthenticationProvider`, `LdapBindAuthenticationProvider`,
  `PreAuthenticatedAuthenticationProvider`, `RememberMeAuthenticationProvider`, `UserAuthenticationProvider` and
  `AuthenticationFailureEvent` from security-core. Use the new authenticator system instead
 * Deprecate `AbstractAuthenticationListener`, `AbstractPreAuthenticatedListener`, `AnonymousAuthenticationListener`,
   `BasicAuthenticationListener`, `RememberMeListener`, `RemoteUserAuthenticationListener`,
   `UsernamePasswordFormAuthenticationListener`, `UsernamePasswordJsonAuthenticationListener` and `X509AuthenticationListener`
   from security-http, use the new authenticator system instead
 * Add `getPassport()` method and a second `$passport` constructor argument to `AuthenticationTokenCreatedEvent`
 * The authenticator system is no longer experimental
 * Login Link functionality is no longer experimental
 * Add `RememberMeConditionsListener` to check if remember me is requested and supported, and set priority of `RememberMeListener` to -63
 * Add `RememberMeHandlerInterface` and implementations, used as a replacement of `RememberMeServicesInterface` when using the AuthenticatorManager
 * Add `TokenDeauthenticatedEvent` that is dispatched when the current security token is deauthenticated
 * [BC break] Change constructor signature of `LoginLinkHandler` to `__construct(UrlGeneratorInterface $urlGenerator, UserProviderInterface $userProvider, SignatureHasher $signatureHashUtil, array $options)`
 * Add `Core\Signature\SignatureHasher` and moved `Http\LoginLink\ExpiredLoginLinkStorage` to `Core\Signature\ExpiredLoginLinkStorage`
 * Deprecate `PersistentTokenInterface::getUsername()` in favor of `PersistentTokenInterface::getUserIdentifier()`
 * Deprecate `UsernameNotFoundException` in favor of `UserNotFoundException` and `getUsername()`/`setUsername()` in favor of `getUserIdentifier()`/`setUserIdentifier()`
 * Deprecate `UserProviderInterface::loadUserByUsername()` in favor of `UserProviderInterface::loadUserByIdentifier()`
 * Deprecate `TokenInterface::getUsername()` in favor of `TokenInterface::getUserIdentifier()`
 * Deprecate `UserInterface::getUsername()` in favor of `getUserIdentifier()`
 * Add `PassportInterface:getBadges()`, implemented by `PassportTrait`
 * [BC BREAK] Remove method `checkIfCompletelyResolved()` from `PassportInterface`, checking that passport badges are
   resolved is up to `AuthenticatorManager`
 * Deprecate class `User`, use `InMemoryUser` instead
 * Deprecate class `UserChecker`, use `InMemoryUserChecker` or your own implementation instead
 * [BC break] Remove support for passing a `UserInterface` implementation to `Passport`, use the `UserBadge` instead.
 * Add `PasswordAuthenticatedUserInterface` for user classes that use passwords
 * Add `LegacyPasswordAuthenticatedUserInterface` for user classes that use user-provided salts in addition to passwords
 * Deprecate all classes in the `Core\Encoder\`  sub-namespace, use the `PasswordHasher` component instead
 * Deprecate the `SessionInterface $session` constructor argument of `SessionTokenStorage`, inject a `\Symfony\Component\HttpFoundation\RequestStack $requestStack` instead
 * Deprecate the `session` service provided by the ServiceLocator injected in `UsageTrackingTokenStorage`, provide a `request_stack` service instead
 * Deprecate using `SessionTokenStorage` outside a request context, it will throw a `SessionNotFoundException` in Symfony 6.0
 * Randomize CSRF tokens to harden BREACH attacks
 * Deprecated voters that do not return a valid decision when calling the `vote` method.
 * Flag `Serializable` implementation of `NullToken` as `@internal` and `@final`
 * Add `TokenVerifierInterface` to allow fixing parallel requests handling in remember-me
 * Add a `CacheTokenVerifier` implementation that stores outdated token in a cache, which is more correct and efficient as the default `DoctrineTokenProvider` implementation

5.2.0
-----

 * Added attributes on `Passport`
 * Changed `AuthorizationChecker` to call the access decision manager in unauthenticated sessions with a `NullToken`
 * [BC break] Removed `AccessListener::PUBLIC_ACCESS` in favor of `AuthenticatedVoter::PUBLIC_ACCESS`
 * Added `Passport` to `LoginFailureEvent`.
 * Deprecated `setProviderKey()`/`getProviderKey()` in favor of `setFirewallName()/getFirewallName()` in `PreAuthenticatedToken`, `RememberMeToken`, `SwitchUserToken`, `UsernamePasswordToken`, `DefaultAuthenticationSuccessHandler`; and deprecated the `AbstractRememberMeServices::$providerKey` property in favor of `AbstractRememberMeServices::$firewallName`
 * Added `FirewallListenerInterface` to make the execution order of firewall listeners configurable
 * Added translator to `\Symfony\Component\Security\Http\Authenticator\JsonLoginAuthenticator` and `\Symfony\Component\Security\Http\Firewall\UsernamePasswordJsonAuthenticationListener` to translate authentication failure messages
 * Added a CurrentUser attribute to force the UserValueResolver to resolve an argument to the current user.
 * Added `LoginThrottlingListener`.
 * Added `LoginLinkAuthenticator`.
 * Moved methods `supports()` and `authenticate()` from `AbstractListener` to `FirewallListenerInterface`.
 * [BC break] `PasswordUpgradeBadge::getPasswordUpgrader()` changed its return type to return null or a `PasswordUpgraderInterface` implementation.

5.1.0
-----

 * Added access decision strategy to override access decisions by voter service priority
 * Added `IS_ANONYMOUS`, `IS_REMEMBERED`, `IS_IMPERSONATOR`
 * Hash the persistent RememberMe token value in database.
 * Added `LogoutEvent` to allow custom logout listeners.
 * Deprecated `LogoutSuccessHandlerInterface` and `LogoutHandlerInterface` in favor of listening on the `LogoutEvent`.
 * Added experimental new security using `Http\Authenticator\AuthenticatorInterface`, `Http\Authentication\AuthenticatorManager` and `Http\Firewall\AuthenticatorManagerListener`.
 * Added `CustomUserMessageAccountStatusException` to be used when extending `UserCheckerInterface`
 * Deprecated `RememberMeServicesInterface` implementations without `logout(Request $request, Response $response, TokenInterface $token)` method, this method will be required in Symfony 6.0.

5.0.0
-----

 * Dropped support for passing more than one attribute to `AccessDecisionManager::decide()` and `AuthorizationChecker::isGranted()` (and indirectly the `is_granted()` Twig and ExpressionLanguage function):

   **Before**
   ```php
   if ($this->authorizationChecker->isGranted(['ROLE_USER', 'ROLE_ADMIN'])) {
       // ...
   }
   ```

   **After**
   ```php
   if ($this->authorizationChecker->isGranted(new Expression("is_granted('ROLE_USER') or is_granted('ROLE_ADMIN')"))) {}
   // or:
   if ($this->authorizationChecker->isGranted('ROLE_USER')
      || $this->authorizationChecker->isGranted('ROLE_ADMIN')
   ) {}
   ```
 * Implementations of `Guard\AuthenticatorInterface::checkCredentials()` must return
   a boolean value now. Please explicitly return `false` to indicate invalid credentials.
 * The `LdapUserProvider` class has been removed, use `Symfony\Component\Ldap\Security\LdapUserProvider` instead.
 * The `FirewallMapInterface::getListeners()` method must return an array of 3 elements.
 * Removed the `ContextListener::setLogoutOnUserChange()` method.
 * Removed the `ListenerInterface`, turn your listeners into callables instead.
 * Removed the `Firewall::handleRequest()` method, use `Firewall::callListeners()` instead.
 * Removed the `AdvancedUserInterface`, use a custom user checker instead.
 * Removed `Argon2iPasswordEncoder`, use `SodiumPasswordEncoder` instead
 * Removed `BcryptPasswordEncoder`, use `NativePasswordEncoder` instead
 * Removed the `has_role()` function from security expressions, use `is_granted()` instead.
 * `SimpleAuthenticatorInterface`, `SimpleFormAuthenticatorInterface`, `SimplePreAuthenticatorInterface`,
   `SimpleAuthenticationProvider`, `SimpleAuthenticationHandler`, `SimpleFormAuthenticationListener` and
   `SimplePreAuthenticationListener` have been removed. Use Guard instead.
 * Removed the `Role` and `SwitchUserRole` classes. Use strings for roles instead.
 * Removed the `getReachableRoles()` method from the `RoleHierarchyInterface`. Role hierarchies must implement
   the `getReachableRoleNames()` method instead and return roles as strings.
 * Removed the `getRoles()` method from the `TokenInterface`. Tokens must implement the `getRoleNames()` method
   instead and return roles as strings.
 * Made the `serialize` and `unserialize` methods of `AbstractToken` final and internal
 * Removed the `serialize` and `unserialize` methods from `AuthenticationException`
 * Added method `__serialize` and `__unserialize` to `TokenInterface`
 * Added method `needsRehash` to `PasswordEncoderInterface` and `UserPasswordEncoderInterface`
 * Removed `ExpressionVoter::addExpressionLanguageProvider()`
 * Made `Security::getUser()` return null when the user is not an instanceof `UserInterface`,
   use `getToken()->getUser()` instead
 * Removed the `AuthenticationTrustResolver` constructor arguments

4.4.0
-----

 * Deprecated class `LdapUserProvider`, use `Symfony\Component\Ldap\Security\LdapUserProvider` instead
 * Added method `needsRehash()` to `PasswordEncoderInterface` and `UserPasswordEncoderInterface`
 * Added `MigratingPasswordEncoder`
 * Added and implemented `PasswordUpgraderInterface`, for opportunistic password migrations
 * Added `Guard\PasswordAuthenticatedInterface`, an optional interface
   for "guard" authenticators that deal with user passwords
 * Marked all dispatched event classes as `@final`
 * Deprecated returning a non-boolean value when implementing `Guard\AuthenticatorInterface::checkCredentials()`.
 * Deprecated passing more than one attribute to `AccessDecisionManager::decide()` and `AuthorizationChecker::isGranted()`
 * Added new `argon2id` encoder, undeprecated the `bcrypt` and `argon2i` ones (using `auto` is still recommended by default.)
 * Added `AbstractListener` which replaces the deprecated `ListenerInterface`

4.3.0
-----

 * Added methods `__serialize` and `__unserialize` to the `TokenInterface`
 * Added `SodiumPasswordEncoder` and `NativePasswordEncoder`
 * The `Role` and `SwitchUserRole` classes are deprecated and will be removed in 5.0. Use strings for roles
   instead.
 * The `getReachableRoles()` method of the `RoleHierarchyInterface` is deprecated and will be removed in 5.0.
   Role hierarchies must implement the `getReachableRoleNames()` method instead and return roles as strings.
 * The `getRoles()` method of the `TokenInterface` is deprecated. Tokens must implement the `getRoleNames()`
   method instead and return roles as strings.
 * Made the `serialize()` and `unserialize()` methods of `AbstractToken` and
  `AuthenticationException` final, use `__serialize()`/`__unserialize()` instead
 * `AuthenticationException` doesn't implement `Serializable` anymore
 * Deprecated the `ListenerInterface`, turn your listeners into callables instead
 * Deprecated `Firewall::handleRequest()`, use `Firewall::callListeners()` instead
 * Dispatch `AuthenticationSuccessEvent` on `security.authentication.success`
 * Dispatch `AuthenticationFailureEvent` on `security.authentication.failure`
 * Dispatch `InteractiveLoginEvent` on `security.interactive_login`
 * Dispatch `SwitchUserEvent` on `security.switch_user`
 * Deprecated `Argon2iPasswordEncoder`, use `SodiumPasswordEncoder` instead
 * Deprecated `BCryptPasswordEncoder`, use `NativePasswordEncoder` instead
 * Added `DeauthenticatedEvent` dispatched in case the user has changed when trying to refresh the token

4.2.0
-----

 * added the `is_granted()` function in security expressions
 * deprecated the `has_role()` function in security expressions, use `is_granted()` instead
 * Passing custom class names to the
   `Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver` to define
   custom anonymous and remember me token classes is deprecated. To
   use custom tokens, extend the existing `Symfony\Component\Security\Core\Authentication\Token\AnonymousToken`
   or `Symfony\Component\Security\Core\Authentication\Token\RememberMeToken`.
 * allow passing null as $filter in LdapUserProvider to get the default filter
 * accessing the user object that is not an instance of `UserInterface` from `Security::getUser()` is deprecated
 * Deprecated `SimpleAuthenticatorInterface`, `SimpleFormAuthenticatorInterface`,
   `SimplePreAuthenticatorInterface`, `SimpleAuthenticationProvider`, `SimpleAuthenticationHandler`,
   `SimpleFormAuthenticationListener` and `SimplePreAuthenticationListener`. Use Guard instead.

4.1.0
-----

 * The `ContextListener::setLogoutOnUserChange()` method is deprecated.
 * added `UserValueResolver`.
 * Using the AdvancedUserInterface is now deprecated. To use the existing
   functionality, create a custom user-checker based on the
   `Symfony\Component\Security\Core\User\UserChecker`.
 * `AuthenticationUtils::getLastUsername()` now always returns a string.

4.0.0
-----

 * The `AbstractFormLoginAuthenticator::onAuthenticationSuccess()` was removed.
   You should implement this method yourself in your concrete authenticator.
 * removed the `AccessDecisionManager::setVoters()` method
 * removed the `RoleInterface`
 * removed support for voters that don't implement the `VoterInterface`
 * added a sixth `string $context` argument to `LogoutUrlGenerator::registerListener()`
 * removed HTTP digest authentication
 * removed `GuardAuthenticatorInterface` in favor of `AuthenticatorInterface`
 * removed `AbstractGuardAuthenticator::supports()`
 * added target user to `SwitchUserListener`

3.4.0
-----

 * Added `getUser`, `getToken` and `isGranted` methods to `Security`.
 * added a `setToken()` method to the `SwitchUserEvent` class to allow to replace the created token while switching users
   when custom token generation is required by application.
 * Using voters that do not implement the `VoterInterface`is now deprecated in
   the `AccessDecisionManager` and this functionality will be removed in 4.0.
 * Using the `ContextListener` without setting the `logoutOnUserChange`
   property will trigger a deprecation when the user has changed. As of 4.0
   the user will always be logged out when the user has changed between
   requests.
 * deprecated HTTP digest authentication
 * Added a new password encoder for the Argon2i hashing algorithm
 * deprecated `GuardAuthenticatorInterface` in favor of `AuthenticatorInterface`
 * deprecated to return `null` from `getCredentials()` in classes that extend
   `AbstractGuardAuthenticator`. Return `false` from `supports()` instead.

3.3.0
-----

 * deprecated `AccessDecisionManager::setVoters()` in favor of passing the
   voters to the constructor.
 * [EXPERIMENTAL] added a `json_login` listener for stateless authentication

3.2.0
-----

 * added `$attributes` and `$subject` with getters/setters to `Symfony\Component\Security\Core\Exception\AccessDeniedException`

3.0.0
-----

 * removed all deprecated code

2.8.0
-----

 * deprecated `getKey()` of the `AnonymousToken`, `RememberMeToken`,
   `AbstractRememberMeServices` and `DigestAuthenticationEntryPoint` classes in favor of `getSecret()`.
 * deprecated `Symfony\Component\Security\Core\Authentication\SimplePreAuthenticatorInterface`, use
   `Symfony\Component\Security\Http\Authentication\SimplePreAuthenticatorInterface` instead
 * deprecated `Symfony\Component\Security\Core\Authentication\SimpleFormAuthenticatorInterface`, use
   `Symfony\Component\Security\Http\Authentication\SimpleFormAuthenticatorInterface` instead
 * deprecated `Symfony\Component\Security\Core\Util\ClassUtils`, use
   `Symfony\Component\Security\Acl\Util\ClassUtils` instead
 * deprecated the `Symfony\Component\Security\Core\Util\SecureRandom` class in favor of the `random_bytes()` function
 * deprecated `supportsAttribute()` and `supportsClass()` methods of
   `Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface` and
   `Symfony\Component\Security\Core\Authorization\Voter\VoterInterface`.
 * deprecated `getSupportedAttributes()` and `getSupportedClasses()` methods of
   `Symfony\Component\Security\Core\Authorization\Voter\AbstractVoter`, use `supports()` instead.
 * deprecated the `intention` option for all the authentication listeners,
   use the `csrf_token_id` option instead.

2.7.0
-----

 * added LogoutUrlGenerator
 * added the triggering of the `Symfony\Component\Security\Http\SecurityEvents::INTERACTIVE_LOGIN` in `Symfony\Component\Security\Http\Firewall\SimplePreAuthenticationListener`
 * The MaskBuilder logic has been abstracted in the `Symfony\Component\Security\Acl\Permission\AbstractMaskBuilder`
   and described in the `Symfony\Component\Security\Acl\Permission\MaskBuilderInterface`
 * added interface `Symfony\Component\Security\Acl\Permission\MaskBuilderRetrievalInterface`

2.6.0
-----

 * added Symfony\Component\Security\Http\Authentication\AuthenticationUtils
 * Deprecated the `SecurityContext` class in favor of the `AuthorizationChecker` and `TokenStorage` classes

2.4.0
-----

 * Translations in the `src/Symfony/Component/Security/Resources/translations/` directory are deprecated, ones in `src/Symfony/Component/Security/Core/Resources/translations/` must be used instead.
 * The switch user listener now preserves the query string when switching a user
 * The remember-me cookie hashes now use HMAC, which means that current cookies will be invalidated
 * added simpler customization options
 * structured component into three sub-components Acl, Core and Http
 * added Csrf sub-component
 * changed Http sub-component to depend on Csrf sub-component instead of the Form component

2.3.0
-----

 * [BC BREAK] the BCrypt encoder constructor signature has changed (the first argument was removed)
   To use the BCrypt encoder, you now need PHP 5.5 or "ircmaxell/password-compat" as a composer dependency
 * [BC BREAK] return 401 instead of 500 when using use_forward during for form authentication
 * added a `require_previous_session` option to `AbstractAuthenticationListener`

2.2.0
-----

 * `Symfony\Component\Security\Http\Firewall` and
   `Symfony\Component\Security\Http\RememberMe\ResponseListener` now
   implements EventSubscriberInterface
 * added secure random number generator
 * added PBKDF2 Password encoder
 * added BCrypt password encoder

2.1.0
-----

 * [BC BREAK] The signature of ExceptionListener has changed
 * changed the HttpUtils constructor signature to take a UrlGenerator and a UrlMatcher instead of a Router
 * EncoderFactoryInterface::getEncoder() can now also take a class name as an argument
 * allow switching to the user that is already impersonated
 * added support for the remember_me parameter in the query
 * added AccessMapInterface
 * [BC BREAK] moved user comparison logic out of UserInterface
 * made the logout path check configurable
 * after login, the user is now redirected to `default_target_path` if
   `use_referer` is true and the referrer is the `login_path`.
 * added a way to remove a token from a session
 * [BC BREAK] changed `MutableAclInterface::setParentAcl` to accept `null`,
   review your implementation to reflect this change.
 * `ObjectIdentity::fromDomainObject`, `UserSecurityIdentity::fromAccount` and
   `UserSecurityIdentity::fromToken` now return correct identities for proxies
   objects (e.g. Doctrine proxies)
 * [BC BREAK] moved the default authentication success and failure handling to
   separate classes. The order of arguments in the constructor of the
   `AbstractAuthenticationListener` has changed.
 * [BC BREAK] moved the default logout success handling to a separate class. The
   order of arguments in the constructor of `LogoutListener` has changed.
 * [BC BREAK] The constructor of `AuthenticationException` and all child
   classes now matches the constructor of `\Exception`. The extra information
   getters and setters are removed. There are now dedicated getters/setters for
   token (`AuthenticationException'), user (`AccountStatusException`) and
   username (`UsernameNotFoundException`).
