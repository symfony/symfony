PasswordHasher Component
========================

The PasswordHasher component provides secure password hashing utilities.

Getting Started
---------------

```bash
composer require symfony/password-hasher
```

```php
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;

// Configure different password hashers via the factory
$factory = new PasswordHasherFactory([
    'common' => ['algorithm' => 'bcrypt'],
    'memory-hard' => ['algorithm' => 'sodium'],
]);

// Retrieve the right password hasher by its name
$passwordHasher = $factory->getPasswordHasher('common');

// Hash a plain password
$hash = $passwordHasher->hash('plain'); // returns a bcrypt hash

// Verify that a given plain password matches the hash
$passwordHasher->verify($hash, 'wrong'); // returns false
$passwordHasher->verify($hash, 'plain'); // returns true (valid)
```

Sponsor
-------

This package is looking for a [backer][1].

Help Symfony by [sponsoring][3] its development!

Resources
---------

 * [Documentation](https://symfony.com/doc/current/security.html#c-hashing-passwords)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)

[1]: https://symfony.com/backers
[3]: https://symfony.com/sponsor
