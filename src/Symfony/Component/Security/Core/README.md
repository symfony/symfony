Security Component - Core
=========================

Security provides an infrastructure for sophisticated authorization systems,
which makes it possible to easily separate the actual authorization logic from
so called user providers that hold the users credentials.

Getting Started
---------------

```bash
composer require symfony/security-core
```

```php
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Symfony\Component\Security\Core\Authorization\Voter\RoleHierarchyVoter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

$accessDecisionManager = new AccessDecisionManager([
    new AuthenticatedVoter(new AuthenticationTrustResolver()),
    new RoleVoter(),
    new RoleHierarchyVoter(new RoleHierarchy([
        'ROLE_ADMIN' => ['ROLE_USER'],
    ]))
]);

$user = new \App\Entity\User(...);
$token = new UsernamePasswordToken($user, 'main', $user->getRoles());

if (!$accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
    throw new AccessDeniedException();
}
```

Sponsor
-------

The Security component for Symfony 8.0 is [backed][1] by [SymfonyCasts][2].

Learn Symfony faster by watching real projects being built and actively coding
along with them. SymfonyCasts bridges that learning gap, bringing you video
tutorials and coding challenges. Code on!

Help Symfony by [sponsoring][3] its development!

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/security.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)

[1]: https://symfony.com/backers
[2]: https://symfonycasts.com
[3]: https://symfony.com/sponsor
