CHANGELOG
=========

7.2
---

 * Add `SameOriginCsrfTokenManager`

6.0
---

 * Remove the `SessionInterface $session` constructor argument of `SessionTokenStorage`, inject a `\Symfony\Component\HttpFoundation\RequestStack $requestStack` instead
 * Using `SessionTokenStorage` outside a request context throws a `SessionNotFoundException`

5.3
---

The CHANGELOG for version 5.3 and earlier can be found at https://github.com/symfony/symfony/blob/5.3/src/Symfony/Component/Security/CHANGELOG.md
