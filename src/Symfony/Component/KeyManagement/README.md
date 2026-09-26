KeyManagement Component
=======================

The KeyManagement component provides a unified abstraction over Key Management
Systems such as AWS KMS, Azure Key Vault, Google Cloud KMS, HashiCorp Vault
Transit and KMIP servers. It exposes a small high-level API for
encrypting/decrypting payloads, generating data keys for envelope encryption,
and is designed so that the secret material never leaves the underlying KMS. A
client made of several providers keeps every ciphertext readable when one of
them is lost.

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
// symfony/hashicorp-vault-key-management, symfony/kmip-key-management,
// symfony/flysystem-key-management, ...).
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
README. `symfony/doctrine-dbal-key-management` ships a Doctrine DBAL Type that
decorates any parent Type with column-level envelope encryption, and
`symfony/doctrine-orm-key-management` the attribute filling a blind index on
flush.

Searching an encrypted column
-----------------------------

Encryption is randomized, so two encryptions of the same value differ and
`WHERE email = ?` never matches. A blind index keeps a searchable trace in a
sibling column: a keyed digest of the value, equal for equal values. It takes a
data key of its own, a name, and what of the value to index.

```php
use Symfony\Component\KeyManagement\BlindIndex;
use Symfony\Component\KeyManagement\BlindIndex\Projection\Email;

$index = new BlindIndex($kms, $wrappedIndexKey, 'user-email', new Email());

$user->setEmailIndex($index->of($email));                      // on the way in
$repository->findOneBy(['emailIndex' => $index->of($email)]);  // and on the way out
```

The key is minted once with `key-management:generate-data-key` and kept wrapped
in the configuration, a `Ciphertext` of the `key_id` and `wrapped` values that
command prints, unwrapped once per process rather than reached for per value.
It must never rotate: every tag already written was derived under it.
`StoredKeyBlindIndex` names a key a store holds instead. Tags are derived under
a subkey named by the index, so one key serves several indexes and no two of
them tag a value alike.

The projection says what of the value is indexed: `Projection\Verbatim` folds
nothing, `Projection\Email` and `Projection\EmailDomain` ship as well, and
anything else implements `ProjectionInterface`. A `CoveringProjectionInterface`
covers several forms of one value, every bucket a number falls in or every
prefix of a name, and `allOf()` then gives a tag per form for a column that
holds them all. It leaks more than the note below: the number of tags is the
number of forms, and rows sharing a form share a tag.

Equal values give equal tags, so the column tells anyone reading it which rows
share a value and how often each occurs. Index what is high-entropy and looked
up by equality, and leave the rest to a decrypted scan.

On a Doctrine entity, `symfony/doctrine-orm-key-management` writes the tag
itself on every flush:

```php
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\BlindIndexed;

#[ORM\Column(type: 'encrypted_string', length: 180)]
private string $email = '';

#[ORM\Column(length: 64)]
#[BlindIndexed('email', 'user-email')]
private string $emailIndex = '';
```

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/key-management.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
