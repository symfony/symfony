Encryption Component
====================

The Encryption component provides safe-by-default cryptography: symmetric and
asymmetric encryption, digital signatures, X.509 certificate management,
hashing, and timing-safe comparison, over Sodium, OpenSSL and phpseclib.

Getting Started
---------------

```bash
composer require symfony/encryption
```

```php
use Symfony\Component\Encryption\Encryption;

$encryption = new Encryption();
$symmetric = $encryption->symmetric();

$key = $symmetric->generateKey();
$ciphertext = $symmetric->encrypt('secret data', $key);
$plaintext = $symmetric->decrypt($ciphertext, $key);
```

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/encryption.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
