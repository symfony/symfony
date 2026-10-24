Symfony Doctrine DBAL Key Management Bridge
===========================================

Provides a Doctrine DBAL Type that decorates any other Type with column-level
encryption powered by `Symfony\Component\KeyManagement\EnvelopeEncrypterInterface`.

Each value is stored as a KeyManagement `Envelope`, either self-contained,
carrying its own wrapped data key, or referring to a data key kept in a table,
so the master KMS keeps full control of the key material either way.

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
    'string',
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

A type authenticates its configured key and parent type name with every value.
Both strings have a length prefix, so a decorator can append its own context without changing their boundaries.
Values written under a different key or parent type name are refused.
The encrypted declaration's name and the parent instance's configuration aren't authenticated.
Two columns using the same key and parent type name can read each other's values.
Give those columns different keys or scopes to keep them apart.

`EncryptedTypes` uses the configured `type` as the parent type name, `string` in the example below.
Changing the PHP implementation behind that name leaves existing values readable if the new converter can read the old plaintext.
Changing the parent type name requires rewriting existing values, even when both names resolve to the same PHP class.
Renaming the encrypted declaration doesn't require rewriting values.

For direct construction, pass the same stable parent name as the optional fourth argument, `$typeName`.
Use the configured parent alias when direct and configured types must read each other's values.
Only `null` falls back to the parent PHP class; empty strings and `'0'` are valid names.
The class fallback changes when the parent class is renamed or subclassed, so these changes require rewriting existing values.
Use a stable explicit name to avoid that migration.
Values written before context binding need to be read with the previous implementation, then rewritten with the current type.
The envelope binds this context locally, so the KMS needn't support authenticated data.

Where to register the types
---------------------------

One instance is registered per `(envelope encrypter, master key id, parent
type)` combination, and the registration must happen before the first query,
on every request.

In a Symfony application, `KeyManagementBundle` does it from its configuration:

```yaml
key_management:
    clients:
        default: '%env(KMS_DSN)%'
    store:
        key_id: 'alias/app-key'
    doctrine_dbal:
        types:
            app_user_email: { type: string, key: 'user.email' }
            app_user_notes: { type: text, key: 'user.notes' }
```

The types encrypt with the envelope encrypter that `EnvelopeEncrypterInterface` resolves to.
When `key_management.store` is configured, this is the store-backed encrypter, and `key` names a scope.
Otherwise, this is the encrypter of the default client, and `key` names a master key.
The bundle declares the types when DoctrineBundle creates a connection.
DoctrineBundle registers the types of its own configuration at that time, so `type` can name one of them.

With the Doctrine ORM, you don't need to declare these types.
Mark the property with the `#[Encrypted]` attribute of `symfony/doctrine-orm-key-management` instead, and the bundle registers the type of the column for you.

Every entry point creates a connection before its first query: the front controller, the console with its schema tool and its migrations, and the test kernel.
A request that doesn't use the database doesn't declare the types.

Do not defer the registration to Doctrine ORM's `loadClassMetadata` event: the
event does not fire when metadata comes from the cache, which DoctrineBundle
enables in production, so a cached field mapping would reference a type name
that is missing from the registry and every read of the column would fail with
`UnknownColumnType`.

Without the bundle, the application declares the types itself.
`doctrine.dbal.types` is not the place for them, and cannot be: it names a class that DoctrineBundle instantiates with no argument, while an `EncryptedType` takes an encrypter, which is a service.
`EncryptedTypes` is what the application declares instead, one service per encrypter:

```yaml
services:
    app.encrypted_types:
        class: Symfony\Component\KeyManagement\Bridge\DoctrineDbal\EncryptedTypes
        public: true
        arguments:
            $envelopes: '@app.envelope_encrypter'
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

When the kernel boots, DoctrineBundle has not registered the types of its configuration yet.
The types declared this way can only wrap the types that are registered before, such as the built-in ones.

Which encrypter a type is given is what decides the regime of the column.
The configured types all share one encrypter, so an entity holding both regimes declares the types of the other one with such a service.
For instance, its `$envelopes` is `@key_management.envelope_encrypter.<client>` for the self-contained envelopes of a client, while the configured types use the store.

Calling `register()` twice is safe and is what a rebooted kernel does: the type
registry is global and outlives the container, so a name already taken is
replaced rather than refused.
Pass the connection to `register($connection)` when the connection uses its own type registry.
That registry must be a writable `TypeRegistry`.

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
    'string',
));
```

Rows of that column use the same data key and contain a 16-byte reference to that key.
To read existing self-contained envelopes during migration, give the store-backed encrypter a fallback, `read_self_contained: true` in the bundle.
Keep the old configured key string as the new scope and retain the same parent type name.
The encrypted declaration's name can change.
Use the configured key from the earlier `alias/app-key` example.

```php
$encrypter = new StoredEnvelopeEncrypter($store, new EnvelopeEncrypter($kms));
$type = new EncryptedType(Type::getTypeRegistry()->get('string'), $encrypter, 'alias/app-key', 'string');
```

Rows written before are still read through the KMS, and new rows refer to the stored data key.
Changing the scope to `user.email` at the same time would prevent the fallback from reading existing values.

Turn the fallback off once the migration is over.
A self-contained payload contains its own wrapped data key and bypasses the store's scope and binding checks.
The envelope still authenticates the type's configured key and parent type name.

The table holds exactly what the store needs and nothing else:

| Column | Type | Role |
| --- | --- | --- |
| `id` | `BINARY(16)` | A UUIDv7, primary key, and the reference recorded by every payload |
| `scope` | `VARCHAR(191)` | The unit a data key is shared over: a column, a tenant, a purpose |
| `key_material` | `BLOB` | The wrapped data key |
| `master_key_id` | `VARCHAR(255)` | The master key that wrapped it |
| `client` | `VARCHAR(64)` | The configured KMS client able to unwrap it |
| `binding` | `BINARY(32)` | Proof that the key was minted for this reference and scope, checked once the key is unwrapped |

There is no timestamp column on purpose: a UUIDv7 carries its creation instant
and sorts chronologically, so the newest row of a scope is its current key and
the retirement age is read back from the reference itself.

A scope longer than its column is refused rather than truncated, and the column
follows the collation of the connection: under a case-insensitive one,
`user.email` and `User.Email` are one scope.

A key is retired thirty days after it was minted, `DEFAULT_MAX_AGE_SECONDS`, and
`max_age` moves that. Rotating is not hygiene here, it is what the format
requires: every payload is sealed under a random 96-bit IV, which NIST SP 800-38D
bounds at 2^32 payloads per key, and thirty days covers some 1650 payloads per
second in one scope. Passing `null` turns rotation off and hands that bound to
the application. Retiring a key never deletes its row, so what it sealed stays
readable.

`bindWrappingContext: true`, `bind_wrapping_context` in the bundle, has the backend
authenticate the reference and the scope of each data key as it wraps it, which is
what a Vault Transit key created with `derived=true` requires and what a backend
refusing authenticated data cannot do. Rows written before it was turned on are
moved over by `key-management:rewrap-data-keys --accept-unbound`.

Turning it on takes effect at once: until that command has moved them, the rows written before are refused, and so is encrypting under a scope whose current key is one of them.
The command can be run again after an interruption.
There is no way back: once the option is turned off again, the rows written or moved while it was on cannot be read, and the command cannot move them back.

The table name is configurable, but a name that needs quoting, a reserved word
such as `order` or a hyphenated name, is not supported, as for the Lock and
Messenger stores. `configureSchema()` declares the table alongside the ones
Doctrine generates, the way the Lock and Messenger tables are picked up:
`symfony/doctrine-orm-key-management` ships the listener that calls it, and the
`KeyManagementBundle` registers it beside the store. Outside that wiring, create the
table with `createTable()` or with your own migration.

In a Symfony application, `key_management.store` wires all of this: the
store is what `DataKeyStoreInterface` resolves to, and the store-backed encrypter
becomes what `EnvelopeEncrypterInterface` injects. With `read_self_contained: true`,
the default client's encrypter is put behind it so the payloads written before the
store keep being read. The per-client encrypters stay available through
`#[Target('<name>')]`.

A scope, or a reference read back from a payload, is resolved once and then held, unwrapped, for as long as the store lives, which is what spares the round trips.
A key created inside a transaction is the one exception: its row is looked up again on every use until the transaction is seen committed, so that a rollback taking the row away is noticed rather than encrypted with.
A row deleted, edited or copied elsewhere is likewise seen only after the next `forget()`.
Holding the keys also means the store holds plaintext key material in memory: `forget()` drops it, and the Symfony wiring calls it between two units of work through the `kernel.reset` tag, so a long-running worker starts each request with nothing retained.

Adding your own columns
-----------------------

Every query names those six columns explicitly, so extra columns are invisible
to the store, provided they are nullable or have a default: nothing fills them
on insert. How you declare them depends on who owns the table.

**You own the table.** Skip `createTable()`, leave `configureSchema()` unwired,
and declare the six columns plus yours in your own migration. Simplest route,
and the one to prefer as soon as the table carries anything the store does not
know about.

**Doctrine owns the schema.** If the schema listener calls `configureSchema()`,
the assembled schema declares six columns while the database holds more, so
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
through `#[Target('old')]` and `#[Target('new')]`.

**Rows referring to a stored data key.** Nothing to do on the Doctrine side.
Declare the new client next to the old one, move the data keys over with
`key-management:rewrap-data-keys --from=old --to=new --key-id=...`, then point
the store at the new client so the keys it creates afterwards are wrapped there.
No payload is read or rewritten, the references stay what they were, and the old
client goes away once a `--from=old --dry-run` run lists nothing.

A store wrapping with a `CompositeKms` records that client, so its rows are
wrapped by every member at once and read through whichever answers. When a
member is lost for good, rows with a wrapping from a surviving member stay
readable after the member list changes. New writes use the new list. Stored
keys need rewrapping under a replacement member to regain redundancy;
`key-management:rewrap-data-keys --from=main --to=main --key-id=...` writes
them under the current active members.

A member's name is stored in each wrapping. When replacing a member, list its
old name under the composite client's `retired` option to read old values
without asking it to wrap new ones. Rewrap stored keys before removing the old
name from `retired`; direct ciphertexts and self-contained envelopes are not
visited by the command and need separate migration. A retired member remains
a full decryption path and needs the same protection as an active member.

**Rows carrying their own wrapped data key.** Each one is wrapped by the master
key of the provider that wrote it, so moving to another provider means rewriting
every row, and a row not yet rewritten still needs the old client to be read.
When the configured key changes, read through the old type and write through a new type.
To keep serving queries, add another column mapped to the new type and backfill that column.
Both types can use an encrypter that routes to the appropriate provider by key id:

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

Keep the existing column mapped to the old type.
Register a separate type with the new key for a second column, `email_next` here.
Keep writes consistent across both columns during the backfill, or pause writes.
The following DBAL pass reads with the old type and writes with the new type.

```php
$platform = $connection->getDatabasePlatform();
$legacy = new EncryptedType($parent, new EnvelopeEncrypter($awsKms), 'alias/app-key', 'string');
$target = new EncryptedType($parent, $encrypter, 'user.email', 'string');

foreach ($connection->iterateAssociative('SELECT id, email FROM user ORDER BY id') as $row) {
    $connection->update(
        'user',
        ['email_next' => $target->convertToDatabaseValue($legacy->convertToPHPValue($row['email'], $platform), $platform)],
        ['id' => $row['id']],
        ['email_next' => ParameterType::BINARY],
    );
}
```

Once every row has a new value, switch readers to the new column and type.
Remove the old column and client when they are no longer used.
For an offline migration, stop reads and writes, update `email` in that loop, then change the mapping to the new type.
Writing through a store-backed encrypter also lets later provider changes rewrap the stored keys without rewriting the payloads.

Physical key rotation within a provider doesn't require rewriting values while the type's configured key string stays unchanged.
For example, the same logical alias can point to a new master key while old keys remain available for decryption.
Changing the configured alias, key name or ARN changes the authenticated context and requires migration, even if the KMS still reads the old key.

Requirements
------------

  * PHP >= 8.4.1
  * Doctrine DBAL >= 4.3, which lifted the `final` constructor on `Type` that
    the encrypted type needs
  * An `EnvelopeEncrypterInterface` configured by the application (typically
    via the `key_management` configuration of `KeyManagementBundle`)

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
  * **A ciphertext is not bound to its row.** The type authenticates its configured key and parent type name.
    `convertToDatabaseValue()` never sees the table, column or row it converts for.
    Rows using the same configured key and parent type name can read each other's encrypted values.
    To detect moving a value between rows, include the row identity in the payload.
    Check the row identity after decryption.

Resources
---------

 * [Documentation](https://symfony.com/doc/current/components/key-management.html)
 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/symfony/issues) and
   [send Pull Requests](https://github.com/symfony/symfony/pulls)
   in the [main Symfony repository](https://github.com/symfony/symfony)
