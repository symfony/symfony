Symfony Azure Key Vault Key Management Bridge
=============================================

Provides an implementation of `Symfony\Component\KeyManagement\EncrypterInterface`,
`Symfony\Component\KeyManagement\DecrypterInterface` and
`Symfony\Component\KeyManagement\DataKeyGeneratorInterface` backed by
[Azure Key Vault](https://learn.microsoft.com/azure/key-vault/) (and
[Managed HSM](https://learn.microsoft.com/azure/key-vault/managed-hsm/)) over
its REST API. Encryption, decryption and data-key wrapping never expose the
master key: Azure performs them server-side.

**This Bridge is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

```php
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\KeyManagement\Bridge\AzureKeyVault\AzureKeyVault;
use Symfony\Component\KeyManagement\Bridge\AzureKeyVault\ClientCredentialsTokenProvider;

$client = HttpClient::createForBaseUri('https://my-vault.vault.azure.net/');
$tokens = new ClientCredentialsTokenProvider(
    $client,
    $_SERVER['AZURE_TENANT_ID'],
    $_SERVER['AZURE_CLIENT_ID'],
    $_SERVER['AZURE_CLIENT_SECRET'],
);

$kms = new AzureKeyVault($client, $tokens);

// Use the key name; append `/<version>` to pin a specific version.
$ciphertext = $kms->encrypt('app-key', 'hello world');
$plaintext  = $kms->decrypt($ciphertext);

$dataKey = $kms->generateDataKey('app-key', 32);
$result  = $dataKey->use(fn (string $dek): string => /* local AEAD encrypt */);
```

Authentication
--------------

Bring your own [`TokenProviderInterface`](TokenProviderInterface.php) to plug
any Azure AD flow: the bundled `ClientCredentialsTokenProvider` covers the
`client_credentials` grant (tenant id + client id + client secret) and caches
the token in memory until 60 seconds before its advertised expiration.
Managed Identity, Workload Identity, federated credentials, on-behalf-of, ...
are out of scope for the default provider; implement
`TokenProviderInterface` against your platform's metadata endpoint.

Algorithms
----------

The bridge accepts two configurable algorithms:

  * `encryptAlgorithm` (default `RSA-OAEP-256`): used by `encrypt()`/`decrypt()`.
    `RSA-OAEP`, `RSA1_5` and the AEAD variants `A128GCM`/`A192GCM`/`A256GCM`
    are also accepted (the AEAD variants require a symmetric key, available
    on Managed HSM).
  * `wrapAlgorithm` (default `RSA-OAEP-256`): used by `generateDataKey()` and
    `unwrapDataKey()`. Same algorithm set as above; on Managed HSM you can
    use `A256KW` / `A256GCMKW` etc. by setting it explicitly.

`encrypt()` / `decrypt()` go through the RSA path by default and are therefore
suited to small payloads (config secrets, tokens). For arbitrary-size
payloads, use `Symfony\Component\KeyManagement\EnvelopeEncrypter` on top of the bridge:
the master key only sees the wrapped DEK.

DSN scheme
----------

```
azure-keyvault://<clientId>:<clientSecret>@<vault-name>.vault.azure.net?tenant=<tenantId>[&algorithm=...&wrap_algorithm=...&api_version=...]
```

The host is the full vault DNS (`<name>.vault.azure.net`,
`<name>.managedhsm.azure.net` for Managed HSM, or the US-government
`<name>.vault.usgovcloudapi.net`). The audience for token acquisition is
inferred from the host. Examples:

```
azure-keyvault://CLIENT_ID:CLIENT_SECRET@my-vault.vault.azure.net?tenant=TENANT_ID
azure-keyvault://CLIENT_ID:CLIENT_SECRET@my-vault.vault.azure.net?tenant=TENANT_ID&algorithm=RSA-OAEP&api_version=7.4
azure-keyvault://CLIENT_ID:CLIENT_SECRET@my-hsm.managedhsm.azure.net?tenant=TENANT_ID&algorithm=A256GCM&wrap_algorithm=A256KW
```

AAD support
-----------

AEAD algorithms (`A128GCM`/`A192GCM`/`A256GCM`) accept Azure's `aad`
parameter natively and are integrity-protected. RSA algorithms have no AAD
concept; passing a non-empty `$aad` to an RSA-configured bridge raises
`UnsupportedOperationException`.

For AEAD ciphertexts, this bridge concatenates the algorithm, IV, tag and
value into a single dot-separated blob (`<alg>.<iv>.<tag>.<value>`, all
base64url) so callers can store one opaque ciphertext and let the bridge
recover its parts on decrypt. The pieces themselves are exactly what Azure
returns; nothing is added to or stripped from them.

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/key-management.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
