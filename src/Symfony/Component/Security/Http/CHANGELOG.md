CHANGELOG
=========

5.4
---

 * Deprecate the `$authManager` argument of `AccessListener`
 * Deprecate not setting the `$exceptionOnNoToken` argument of `AccessListener` to `false`
 * Deprecate `DeauthenticatedEvent`, use `TokenDeauthenticatedEvent` instead
 * Deprecate `CookieClearingLogoutHandler`, `SessionLogoutHandler` and `CsrfTokenClearingLogoutHandler`.
   Use `CookieClearingLogoutListener`, `SessionLogoutListener` and `CsrfTokenClearingLogoutListener` instead
 * Deprecate `PassportInterface`, `UserPassportInterface` and `PassportTrait`, use `Passport` instead

5.3
---

The CHANGELOG for version 5.3 and earlier can be found at https://github.com/symfony/symfony/blob/5.3/src/Symfony/Component/Security/CHANGELOG.md
