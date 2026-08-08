KeyManagement Component
=======================

The KeyManagement component provides a unified abstraction over Key Management
Systems such as AWS KMS, Azure Key Vault, Google Cloud KMS and HashiCorp Vault
Transit. It exposes a small high-level API for encrypting/decrypting payloads,
generating data keys for envelope encryption, and is designed so that the
secret material never leaves the underlying KMS.

**This Component is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

Getting Started
---------------

```bash
composer require symfony/key-management
```

```php
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\SodiumKms;
use Symfony\Component\KeyManagement\Envelope;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;

// Local libsodium-backed provider, suitable for tests and development.
// The component also ships `OpenSslKms` (AES-256-GCM, no ext-sodium
// requirement) and `SealedBoxKms` (asymmetric). Cloud and Flysystem
// backends ship as separate bridges (symfony/aws-key-management,
// symfony/vault-key-management, symfony/flysystem-key-management, ...).
$kms = new SodiumKms(new InMemoryKeyLoader([
    'app-key' => sodium_crypto_aead_xchacha20poly1305_ietf_keygen(),
]));

// Direct mode: short payloads (config secrets, tokens, ...).
$ciphertext = $kms->encrypt('app-key', 'hello world');
$plaintext  = $kms->decrypt($ciphertext);

// Envelope mode: arbitrary-size payloads (files, DB rows, ...). The KMS only
// sees the wrapped data key, never the bulk plaintext. The Envelope is
// self-contained (it carries the wrapped DEK, IV, tag and ciphertext);
// persist it as-is. Same flow regardless of which KMS backend is used.
$envelopeEncrypter = new EnvelopeEncrypter($kms);

$envelope = $envelopeEncrypter->encrypt('app-key', $payload);
file_put_contents($path, $envelope);

$payload = $envelopeEncrypter->decrypt(Envelope::fromBytes(file_get_contents($path)));
```

Each bridge under `Symfony\Component\KeyManagement\Bridge\` is published as its
own Composer package and documents the DSN schemes it supports in its own
README.

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/key-management.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
