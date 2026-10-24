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
use Symfony\Component\KeyManagement\Bridge\HashiCorpVault\StaticTokenProvider;
use Symfony\Component\KeyManagement\Bridge\HashiCorpVault\TransitKms;

$kms = new TransitKms(
    HttpClient::createForBaseUri('https://vault.example.com:8200/v1/'),
    new StaticTokenProvider($_SERVER['VAULT_TOKEN']),
);

$ciphertext = $kms->encrypt('app-key', 'hello world');
// → Ciphertext blob looks like "vault:v1:..."

$plaintext = $kms->decrypt($ciphertext);

$dataKey = $kms->generateDataKey('app-key', 32);
$result = $dataKey->use(fn (string $dek): string => /* local AEAD encrypt */);
```

Authentication
--------------

`TransitKms` gets its Vault token from a
[`TokenProviderInterface`](TokenProviderInterface.php):

 * `StaticTokenProvider` returns a token obtained out of band, like the one of
   the DSN;
 * `KubernetesAuthTokenProvider` logs in to the
   [Kubernetes auth method](https://developer.hashicorp.com/vault/docs/auth/kubernetes)
   with the pod's service-account token, so that no long-lived Vault token or
   sidecar is needed. The service-account token is read at each login, since
   the kubelet rotates it, and the Vault token is cached in memory until
   60 seconds before its lease ends.

```php
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\KeyManagement\Bridge\HashiCorpVault\KubernetesAuthTokenProvider;
use Symfony\Component\KeyManagement\Bridge\HashiCorpVault\TransitKms;

$client = HttpClient::createForBaseUri('https://vault.example.com:8200/v1/');
$kms = new TransitKms($client, new KubernetesAuthTokenProvider($client, 'my-app'));
```

The provider takes the auth mount point (`kubernetes` by default), the path
of the service-account token and the Vault Enterprise namespace as further
arguments. Other auth methods are plugged in by implementing
`TokenProviderInterface`. In a Symfony application, define such a `TransitKms`
service yourself and reference it as `service://<id>` in
`key_management.clients`.

The Vault token is cached in memory, so each PHP process logs in on its own.
With PHP-FPM, every request logs in again and gets a new Vault token, which
Vault stores until its lease ends. To avoid that, use a worker runtime like
FrankenPHP. You can also wrap the provider in your own `TokenProviderInterface`
that caches the token in a store shared by all processes.

Since version 1.17, Vault answers an expired or revoked token with HTTP 403 and
an "invalid token" error. The bridge then calls `invalidateToken()`, gets a new
token and retries the request once if that token differs. Any other 403, like a
policy that lacks a capability, is reported directly without invalidating the
token, since a new token would not fix it. Older versions only answer
"permission denied", so the request is not retried.

DSN scheme
----------

```
hashicorp-vault-transit://<token>@<host>[:<port>][/<path>][?mount=<mount>&namespace=<ns>&timeout=<seconds>&max_duration=<seconds>]
```

The HTTP base URI is built from `<host>[:<port>]<path>`; if `<path>` is empty
it defaults to `/v1/`. The Vault token is taken from the user component of
the DSN. Default mount point is `transit`. The `namespace` option maps to
Vault's `X-Vault-Namespace` header (Vault Enterprise multi-tenancy).

The `timeout` (idle timeout) and `max_duration` (whole request) options set
the HTTP client's timeouts. When the factory is given no client, it builds one
with a 5-second `timeout` and a 10-second `max_duration`. A client given to the
factory, like the application's `http_client`, keeps its own settings unless
the DSN sets the timeouts. Requests don't follow redirects, whatever the
client, since the `X-Vault-Token` header would follow a redirect to another
host.

Example:

```
hashicorp-vault-transit://s.token@vault.example.com:8200/v1/?mount=transit&namespace=tenant-acme
```

AAD support
-----------

The bridge passes `base64_encode("symfony/key-management/hashicorp-vault/transit/v1\0".$aad)` as Vault's `context` parameter for every request.
This framing keeps arbitrary binary AAD distinct, including an empty AAD.
Vault uses the context as HKDF input for keys created with `derived=true`.
Vault silently ignores the context on non-derived keys.
For nonempty AAD, the bridge reads each key's configuration once with `GET <mount>/keys/<name>`.
It throws `UnsupportedOperationException` if the key is non-derived.

That preflight is a read on the key itself, so a token whose policy only
grants `update` on the encryption endpoints is no longer enough as soon as an
AAD is passed: the request fails with a `RuntimeException` reporting HTTP 403.
Grant `read` on `<mount>/keys/*` too:

```hcl
path "transit/encrypt/*"           { capabilities = ["update"] }
path "transit/decrypt/*"           { capabilities = ["update"] }
path "transit/datakey/plaintext/*" { capabilities = ["update"] }
# only needed when AAD is used, to tell derived keys from non-derived ones
path "transit/keys/*"              { capabilities = ["read"] }
```

Derived keys require a nonempty context.
The framing supplies a nonempty context even when the caller passes no AAD.
The key configuration is read only for nonempty AAD, so the `read` permission above stays optional.
With `convergent_encryption=true`, equal plaintexts with the same AAD produce equal ciphertexts.
Different AAD values create different convergence scopes.

Note that Vault's `context` is a key-derivation input, not authenticated data
in the AEAD sense. The practical effect is similar (different context →
unable to decrypt) but the security model differs slightly from backends like
AWS KMS, where `EncryptionContext` is integrity-protected. AAD is treated as
opaque bytes; if you have structured data, serialize it (e.g. canonical JSON
with sorted keys) yourself and pass the resulting string.

Migrating derived keys
----------------------

Migrate existing values under derived keys before switching readers.
Before framing, the nonempty AAD was used directly as the context.
The previous fallback for empty AAD was `symfony/key-management/hashicorp-vault/transit/v1`.
Read each old ciphertext with the old implementation or Vault's API, using the trusted original AAD and context.
Encrypt the plaintext with the current implementation.
Non-derived keys with empty AAD need no migration.

For persisted wrapped data keys, unwrap with the old context and encrypt the same key bytes with the new context.
Keep the plaintext key unchanged so existing payloads and blind indexes remain valid.
Replace the persisted wrapped value, including wrapped keys inside self-contained envelopes.
`EnvelopeEncrypter` wraps its data keys with empty AAD; the payload AAD is authenticated locally.
For older envelopes that also passed the payload AAD to the KMS, unwrap with that original AAD before rewrapping with empty AAD.
The current `key-management:rewrap-data-keys` command uses the new reader and cannot migrate old contexts by itself.

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/key-management.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
