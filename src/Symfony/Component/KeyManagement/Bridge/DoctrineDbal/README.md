Symfony Doctrine DBAL Key Management Bridge
===========================================

Provides a Doctrine DBAL Type that decorates any other Type with column-level
encryption powered by `Symfony\Component\KeyManagement\EnvelopeEncrypterInterface`.

Each row is stored as a self-contained KeyManagement `Envelope` (master keyId,
wrapped data key, IV, tag, ciphertext) so the master KMS keeps full control of
the key material and rows can be re-encrypted independently.

**This Bridge is experimental**.
[Experimental features](https://symfony.com/doc/current/contributing/code/experimental.html)
are not covered by Symfony's
[Backward Compatibility Promise](https://symfony.com/doc/current/contributing/code/bc.html).

```php
use Doctrine\DBAL\Types\Type;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\EncryptedType;

$type = new EncryptedType(
    Type::getTypeRegistry()->get('string'),
    $envelopeEncrypter,
    'alias/app-key',
);

Type::getTypeRegistry()->register('app_user_email', $type);
```

The registered name is then usable anywhere DBAL accepts a type, so plain
DBAL applications encrypt and decrypt without any ORM involved:

```php
$connection->insert('user', ['email' => 'jane@example.com'], ['email' => 'app_user_email']);

$email = $connection->convertToPHPValue(
    $connection->fetchOne('SELECT email FROM user WHERE id = ?', [$id]),
    'app_user_email',
);
```

Doctrine ORM entities reference the registered name like any other type:

```php
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class User
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(type: 'app_user_email')]
    public string $email;
}
```

Where to register the types
---------------------------

One instance is registered per `(envelope encrypter, master key id, parent
type)` combination, and the registration must happen before the first query,
on every request.

In particular, do not defer it to Doctrine ORM's `loadClassMetadata` event: the
event does not fire when metadata comes from the cache, which DoctrineBundle
enables in production, so a cached field mapping would reference a type name
that is missing from the registry and every read of the column would fail with
`UnknownColumnType`. Declare the types where the application declares the rest
of its Doctrine configuration instead.

`doctrine.dbal.types` is not that place, and cannot be: it names a class that
DoctrineBundle instantiates with no argument, while an `EncryptedType` takes an
encrypter, which is a service. `EncryptedTypes` is what an application declares
instead, one service per encrypter:

```yaml
services:
    app.encrypted_types:
        class: Symfony\Component\KeyManagement\Bridge\DoctrineDbal\EncryptedTypes
        public: true
        arguments:
            $envelopes: '@key_management.stored_envelope_encrypter'
            $types:
                app_user_email: { type: string, key: 'user.email' }
                app_user_notes: { type: text, key: 'user.notes' }
```

and calls once the container is built, which means booting:

```php
// src/Kernel.php
public function boot(): void
{
    parent::boot();

    $this->container->get('app.encrypted_types')->register();
}
```

Booting is early enough for every entry point at once, the front controller,
the console with its schema tool and its migrations, and the test kernel, and
it costs nothing: a DBAL connection only reaches the database on its first
query. Registering later is what does not work. A connection cannot do it
either, however tempting `doctrine.dbal.connection_factory` looks: a store-backed
encrypter needs a connection, so asking a connection for the types closes a
circle the container refuses to compile.

Which encrypter a type is given is what decides the regime of the column, so an
entity holding both is two services, one per encrypter, each registering its own
names. Swapping one for another environment is then one argument.

Calling `register()` twice is safe and is what a rebooted kernel does: the type
registry is global and outlives the container, so a name already taken is
replaced rather than refused.

Keeping the data keys in a table
--------------------------------

`DataKeyStore` persists wrapped data keys in a DBAL table, so a payload refers
to one instead of carrying it. The KMS is then contacted once per data key and
per process rather than once per encrypted value, and the master key protecting
those keys can be rotated, or swapped for another provider, by rewrapping the
rows instead of rewriting the payloads:

```php
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\DataKeyStore;
use Symfony\Component\KeyManagement\StoredEnvelopeEncrypter;

$store = new DataKeyStore($connection, $clients, 'aws', 'alias/app-key');
$store->createTable();

$encrypter = new StoredEnvelopeEncrypter($store);
$envelope = $encrypter->encrypt('user.email', 'jane@example.com');
```

`EncryptedType` takes that encrypter like any other, and its third argument then
names a scope instead of a master key, since that is what the encrypter reads it
as:

```php
Type::getTypeRegistry()->register('app_user_email', new EncryptedType(
    Type::getTypeRegistry()->get('string'),
    $encrypter,
    'user.email',
));
```

Rows of that column then share one data key and carry a 16-byte reference to
it. To migrate a column that already holds self-contained envelopes, give the
store-backed encrypter a fallback and keep the same registered name: rows
written before are still read through the KMS, and rows written afterwards refer
to the stored key.

```php
$encrypter = new StoredEnvelopeEncrypter($store, new EnvelopeEncrypter($kms));
```

The table holds exactly what the store needs and nothing else:

| Column | Type | Role |
| --- | --- | --- |
| `id` | `BINARY(16)` | A UUIDv7, primary key, and the reference recorded by every payload |
| `scope` | `VARCHAR(191)` | The unit a data key is shared over: a column, a tenant, a purpose |
| `key_material` | `BLOB` | The wrapped data key |
| `master_key_id` | `VARCHAR(255)` | The master key that wrapped it |
| `client` | `VARCHAR(64)` | The configured KMS client able to unwrap it |

There is no timestamp column on purpose: a UUIDv7 carries its creation instant
and sorts chronologically, so the newest row of a scope is its current key and
the retirement age is read back from the reference itself.

A key is retired thirty days after it was minted, `DEFAULT_MAX_AGE_SECONDS`, and
`max_age` moves that. Rotating is not hygiene here, it is what the format
requires: every payload is sealed under a random 96-bit IV, which NIST SP 800-38D
bounds at 2^32 payloads per key, and thirty days covers some 1650 payloads per
second in one scope. Passing `null` turns rotation off and hands that bound to
the application. Retiring a key never deletes its row, so what it sealed stays
readable.

The table name is configurable. `configureSchema()` declares the table alongside
the ones Doctrine generates, the way the Lock and Messenger tables are picked up:
`symfony/doctrine-orm-key-management` ships the listener that calls it, and the
FrameworkBundle registers it beside the store. Outside that wiring, create the
table with `createTable()` or with your own migration.

In a Symfony application, `framework.key_management.store` wires all of this: the
store is what `DataKeyStoreInterface` resolves to, and the store-backed encrypter
becomes what `EnvelopeEncrypterInterface` injects, with the default client's
encrypter behind it so the payloads written before the store keep being read. The
per-client encrypters stay available as `$<name>EnvelopeEncrypter`.

A scope is resolved once and then held, unwrapped, for as long as the store
lives, which is what spares the round trips. That also means the store holds
plaintext key material in memory: `forget()` drops it, and the Symfony wiring
calls it between two units of work through the `kernel.reset` tag, so a
long-running worker starts each request with nothing retained.

Adding your own columns
-----------------------

Every query names those five columns explicitly, so extra columns are invisible
to the store, provided they are nullable or have a default: nothing fills them
on insert. How you declare them depends on who owns the table.

**You own the table.** Skip `createTable()`, leave `configureSchema()` unwired,
and declare the five columns plus yours in your own migration. Simplest route,
and the one to prefer as soon as the table carries anything the store does not
know about.

**Doctrine owns the schema.** If the schema listener calls `configureSchema()`,
the assembled schema declares five columns while the database holds more, so
`doctrine:schema:update` will offer to drop the extra ones. Add them to the same
table from your own `postGenerateSchema` listener, so both sides agree.

**Filling them when a key is created.** `rotate()` returns the handle whose
`reference` is the row's primary key, so a decorator writes the rest:

```php
final class TenantScopedStore implements RewrappableDataKeyStoreInterface
{
    public function __construct(
        private DataKeyStore $inner,
        private Connection $connection,
        private string $tenantId,
    ) {
    }

    public function rotate(string $scope): DataKeyHandle
    {
        $handle = $this->inner->rotate($scope);

        $this->connection->update(
            'key_management_data_keys',
            ['tenant_id' => $this->tenantId],
            ['id' => $handle->reference],
            ['id' => ParameterType::BINARY],
        );

        return $handle;
    }

    // current(), get(), all() and rewrap() delegate to $this->inner
}
```

Moving a column to another KMS
------------------------------

Nothing about the KMS reaches the entity, the mapping or the registered type
name: `EncryptedType` knows an envelope encrypter and a `$key`, and that is all.
Changing provider is a change of the encrypter handed to the type, and what it
costs depends on what the column already holds. Both clients are configured at
once meanwhile, which in a Symfony application makes their encrypters available
as `$awsEnvelopeEncrypter` and `$azureEnvelopeEncrypter`.

**Rows referring to a stored data key.** Nothing to do on the Doctrine side.
Declare the new client next to the old one, move the data keys over with
`key-management:rewrap-data-keys --from=aws --to=azure --key-id=...`, then point
the store at the new client so the keys it creates afterwards are wrapped there.
No payload is read or rewritten, the references stay what they were, and the old
client goes away once a `--from=aws --dry-run` run lists nothing.

**Rows carrying their own wrapped data key.** Each one is wrapped by the master
key of the provider that wrote it, so moving to another provider means rewriting
every row, and a row not yet rewritten still needs the old client to be read. A
decrypter routing on the key id keeps both readable while the column is being
rewritten, and writes everything through the new provider:

```php
final class MigratingEnvelopes implements EnvelopeEncrypterInterface, EnvelopeDecrypterInterface
{
    public function __construct(
        private EnvelopeEncrypterInterface&EnvelopeDecrypterInterface $target,
        private EnvelopeDecrypterInterface $legacy,
    ) {
    }

    public function encrypt(string $key, #[\SensitiveParameter] string $plaintext, string $aad = ''): Envelope
    {
        return $this->target->encrypt($key, $plaintext, $aad);
    }

    public function decrypt(Envelope $envelope, string $aad = ''): string
    {
        return str_starts_with($envelope->keyId ?? '', 'arn:aws:kms:')
            ? $this->legacy->decrypt($envelope, $aad)
            : $this->target->decrypt($envelope, $aad);
    }
}
```

Register the type with that encrypter, deploy, then rewrite the column. Do that
through DBAL rather than through the ORM: the decrypted value is the same before
and after, so the unit of work computes an empty change set and no `UPDATE` is
ever issued.

```php
$platform = $connection->getDatabasePlatform();
$legacy = new EncryptedType($parent, new EnvelopeEncrypter($awsKms), 'alias/app-key');
$target = new EncryptedType($parent, $encrypter, 'user.email');

foreach ($connection->iterateAssociative('SELECT id, email FROM user ORDER BY id') as $row) {
    $connection->update(
        'user',
        ['email' => $target->convertToDatabaseValue($legacy->convertToPHPValue($row['email'], $platform), $platform)],
        ['id' => $row['id']],
        ['email' => ParameterType::BINARY],
    );
}
```

Register the type with the target encrypter alone once every row is rewritten,
and drop the old client. That pass is the one occasion to change how the column
is protected without paying for it twice, so it is worth writing through a
store-backed encrypter, as `$target` does above: the cost is the same today, and
the next provider change becomes the case above, one command and not a single
row read.

Rotating the master key within one provider is another matter entirely. The key
id travels inside the envelope and is handed back to the KMS on every read, so
rows written under a retired key keep resolving and nothing has to be rewritten,
for as long as that key stays usable.

Requirements
------------

  * PHP >= 8.4.1
  * Doctrine DBAL >= 4.3, which lifted the `final` constructor on `Type` that
    the encrypted type needs
  * An `EnvelopeEncrypterInterface` configured by the application (typically
    via `framework.key_management`)

Trade-offs
----------

Without a data key store, each row is a self-contained envelope:

  * **One master KMS round-trip per encrypt and per decrypt.** No data key is
    cached, so the unwrapped one never lives longer than a single column write
    or read.
  * **Each row carries its own wrapped data key**, a couple hundred bytes of
    overhead per encrypted column, and no shared state between rows.
  * **The master key cannot be rotated** without decrypting and rewriting every
    row that refers to it.

With a store, the first two invert and the third disappears: the KMS is reached
once per data key and per process, a row carries a 16-byte reference, and moving
to another master key or another provider rewraps the key rows only. What you
give up is self-sufficiency, since reading a row now needs the store as well as
the KMS, and isolation, since a whole scope shares one data key.

Either way:

  * **No exact-match indexing.** Two rows with the same plaintext encrypt to
    different ciphertexts, because the nonce is drawn afresh every time. Store a
    blind index (an HMAC of the plaintext keyed by an application secret) on a
    sibling column when a column must be looked up.
  * **The column is unbounded.** The type declares `LONGBLOB`, `BYTEA`, ...
    whatever `length` the mapping carries: that length describes the plaintext,
    and the ciphertext outgrows it by the envelope's framing, so a column sized
    after it would truncate the ciphertext and destroy the authentication tag
    with it.
  * **A ciphertext is not bound to its row.** The type passes no AAD, because
    `convertToDatabaseValue()` never sees the table, the column or the row it
    converts for. Within one scope, or one master key, an encrypted value copied
    into another row of the same type decrypts there. When moving a value
    between rows must be detectable, bind the row yourself: put its identity in
    the payload and check it after decryption.

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/key-management.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
