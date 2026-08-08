Symfony Google Cloud Key Management Bridge
==========================================

Provides an implementation of `Symfony\Component\KeyManagement\EncrypterInterface`,
`Symfony\Component\KeyManagement\DecrypterInterface` and
`Symfony\Component\KeyManagement\DataKeyGeneratorInterface` backed by
[Google Cloud KMS](https://cloud.google.com/kms/docs) over its REST API.
Encryption, decryption and data-key wrapping never expose the master key:
Cloud KMS performs them server-side.

**This Bridge is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

```php
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\KeyManagement\Bridge\GoogleCloudKms\GoogleCloudKms;
use Symfony\Component\KeyManagement\Bridge\GoogleCloudKms\ServiceAccountTokenProvider;

$client = HttpClient::createForBaseUri('https://cloudkms.googleapis.com/v1/');
$tokens = ServiceAccountTokenProvider::fromJsonFile($client, '/path/to/service-account.json');

$kms = new GoogleCloudKms($client, $tokens);

// $keyId is the Cloud KMS resource name; pin a version with
// `.../cryptoKeyVersions/<n>` if needed.
$ciphertext = $kms->encrypt(
    'projects/my-project/locations/global/keyRings/app/cryptoKeys/master',
    'hello world',
);
$plaintext = $kms->decrypt($ciphertext);

$dataKey = $kms->generateDataKey(
    'projects/my-project/locations/global/keyRings/app/cryptoKeys/master',
    32,
);
$result = $dataKey->use(fn (string $dek): string => /* local AEAD encrypt */);
```

Authentication
--------------

The bundled `ServiceAccountTokenProvider` covers the most common case: a
JSON service-account key downloaded from the GCP console. The provider
signs a JWT with the account's RSA private key (RS256) and exchanges it for
an OAuth2 access token at the Google token endpoint, caching the result
until 60s before expiration.

For Application Default Credentials, the GCE/GKE/Cloud Run metadata
server, Workload Identity Federation, or any other flow, implement
`TokenProviderInterface` against your platform.

DSN scheme
----------

```
gcp-kms://default?credentials=/path/to/service-account.json
```

The host `default` selects the public Cloud KMS endpoint
(`https://cloudkms.googleapis.com/v1/`); any other host is treated as a
custom endpoint. The `credentials` option must point at a service-account
JSON key file.

AAD support
-----------

The `$aad` argument maps to Cloud KMS's `additionalAuthenticatedData`,
which is integrity-protected through the AEAD cipher used by the master
key. AAD is treated as opaque bytes; structured callers should serialize
to a stable form (e.g. canonical JSON) themselves.

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/key-management.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
