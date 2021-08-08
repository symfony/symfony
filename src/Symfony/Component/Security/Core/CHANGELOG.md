CHANGELOG
=========

5.4
---

 * Deprecate the `$authenticationManager` argument of the `AuthorizationChecker` constructor
 * Deprecate setting the `$alwaysAuthenticate` argument to `true` and not setting the
   `$exceptionOnNoToken` argument to `false` of `AuthorizationChecker`
 * Deprecate methods `TokenInterface::isAuthenticated()` and `setAuthenticated`,
   tokens will always be considered authenticated in 6.0

5.3
---

The CHANGELOG for version 5.3 and earlier can be found at https://github.com/symfony/symfony/blob/5.3/src/Symfony/Component/Security/CHANGELOG.md
