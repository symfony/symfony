Symfony Flysystem Key Management Bridge
=======================================

Provides a `FlysystemKeyLoader` and a matching `FlysystemKmsFactory` that
let the local KMS backends shipped by `symfony/key-management` (`SodiumKms`,
`OpenSslKms`, `SealedBoxKms`) source their key material through any
[league/flysystem](https://flysystem.thephpleague.com/) reader: S3, FTP,
SFTP, Azure Blob, Google Cloud Storage, ...

**This Bridge is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

```php
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use Symfony\Component\KeyManagement\Bridge\Flysystem\FlysystemKeyLoader;
use Symfony\Component\KeyManagement\Local\OpenSslKms;

$flysystem = new Filesystem(new AwsS3V3Adapter(/* ... */));

$kms = new OpenSslKms(new FlysystemKeyLoader($flysystem, 'keys', '.bin'));

$ciphertext = $kms->encrypt('app', 'hello world');
$plaintext  = $kms->decrypt($ciphertext);
```

The loader caches each key after its first successful read. The cache lasts for the lifetime of the loader service. Call `reset()` to clear it. To clear a loader registered as a service between requests, opt in by tagging that service `kernel.reset` with `method: reset`.

DSN schemes
-----------

When `KeyManagementBundle` is registered and `key_management` is configured,
this bridge exposes three DSN schemes: one per local backend: that read
keys through Flysystem:

  * `sodium+fly://<flysystem-service-id>/<path>?ext=.bin`
  * `openssl+fly://<flysystem-service-id>/<path>?ext=.bin`
  * `sodium-sealed-box+fly://<flysystem-service-id>/<path>?ext=.bin`

`<flysystem-service-id>` is the host segment of the DSN. With
`league/flysystem-bundle` installed, every storage it declares answers to the
name it was given in `flysystem.yaml`, and there is nothing else to do:

```yaml
flysystem:
    storages:
        keys.storage:
            adapter: 'asyncaws'
            options: { client: 'app.s3_client', bucket: 'kms-keys' }

key_management:
    clients:
        app: 'sodium+fly://keys.storage/keys?ext=.key'
```

DSN clients keep cached keys across requests by default. Add `reset=1` to a Flysystem DSN to clear that client's cache when the bundle's services resetter runs between requests. This also lets a long-running worker pick up a key changed in Flysystem on its next lookup.

A Flysystem instance registered by hand, or one that has to answer to another
name than its service id, is declared by tagging it `key_management.flysystem`
with a `key` attribute equal to the host:

```yaml
services:
    app.keys_filesystem:
        class: League\Flysystem\Filesystem
        arguments: [!service { class: League\Flysystem\Local\LocalFilesystemAdapter, arguments: ['/etc/keys'] }]
        tags:
            - { name: 'key_management.flysystem', key: 'vault' }
```

A tag placed by hand wins: the storages of the bundle are only given one when
they carry none.

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/key-management.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
