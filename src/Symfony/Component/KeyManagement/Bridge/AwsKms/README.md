Symfony AWS Key Management Bridge
=================================

Provides an implementation of `Symfony\Component\KeyManagement\EncrypterInterface` and
`Symfony\Component\KeyManagement\DataKeyGeneratorInterface` backed by
[AWS Key Management Service](https://aws.amazon.com/kms/) through the
lightweight [`async-aws/kms`](https://async-aws.com/) client. Encryption,
decryption and data-key generation never expose the master key: AWS performs
them server-side.

**This Bridge is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

```php
use AsyncAws\Core\Configuration;
use AsyncAws\Kms\KmsClient;
use Symfony\Component\KeyManagement\Bridge\AwsKms\AwsKms;

$kms = new AwsKms(new KmsClient(Configuration::create([
    'region' => 'eu-west-1',
    // 'accessKeyId' / 'accessKeySecret' / 'sessionToken' are optional;
    // by default async-aws walks the standard credential provider chain.
])));

// Use the key ARN, key id, or alias (e.g. "alias/app-key").
$ciphertext = $kms->encrypt('alias/app-key', 'hello world');
$plaintext  = $kms->decrypt($ciphertext);

$dataKey = $kms->generateDataKey('alias/app-key', 32);
$result  = $dataKey->use(fn (string $dek): string => /* local AEAD encrypt */);
```

Each ciphertext and wrapped data key records the ARN of the key that AWS used, not the alias.
Moving an alias to a new key leaves older ciphertexts readable, as long as the previous key stays enabled.

DSN scheme
----------

```
aws-kms://[<accessKey>:<secretKey>@]<host>[:<port>]?region=<region>[&session_token=<token>&timeout=<seconds>&max_duration=<seconds>]
```

The host `default` selects the public AWS endpoint for the given region;
any other host is treated as a custom endpoint (LocalStack, VPC endpoint,
...). Leaving the credentials out lets `async-aws` fall back to the
standard provider chain (env vars, instance profile, ...).

The `timeout` (idle timeout) and `max_duration` (whole request) options set
the HTTP client's timeouts. When the factory is given no client, it builds one
with a 5-second `timeout` and a 10-second `max_duration`. That client uses the
retry strategy of `async-aws`. A client given to the factory, like the
application's `http_client`, keeps its own timeouts unless the DSN sets them.

Requests don't follow redirects, whatever the client. On a 307 or 308 redirect
to another host, the session token and the plaintext would be sent again. When
you build the `KmsClient` yourself, give it a client that doesn't follow
redirects.

Examples:

```
aws-kms://default?region=eu-west-1
aws-kms://AKIA...:secret@default?region=us-east-1&session_token=TOKEN
aws-kms://localhost:4566?region=eu-west-1
```

AAD support
-----------

AWS KMS exposes additional authenticated data through `EncryptionContext`,
which is restricted to `array<string, string>`. To stay compatible with the
opaque-bytes contract of `EncrypterInterface`, this bridge stores the AAD as a
single base64-encoded entry under a conventional key. Cross-bridge
interoperability is therefore not guaranteed: a ciphertext produced by this
bridge with a non-empty AAD only round-trips through the same bridge.

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/key-management.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
