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

Searching an encrypted column
-----------------------------

Encryption is randomized, so two encryptions of the same value differ and
`WHERE email = ?` never matches. A blind index keeps a searchable trace in a
sibling column: a keyed digest of the value, equal for equal values.

```php
use Symfony\Component\KeyManagement\BlindIndex\Email;

// Minted once with "key-management:generate-data-key", kept wrapped in the configuration.
$index = new Email($kms, $wrappedIndexKey);

$user->setEmail($email);
$user->setEmailIndex($index->of($email));                      // on the way in
$repository->findOneBy(['emailIndex' => $index->of($email)]);  // and on the way out
```

The tags are keyed HMACs over a data key of its own, unwrapped once per process,
so every backend can drive an index and the KMS is not reached per value. That
key must never rotate: every index already written was derived under it.

Which part of the value is indexed is the subclass. `BlindIndex\Email` folds the
domain and leaves the local part alone, which is what RFC 5321 says about each;
`BlindIndex\EmailDomain` keeps the domain only; anything else overrides
`project()`. That is also how far a partial search goes here: rather than making
one index searchable by pieces, name the question and index the answer in a
column of its own.

Equal values give equal tags, so the column tells anyone reading it which rows
share a value and how often each occurs. Index what is high-entropy and looked
up by equality, and leave the rest to a decrypted scan.

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/key-management.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
