CHANGELOG
=========

7.3
---

 * Add encryption support to `OidcTokenHandler` (JWE)
 * Replace `$hideAccountStatusExceptions` argument with `$exposeSecurityErrors` in `AuthenticatorManager` constructor
 * Add argument `$identifierNormalizer` to `UserBadge::__construct()` to allow normalizing the identifier
 * Support hashing the hashed password using crc32c when putting the user in the session
 * Add support for closures in `#[IsGranted]`
 * Add `OAuth2TokenHandler` with OAuth2 Token Introspection support for `AccessTokenAuthenticator`
 * Add discovery support to `OidcTokenHandler` and `OidcUserInfoTokenHandler`

7.2
---

 * Pass the current token to the `checkPostAuth()` method of user checkers
 * Deprecate argument `$secret` of `RememberMeAuthenticator`
 * Deprecate passing an empty string as `$userIdentifier` argument to `UserBadge` constructor
 * Allow passing passport attributes to the `UserAuthenticatorInterface::authenticateUser()` method

7.1
---

 * Add `#[IsCsrfTokenValid]` attribute
 * Add CAS 2.0 access token handler
 * Make empty username or empty password on form login attempts throw `BadCredentialsException`

7.0
---

 * Add argument `$badgeFqcn` to `Passport::addBadge()`
 * Add argument `$lifetime` to `LoginLinkHandlerInterface::createLoginLink()`
 * Throw when calling the constructor of `DefaultLoginRateLimiter` with an empty secret

6.4
---

 * `UserValueResolver` no longer implements `ArgumentValueResolverInterface`
 * Deprecate calling the constructor of `DefaultLoginRateLimiter` with an empty secret

6.3
---

 * Add `RememberMeBadge` to `JsonLoginAuthenticator` and enable reading parameter in JSON request body
 * Add argument `$exceptionCode` to `#[IsGranted]`
 * Deprecate passing a secret as the 2nd argument to the constructor of `Symfony\Component\Security\Http\RememberMe\PersistentRememberMeHandler`
 * Add `OidcUserInfoTokenHandler` and `OidcTokenHandler` with OIDC support for `AccessTokenAuthenticator`
 * Add `attributes` optional array argument in `UserBadge`
 * Call `UserBadge::userLoader` with attributes if the argument is set
 * Allow to override badge fqcn on `Passport::addBadge`
 * Add `SecurityTokenValueResolver` to inject token as controller argument

6.2
---

 * Add maximum username length enforcement of 4096 characters in `UserBadge`
 * Add `#[IsGranted()]`
 * Deprecate empty username or password when using when using `JsonLoginAuthenticator`
 * Set custom lifetime for login link
 * Add `$lifetime` parameter to `LoginLinkHandlerInterface::createLoginLink()`
 * Add RFC6750 Access Token support to allow token-based authentication
 * Allow using expressions as `#[IsGranted()]` attribute and subject

6.0
---

 * Remove `LogoutSuccessHandlerInterface` and `LogoutHandlerInterface`, register a listener on the `LogoutEvent` event instead
 * Remove `CookieClearingLogoutHandler`, `SessionLogoutHandler` and `CsrfTokenClearingLogoutHandler`.
   Use `CookieClearingLogoutListener`, `SessionLogoutListener` and `CsrfTokenClearingLogoutListener` instead

5.4
---

 * Deprecate the `$authenticationEntryPoint` argument of `ChannelListener`, and add `$httpPort` and `$httpsPort` arguments
 * Deprecate `RetryAuthenticationEntryPoint`, this code is now inlined in the `ChannelListener`
 * Deprecate `FormAuthenticationEntryPoint` and `BasicAuthenticationEntryPoint`, in the new system the `FormLoginAuthenticator`
   and `HttpBasicAuthenticator` should be used instead
 * Deprecate `AbstractRememberMeServices`, `PersistentTokenBasedRememberMeServices`, `RememberMeServicesInterface`,
   `TokenBasedRememberMeServices`, use the remember me handler alternatives instead
 * Deprecate the `$authManager` argument of `AccessListener`
 * Deprecate not setting the `$exceptionOnNoToken` argument of `AccessListener` to `false`
 * Deprecate `DeauthenticatedEvent`, use `TokenDeauthenticatedEvent` instead
 * Deprecate `CookieClearingLogoutHandler`, `SessionLogoutHandler` and `CsrfTokenClearingLogoutHandler`.
   Use `CookieClearingLogoutListener`, `SessionLogoutListener` and `CsrfTokenClearingLogoutListener` instead
 * Deprecate `PassportInterface`, `UserPassportInterface` and `PassportTrait`, use `Passport` instead

5.3
---

The CHANGELOG for version 5.3 and earlier can be found at https://github.com/symfony/symfony/blob/5.3/src/Symfony/Component/Security/CHANGELOG.md
