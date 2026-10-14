Symfony Scaleway Key Management Bridge
======================================

Provides an implementation of `Symfony\Component\KeyManagement\EncrypterInterface` and
`Symfony\Component\KeyManagement\DataKeyGeneratorInterface` backed by
[Scaleway Key Manager](https://www.scaleway.com/en/docs/key-manager/).
The key never leaves Scaleway: encryption and decryption happen server-side,
and data keys are generated locally then wrapped by Scaleway.

**This Bridge is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

```php
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\KeyManagement\Bridge\Scaleway\ScalewayKms;

$kms = new ScalewayKms(
    HttpClient::createForBaseUri('https://api.scaleway.com/key-manager/v1alpha1/regions/fr-par/', ['max_redirects' => 0]),
    $_SERVER['SCW_SECRET_KEY'],
);

// the key id is the UUID of a Key Manager key whose usage is "symmetric_encryption"
$ciphertext = $kms->encrypt('11111111-2222-3333-4444-555555555555', 'hello world');
$plaintext = $kms->decrypt($ciphertext);

$dataKey = $kms->generateDataKey('11111111-2222-3333-4444-555555555555');
$result = $dataKey->use(fn (string $dek): string => /* local AEAD encrypt */);
```

DSN scheme
----------

```
scaleway-kms://<secret_key>@<host>[:<port>]?region=<region>[&timeout=<seconds>&max_duration=<seconds>]
```

The host `default` selects the public Scaleway API (`https://api.scaleway.com`);
any other host is used as a custom HTTPS endpoint. The `region` option is
required (`fr-par`, `nl-ams`, `pl-waw`, ...). The secret key of an IAM API key
is taken from the user component of the DSN and sent in the `X-Auth-Token`
header; a DSN with a password, like the `PROJECT_ID:SECRET_KEY` form of the
Scaleway Mailer bridge, is refused since Key Manager calls don't need the
project id. The API key needs the `KeyManagerFullAccess` permission set, or a
narrower one granting the encrypt, decrypt and data key operations.

The `timeout` (idle timeout) and `max_duration` (whole request) options set
the HTTP client's timeouts. When the factory is given no client, it builds one
with a 5-second `timeout` and a 10-second `max_duration`. Requests don't follow
redirects, whatever the client, since the `X-Auth-Token` header would follow a
redirect to another host.

Example:

```
scaleway-kms://%env(SCW_SECRET_KEY)%@default?region=fr-par
```

AAD support
-----------

The `$aad` argument is forwarded as Scaleway's `associated_data` parameter,
which is authenticated by AES-256-GCM: decrypting with a different AAD fails.

`generateDataKey()` generates the data key locally with `random_bytes()` and
wraps it through the `encrypt` endpoint, with the AAD when one is given. This
works for any length from 16 bytes, and with symmetric and asymmetric keys.
Scaleway's `generate-data-key` endpoint is not used: it only makes 32-byte
keys, cannot bind associated data and refuses asymmetric keys.

Limitations
-----------

 * Data keys are at least 16 bytes long.
 * Scaleway encrypts at most 64 KiB per call; use envelope encryption for
   larger payloads.
 * Deterministic encryption is not supported.
 * On decryption, HTTP 400 and 404 are reported as a `DecryptionFailedException`
   so that a caller cannot tell an unknown key from a tampered ciphertext;
   authentication, quota and server errors are reported as a `RuntimeException`.

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/key-management.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
