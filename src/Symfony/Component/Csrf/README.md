CSRF Component
==============

The CSRF (cross-site request forgery) component provides a class
`CsrfTokenManager` for generating and validating CSRF tokens.

Getting Started
---------------

```
$ composer require symfony/csrf
```

```php
use Symfony\Component\Csrf\CsrfToken;
use Symfony\Component\Csrf\CsrfTokenManager;

$csrfTokenManager = new CsrfTokenManager();
$csrfToken = new CsrfToken('csrftokenid', $_POST['_csrf_param']);
if (!$csrfTokenManager->isTokenValid($csrfToken)) {
    // ... invalid CSRF token
}
```

Resources
---------

  * [Documentation](https://symfony.com/doc/current/security/csrf.html)
  * [Contributing](https://symfony.com/doc/current/contributing/index.html)
  * [Report issues](https://github.com/symfony/symfony/issues) and
    [send Pull Requests](https://github.com/symfony/symfony/pulls)
    in the [main Symfony repository](https://github.com/symfony/symfony)
