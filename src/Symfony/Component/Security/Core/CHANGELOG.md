CHANGELOG
=========

6.2
---

 * Deprecate the `Security` class, use `Symfony\Bundle\SecurityBundle\Security` instead
 * Change the signature of `TokenStorageInterface::setToken()` to `setToken(?TokenInterface $token)`
 * Deprecate calling `TokenStorage::setToken()` without arguments
 * Add a `ChainUserChecker` to allow calling multiple user checkers for a firewall

6.0
---

 * `TokenInterface` does not extend `Serializable` anymore
 * Remove all classes in the `Core\Encoder\`  sub-namespace, use the `PasswordHasher` component instead
 * Remove methods `getPassword()` and `getSalt()` from `UserInterface`, use `PasswordAuthenticatedUserInterface`
   or `LegacyPasswordAuthenticatedUserInterface` instead
* `AccessDecisionManager` requires the strategy to be passed as in instance of `AccessDecisionStrategyInterface`

5.4.21
------

 * [BC BREAK] `AccessDecisionStrategyTestCase::provideStrategyTests()` is now static

5.4
---

 * Add a `CacheableVoterInterface` for voters that vote only on identified attributes and subjects
 * Deprecate `AuthenticationEvents::AUTHENTICATION_FAILURE`, use the `LoginFailureEvent` instead
 * Deprecate `AnonymousToken`, as the related authenticator was deprecated in 5.3
 * Deprecate `Token::getCredentials()`, tokens should no longer contain credentials (as they represent authenticated sessions)
 * Deprecate returning `string|\Stringable` from `Token::getUser()` (it must return a `UserInterface`)
 * Deprecate `AuthenticatedVoter::IS_AUTHENTICATED_ANONYMOUSLY` and `AuthenticatedVoter::IS_ANONYMOUS`,
   use `AuthenticatedVoter::IS_AUTHENTICATED_FULLY` or `AuthenticatedVoter::IS_AUTHENTICATED` instead.
 * Deprecate `AuthenticationTrustResolverInterface::isAnonymous()` and the `is_anonymous()` expression
   function as anonymous no longer exists in version 6, use the `isFullFledged()` or the new
   `isAuthenticated()` instead if you want to check if the request is (fully) authenticated.
 * Deprecate the `$authenticationManager` argument of the `AuthorizationChecker` constructor
 * Deprecate setting the `$alwaysAuthenticate` argument to `true` and not setting the
   `$exceptionOnNoToken` argument to `false` of `AuthorizationChecker`
 * Deprecate methods `TokenInterface::isAuthenticated()` and `setAuthenticated`,
   return null from "getUser()" instead when a token is not authenticated
 * Add `AccessDecisionStrategyInterface` to allow custom access decision strategies
 * Add access decision strategies `AffirmativeStrategy`, `ConsensusStrategy`, `PriorityStrategy`, `UnanimousStrategy`
 * Deprecate passing the strategy as string to `AccessDecisionManager`,
   pass an instance of `AccessDecisionStrategyInterface` instead
 * Flag `AccessDecisionManager` as `@final`

5.3
---

The CHANGELOG for version 5.3 and earlier can be found at https://github.com/symfony/symfony/blob/5.3/src/Symfony/Component/Security/CHANGELOG.md
