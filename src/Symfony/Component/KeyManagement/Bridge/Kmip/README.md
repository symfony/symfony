Symfony KMIP Key Management Bridge
==================================

Provides `EncrypterInterface`, `DecrypterInterface` and `DataKeyGeneratorInterface`
implementations backed by a [KMIP](https://www.oasis-open.org/committees/kmip/) server.
The server keeps the master key and performs authenticated encryption and
decryption. Applications can also wrap locally generated data keys with it.

**This Bridge is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

Install the bridge with `composer require symfony/kmip-key-management`.

Getting started
---------------

Provision an active symmetric key on the KMIP server with Encrypt and Decrypt
in its Cryptographic Usage Mask. Use its KMIP Unique Identifier as `$keyId`.
The key and server must support the selected encryption scheme; AES-GCM is the
default.

```php
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipKms;
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipVersion;
use Symfony\Component\KeyManagement\EnvelopeEncrypter;

$kms = KmipKms::fromTls(
    'kmip.example.org',
    '/run/secrets/kmip-client.crt',
    '/run/secrets/kmip-client.key',
    KmipVersion::Version20,
    5696,
    '/run/secrets/kmip-ca.crt',
);

$keyId = 'your-kmip-unique-identifier';
$ciphertext = $kms->encrypt($keyId, 'hello world', 'context');
$plaintext = $kms->decrypt($ciphertext, 'context');

$envelopes = new EnvelopeEncrypter($kms);
$envelope = $envelopes->encrypt($keyId, 'another secret', 'context');
$envelopePlaintext = $envelopes->decrypt($envelope, 'context');
```

Using encrypted data
--------------------

The `context` argument above is associated data (`$aad`). It is authenticated
but not encrypted; pass the same bytes when decrypting. Persist a direct
`Ciphertext` with both its `keyId` and `blob` intact. Persist a self-contained
`Envelope` as its string value and restore it with `Envelope::fromBytes()`.
Keep the algorithm or vendor-specific scheme registered and the server key
available while data encrypted with them is needed.

Direct encryption sends plaintext to the KMIP server. A complete TTLV message,
including its headers, must fit within 16 MiB, so direct payloads have a lower
limit. `EnvelopeEncrypter` handles larger payloads locally and asks the server
to protect only the data key. With the built-in TLS transport, each KMIP
operation opens a connection. Using a stored data key can reduce KMIP calls
for repeated operations.
Deterministic encryption is not supported.

PyKMIP reports an invalid authentication tag as KMIP `General Failure`
(`0x100`). That reason can also mean a server error, so the bridge leaves it as
a KMIP `RuntimeException`. With PyKMIP, tampered ciphertext or incorrect AAD
can therefore raise `RuntimeException` rather than `DecryptionFailedException`.

Authentication and DSN
----------------------

A client certificate and private key are required, including when KMIP
username and password credentials are supplied. The server certificate is
verified against the `ca` certificate authority file, or PHP's system trust
store when `ca` is omitted. The expected server name defaults to the DSN host;
use `peer_name` if that host differs from the name on the server certificate,
for example when connecting by IP address. TLS verification cannot be disabled.
Use `passphrase` for an encrypted client private key, and keep it and any KMIP
password in application secrets.

```
kmip://[<username>:<password>@]<host>[:<port>]?cert=<client-cert-file>&key=<client-private-key-file>&version=<version>[&ca=<ca-file>][&peer_name=<name>][&passphrase=<passphrase>][&cipher=aes-gcm][&iv_length=16][&timeout=10]
```

`cert`, `key` and `version` are required. The port defaults to 5696 and the
timeout to 10 seconds. Supply both username and password or neither, and
percent-encode reserved characters in credentials and option values.

The bridge supports KMIP 1.4, 2.0 and 2.1 for authenticated Encrypt and
Decrypt. Select the server's version in the DSN, or pass a `KmipVersion` enum
case when constructing `KmipKms` directly. KMIP 1.0 and
1.1 lack Encrypt and Decrypt; 1.2 and 1.3 lack the authentication tag and
associated data fields required by this bridge.

For example:

```
kmip://kmip.example.org:5696?cert=/run/secrets/kmip-client.crt&key=/run/secrets/kmip-client.key&version=2.0&ca=/run/secrets/kmip-ca.crt
```

In a Symfony application, configure a named client with the DSN:

```yaml
key_management:
    clients:
        app: '%env(KMIP_DSN)%'
```

Encryption schemes
------------------

AES-GCM is the recommended default for general use. KMIP defines its algorithm
and block cipher mode in every version supported by this bridge. It needs no
vendor-specific mode value.
`cipher=aes-gcm` uses a 16-byte IV by default. If a server requires a different
length, add `iv_length=12` (or another supported length from 12 to 255 bytes)
to that client's DSN. The ciphertext stores its IV
length while retaining the `aes-gcm` scheme name. Changing the DSN setting
affects new ciphertext; earlier data remains decryptable if the server still
supports its IV length. For direct TLS construction, pass a registry
containing `new AesGcmEncryptionScheme(12)` to `KmipKms::fromTls()`.

ChaCha20-Poly1305 is an optional alternative. To select it, set
`cipher=chacha20-poly1305` after confirming that the server and key support it.
AES-GCM-SIV uses an AES key and also needs the Block Cipher Mode value assigned
by the server, since KMIP 1.4, 2.0 and 2.1 do not assign it a standard value.
To register schemes for Eviden KMS and another server:

```yaml
key_management:
    kmip:
        aes_gcm_siv_schemes:
            aes-gcm-siv/eviden: '0x80000002'
            # Example value: replace it with the other server's documented mode.
            aes-gcm-siv/other-server: '0x80001234'
```

Set `cipher=aes-gcm-siv/eviden` or `cipher=aes-gcm-siv/other-server` in each
client's DSN. Quote hexadecimal mode values so they work on 32-bit PHP. Use
the mode documented by each server, and keep each name mapped to the same
mode while ciphertext created with it is stored. Outside the bundle, pass
`AesGcmSivEncryptionScheme` with the server's mode in a
`KmipEncryptionSchemeRegistry` to the KMIP client or factory.

You can add another algorithm by implementing
[`KmipEncryptionSchemeInterface`](KmipEncryptionSchemeInterface.php).
Service autoconfiguration tags implementations automatically; otherwise, tag
the service `key_management.kmip.encryption_scheme`. Select its unique name
with the DSN's `cipher` option. A direct `KmipKms` or `KmipKmsFactory` can use a
`KmipEncryptionSchemeRegistry` containing application schemes. Implementations
can use the Encrypt, Decrypt and field constants on `KmipRequestClientInterface`
with `Ttlv` to build requests and read responses. Construct `KmipKms` with a
`KmipRequestClientInterface` for a client managed by the application, or use
`KmipKms::fromTls()` for a direct TLS connection. Implementations must
authenticate the supplied associated data, which includes the bridge's
ciphertext header, and mark secret-bearing parameters as sensitive.

Key rotation
------------

Before retiring a master key, migrate data encrypted under it. For the direct
`Ciphertext` and self-contained `Envelope` APIs shown above, decrypt and
re-encrypt the data with the new key. The built-in rewrap command only updates
data keys in a registered `RewrappableDataKeyStoreInterface`; rewrap those
stored keys before revoking or destroying the old master key.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
