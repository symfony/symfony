CHANGELOG
=========

8.2
---

 * Make `FormLoginAuthenticator` a `ReAuthenticationEntryPointInterface`, sending the user back to the login form with their identifier filled in
 * Add `AuthenticationUtils::getReAuthenticationAttribute()` and `SecurityRequestAttributes::RE_AUTHENTICATION_ATTRIBUTE`, the security attribute the current request is asking a re-authentication for
 * Add `SenderConstraintInterface`, `DpopSenderConstraint` and the `$senderConstraint` argument of `AccessTokenAuthenticator`, to accept only the access tokens bound to a key the request proves it holds (RFC 9449)
 * Add `DpopProofFactory`, to bind the tokens of an OIDC provider to a key the client holds (RFC 9449)
 * Add `OidcBackChannelLogout`, `OidcLogoutToken`, `OidcEndedSessions`, `OidcBackChannelLogoutListener` and `OidcSessionEndedException` to support OpenID Connect Back-Channel Logout
 * Add `CheckRefreshedUserEvent`, dispatched when a user restored from the session has been reloaded from its user provider, and `RefreshedUserCheckerListener`, which runs a user checker on it so an account disabled during a session is rejected on the next request
 * Add argument `$mapRequestPayload` to `IsGrantedAttributeListener`, to map the `#[MapRequestPayload]`, `#[MapQueryString]` and `#[MapUploadedFile]` arguments that an `#[IsGranted]` subject reads before voting
 * Add argument `$exception` to `TokenDeauthenticatedEvent::__construct()` and `TokenDeauthenticatedEvent::getException()`
 * Handle security exceptions from a `kernel.exception` listener of the firewall, and deprecate `ExceptionListener::register()`, `ExceptionListener::unregister()` and the `$dispatcher` argument of `Firewall::__construct()`
 * Register `ContextListener::onKernelResponse()` on the `kernel.response` event instead of on the event dispatcher at runtime
 * Add `ReAuthenticationEntryPointInterface`, started by `ExceptionListener` when the vote denying an attribute requests a re-authentication for it
 * Add `IsGrantedContext::isAuthenticatedRecently()` and `IsGrantedContext::isAuthenticatedVeryRecently()`
 * Add `AuthenticationProofsListener`, which records an interactive authentication as an authentication proof of the token, under the methods its `AuthenticationMethodBadge` states
 * Add `AuthenticationMethodBadge`, for an authenticator to state which authentication methods it verified; `form_login`, `json_login` and `http_basic` state `AuthenticationMethod::PASSWORD`
 * Add `allowed_time_drift` option to `OidcTokenHandler` to configure time tolerance for token validation (`iat`, `nbf`, `exp` claims)
 * Expose the OAuth2 scopes an access token was granted as the `oauth2_scope` token attribute, and add `OAuth2ScopeVoter` to require them with an `OAUTH2_SCOPE(...)` attribute
 * Add `InsufficientScopeAccessDeniedHandler` to answer a denial caused by a missing scope with the RFC 6750 §3.1 challenge
 * Add the `$audiences`, `$issuer`, `$claim`, `$clock` and `$allowedTimeDrift` arguments to `Oauth2TokenHandler`, which validates the `exp`, `nbf`, `iat`, `iss` and `aud` members of the introspection response against them
 * Add `Oauth2TokenHandler::enableCache()`, `enableSignedResponse()` and `enableSignedResponseDiscovery()` to cache the introspection responses and to request and verify them as JWTs (RFC 9701)
 * Throw a 403 `Symfony\Component\Security\Http\Exception\InvalidCsrfTokenException` instead of the `Security\Core` one when the `#[IsCsrfTokenValid]` attribute fails
 * Add the deauthentication reason and the responsible user providers to `TokenDeauthenticatedEvent`
 * Add `LogoutUrlGenerator::getLogoutForm()` to build a form that logs the user out with a POST
 * Throw the status-code-specific `HttpException` (e.g. `NotFoundHttpException`, `AccessDeniedHttpException`) instead of a generic one when `#[IsGranted]`'s `statusCode` option is set
 * Add `$httpUtils`, `$path`, `$csrfTokenManager`, `$csrfParameter` and `$csrfTokenId` arguments to `SwitchUserListener` to restrict user switching to a dedicated route protected by a CSRF token
 * Add `$urlGenerator` and `$csrfTokenManager` arguments to `ImpersonateUrlGenerator` so that the generated URLs follow the `path` and CSRF configuration of the firewall
 * Add `ImpersonateUrlGenerator::generateImpersonationForm()` and `generateExitForm()` to build a form that switches the user with a POST
 * Add `$targetUri` argument to `ImpersonateUrlGenerator::generateImpersonationPath()` and `generateImpersonationUrl()`
 * Configure the decorated handler of `CustomAuthenticationSuccessHandler` and `CustomAuthenticationFailureHandler` when they are called instead of when they are built, so that a single handler can be shared by several authenticators
 * Add argument `$parameters` to `LoginLinkHandlerInterface::createLoginLink()` to add extra query parameters covered by the link signature, exposed as the `_login_link_parameters` request attribute when the link is consumed
 * Add `OidcLoginAuthenticator`, `OidcClientInterface`, `OidcClient`, `OidcDiscovery` and `OidcSignatureVerifier` for the OpenID Connect Authorization Code Flow
 * Cache the discovery document of the `oidc` access token handler for one hour, where it was fetched again on every refresh of the JWKS
 * Add `ClientAuthenticationInterface` and its `ClientSecretBasic`, `ClientSecretPost`, `ClientSecretJwt`, `PrivateKeyJwt`, `TlsClientAuth`, `SelfSignedTlsClientAuth` and `NoClientAuthentication` implementations, which say how an OAuth2 client authenticates at the token endpoint
 * Add `OidcEndSessionListener` for RP-Initiated Logout via the OIDC `end_session_endpoint`
 * Add `UnsupportedReasons`, held by the `SecurityRequestAttributes::UNSUPPORTED_REASONS` request attribute while the profiler is enabled, so that `supports()` can tell why an authenticator did not support a request
 * Add `OidcTokenRefresher` and `OidcTokenRefreshListener` to renew the OIDC access token with the refresh token grant
 * Add the `$enforceAtJwtType` argument to `OidcTokenHandler` to reject the tokens whose `typ` header is not `at+jwt` (RFC 9068)
 * Deprecate not passing the `$enforceAtJwtType` argument to `OidcTokenHandler`, it will default to `true` in 9.0
 * Make `OidcTokenGenerator` emit the `at+jwt` type header RFC 9068 requires from an access token
 * Add the `$resourceMetadataUri` argument to `AccessTokenAuthenticator`, advertised in the `resource_metadata` parameter of the `WWW-Authenticate` header (RFC 9728)
 * Allow a list of identifiers in the `$audience` argument of `OidcTokenHandler` and `OidcTokenGenerator`
 * Add `FallbackAuthenticationEntryPointInterface` for an entry point that only stands in for a firewall declaring no other one, and make `AccessTokenAuthenticator` one
 * Add `OidcAuthorizationRequestEvent` to compute the extra parameters of each OIDC authorization request
 * Allow keying the clients passed to `OidcTokenHandler::enableDiscovery()` by the issuer their discovery document must announce
 * Add the `$limiterName` argument to `LoginThrottlingListener`, which dispatches `RateLimitExceededEvent` when login throttling rejects an attempt

8.1
---

 * Add support for the `clientHints`, `prefetchCache`, and `prerenderCache` `ClearSite-Data` directives
 * Add `this` to `#[IsGranted]` subject expression variables when available
 * Add support for closures and `this` in `#[IsCsrfTokenValid]` when evaluating its `id`
 * Add `$enforceKeyUsageVerification` argument to `OidcTokenHandler::enableDiscovery()` to allow accepting JWKs lacking `use`/`key_ops` (lax mode)
 * Deprecate the `$eraseCredentials` argument of `AuthenticatorManager::__construct()`, as the `eraseCredentials()` method was removed in Symfony 8.0

8.0
---

 * When extending the `RememberMeDetails` class and overriding its constructor, the `$userFqcn` parameter has to be removed from its signature:

   Before:

   ```php
   class CustomRememberMeDetails extends RememberMeDetails
   {
       public function __construct(string $userFqcn, string $userIdentifier, int $expires, string $value)
       {
           parent::__construct($userFqcn, $userIdentifier, $expires, $value);
       }
   }
   ```

   After:

   ```php
   class CustomRememberMeDetails extends RememberMeDetails
   {
       public function __construct(string $userIdentifier, int $expires, string $value)
       {
           parent::__construct($userIdentifier, $expires, $value);
       }
   }
   ```
 * Remove `RememberMeDetails::getUserFqcn()`
 * Remove callable firewall listeners support, extend `AbstractListener` or implement `FirewallListenerInterface` instead
 * Remove `AbstractListener::__invoke`
 * Throw a `BadCredentialsException` when passing an empty string as `$userIdentifier` argument to `UserBadge` constructor
 * Accept only `ExposeSecurityLevel` enums for `AuthenticatorManager`'s `$exposeSecurityErrors` argument
 * Respectively accept only `AlgorithmManager` and `JWKSet` for `OidcTokenHandler`'s `$signatureAlgorithm` and `$signatureKeyset` arguments
 * Add argument `$attributes` to `UserAuthenticatorInterface::authenticateUser()`

7.4
---

 * Deprecate extending the `RememberMeDetails` class with a constructor expecting the user FQCN

   Before:

   ```php
   class CustomRememberMeDetails extends RememberMeDetails
   {
       public function __construct(string $userFqcn, string $userIdentifier, int $expires, string $value)
       {
           parent::__construct($userFqcn, $userIdentifier, $expires, $value);
       }
   }
   ```

   After:

   ```php
   class CustomRememberMeDetails extends RememberMeDetails
   {
       public function __construct(string $userIdentifier, int $expires, string $value)
       {
           parent::__construct($userIdentifier, $expires, $value);
       }
   }
   ```
 * Add support for union types with `#[CurrentUser]`
 * Deprecate callable firewall listeners, extend `AbstractListener` or implement `FirewallListenerInterface` instead
 * Deprecate `AbstractListener::__invoke`
 * Add `$methods` argument to `#[IsGranted]` to restrict validation to specific HTTP methods
 * Allow subclassing `#[IsGranted]`
 * Add `$tokenSource` argument to `#[IsCsrfTokenValid]` to support reading tokens from the query string or headers
 * Deprecate `RememberMeDetails::getUserFqcn()`, the user FQCN will be removed from the remember-me cookie in 8.0
 * Allow configuring multiple OIDC discovery base URIs

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
