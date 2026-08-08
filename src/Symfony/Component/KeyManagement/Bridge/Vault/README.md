Symfony HashiCorp Vault Key Management Bridge
=============================================

Provides an implementation of `Symfony\Component\KeyManagement\EncrypterInterface` and
`Symfony\Component\KeyManagement\DataKeyGeneratorInterface` backed by the
[HashiCorp Vault Transit](https://developer.hashicorp.com/vault/docs/secrets/transit)
secret engine. Encryption, decryption and data-key generation never expose
the master key: Vault performs them server-side.

**This Bridge is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

```php
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\KeyManagement\Bridge\Vault\TransitKms;

$kms = new TransitKms(
    HttpClient::createForBaseUri('https://vault.example.com:8200/v1/'),
    $_SERVER['VAULT_TOKEN'],
);

$ciphertext = $kms->encrypt('app-key', 'hello world');
// → Ciphertext blob looks like "vault:v1:..."

$plaintext = $kms->decrypt($ciphertext);

$dataKey = $kms->generateDataKey('app-key', 32);
$result = $dataKey->use(fn (string $dek): string => /* local AEAD encrypt */);
```

DSN scheme
----------

```
vault-transit://<token>@<host>[:<port>][/<path>][?mount=<mount>&namespace=<ns>]
```

The HTTP base URI is built from `<host>[:<port>]<path>`; if `<path>` is empty
it defaults to `/v1/`. The Vault token is taken from the user component of
the DSN. Default mount point is `transit`. The `namespace` option maps to
Vault's `X-Vault-Namespace` header (Vault Enterprise multi-tenancy).

Example:

```
vault-transit://s.token@vault.example.com:8200/v1/?mount=transit&namespace=tenant-acme
```

AAD support
-----------

The `$aad` argument is forwarded as Vault's `context` parameter (HKDF
context, base64-encoded). This works for keys created with `derived=true`.
Vault does not reject a context on non-derived keys, it silently ignores it,
which would make an AAD mismatch decrypt fine. The bridge therefore reads the
key configuration once per key (`GET <mount>/keys/<name>`) when a non-empty
`$aad` is used and refuses AAD for non-derived keys with an
`UnsupportedOperationException`.

Note that Vault's `context` is a key-derivation input, not authenticated data
in the AEAD sense. The practical effect is similar (different context →
unable to decrypt) but the security model differs slightly from backends like
AWS KMS, where `EncryptionContext` is integrity-protected. AAD is treated as
opaque bytes; if you have structured data, serialize it (e.g. canonical JSON
with sorted keys) yourself and pass the resulting string.

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/key-management.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
