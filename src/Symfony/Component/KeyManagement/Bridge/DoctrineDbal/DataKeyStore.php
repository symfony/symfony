<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineDbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Psr\Container\ContainerInterface;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\DataKeyHandle;
use Symfony\Component\KeyManagement\Exception\DataKeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\RewrappableDataKeyStoreInterface;
use Symfony\Component\KeyManagement\StoredDataKey;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Keeps wrapped data keys in a Doctrine DBAL table.
 *
 * The table holds the five columns the contract actually needs and not one more, so an application
 * is free to add its own; the queries below name only these:
 *
 *   - `id`, a UUIDv7 in binary form, primary key and reference recorded by every payload;
 *   - `scope`, the unit a data key is shared over;
 *   - `key_material`, the wrapped data key;
 *   - `master_key_id`, the master key that wrapped it, needed to rebuild a {@see Ciphertext};
 *   - `client`, the name of the configured KMS client able to unwrap it.
 *
 * There is deliberately no timestamp: a UUIDv7 carries its creation instant and orders
 * chronologically, so `ORDER BY id DESC` selects the current key of a scope and the retirement age
 * is read back from the reference itself.
 *
 * `client` is what makes a provider migration possible. Both the old and the new client are
 * configured at once, each row says who can unwrap it, and
 * {@see RewrappableDataKeyStoreInterface::rewrap()} moves it over without touching a payload.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class DataKeyStore implements RewrappableDataKeyStoreInterface
{
    use BinaryColumn;

    public const string DEFAULT_TABLE = 'key_management_data_keys';

    /**
     * Thirty days.
     *
     * A stored data key is rotated by age because counting what it seals would cost a write per
     * payload, which is the round trip this store exists to spare. The age is therefore a proxy for
     * a bound that is really a count: {@see StoredEnvelopeEncrypter} seals every payload with a
     * random 96-bit IV under AES-256-GCM, and NIST SP 800-38D puts the ceiling for that at 2^32
     * payloads per key, past which two payloads share an IV with probability above 2^-32 and the
     * key stops protecting either of them.
     *
     * Thirty days keeps a scope writing up to some 1650 payloads per second under that ceiling.
     * An application writing faster than that, in a single scope, has to say so.
     */
    public const int DEFAULT_MAX_AGE_SECONDS = 2592000;

    /**
     * @var array<string, DataKeyHandle> keyed by reference
     */
    private array $handles = [];

    /**
     * @var array<string, string> reference of the data key to encrypt with, keyed by scope
     */
    private array $current = [];

    /**
     * @param ContainerInterface $clients       KMS clients, each a {@see DataKeyGeneratorInterface}, indexed by name
     * @param string             $client        name of the client wrapping the keys this store creates
     * @param string             $masterKeyId   master key wrapping the keys this store creates
     * @param positive-int       $keyBytes      length of the data keys to generate
     * @param int<0, max>|null   $maxAgeSeconds age past which {@see current()} rotates; `null` never rotates,
     *                                          which leaves the bound {@see DEFAULT_MAX_AGE_SECONDS} describes
     *                                          to whoever asked for it
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ContainerInterface $clients,
        private readonly string $client,
        private readonly string $masterKeyId,
        private readonly string $table = self::DEFAULT_TABLE,
        private readonly int $keyBytes = 32,
        private readonly ?int $maxAgeSeconds = self::DEFAULT_MAX_AGE_SECONDS,
    ) {
    }

    /**
     * The current key of a scope is its newest row, which `ORDER BY id DESC` gives for free since a
     * UUIDv7 sorts chronologically.
     *
     * A scope is resolved once and then remembered, because an encrypter asks for it on every
     * single payload while the answer only changes when the key is retired, which the reference
     * says on its own. Reading a column would otherwise cost one query per row written, which would
     * be a strange price for a store whose purpose is to spare round trips. The counterpart is that
     * a rotation performed elsewhere is picked up at the next retirement, at the next
     * {@see forget()}, or in the next process, rather than at the next payload.
     */
    public function current(string $scope): DataKeyHandle
    {
        if (null !== $handle = $this->remembered($scope)) {
            return $handle;
        }

        $row = $this->connection->createQueryBuilder()
            ->select('id', 'scope', 'key_material', 'master_key_id', 'client')
            ->from($this->table)
            ->where('scope = :scope')
            ->setParameter('scope', $scope)
            ->orderBy('id', 'DESC')
            ->setMaxResults(1)
            ->fetchAssociative();

        if (!$row) {
            return $this->rotate($scope);
        }

        $stored = self::hydrate($row);
        if ($this->isRetired($stored->reference)) {
            return $this->rotate($scope);
        }

        $this->current[$scope] = $stored->reference;

        return $this->handleFor($stored);
    }

    public function get(string $reference): DataKeyHandle
    {
        $row = $this->connection->fetchAssociative(
            \sprintf('SELECT id, scope, key_material, master_key_id, client FROM %s WHERE id = ?', $this->table),
            [$reference],
            [ParameterType::BINARY],
        );

        return $this->handleFor(self::hydrate($row ?: throw new DataKeyNotFoundException($reference)));
    }

    public function all(?string $client = null): iterable
    {
        $sql = \sprintf('SELECT id, scope, key_material, master_key_id, client FROM %s', $this->table);
        $parameters = [];

        if (null !== $client) {
            $sql .= ' WHERE client = ?';
            $parameters[] = $client;
        }

        foreach ($this->connection->iterateAssociative($sql.' ORDER BY id ASC', $parameters) as $row) {
            yield self::hydrate($row);
        }
    }

    /**
     * The cached handle survives on purpose: rewrapping changes how the data key is protected, not
     * the key itself, so anything already encrypted with it stays valid.
     */
    public function rewrap(string $reference, Ciphertext $wrapped, string $client): void
    {
        $updated = $this->connection->executeStatement(
            \sprintf('UPDATE %s SET key_material = ?, master_key_id = ?, client = ? WHERE id = ?', $this->table),
            [$wrapped->blob, $wrapped->keyId, $client, $reference],
            [ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING, ParameterType::BINARY],
        );

        if (0 === $updated) {
            throw new DataKeyNotFoundException($reference);
        }
    }

    /**
     * The plaintext is deliberately taken out of the {@see DataKey} and retained by the handle: a
     * store exists to unwrap once and encrypt many payloads. The handle takes a buffer of its own
     * as it does so, so the DataKey still wipes what it held.
     */
    public function rotate(string $scope): DataKeyHandle
    {
        $dataKey = $this->clientFor($this->client)->generateDataKey($this->masterKeyId, $this->keyBytes);
        $reference = Uuid::v7()->toBinary();

        $this->connection->insert($this->table, [
            'id' => $reference,
            'scope' => $scope,
            'key_material' => $dataKey->wrapped->blob,
            'master_key_id' => $dataKey->wrapped->keyId,
            'client' => $this->client,
        ], [
            'id' => ParameterType::BINARY,
            'key_material' => ParameterType::BINARY,
        ]);

        $this->current[$scope] = $reference;

        return $this->handles[$reference] = new DataKeyHandle($reference, $dataKey);
    }

    /**
     * Drops the retained plaintexts and everything remembered about them, so the next resolution
     * goes back to the database and to the KMS. Worth calling between two units of work in a
     * long-running process, which is what the `kernel.reset` tag does in a Symfony application:
     * the plaintexts are held for as long as the store is, and a rotation performed elsewhere is
     * only seen afterwards.
     */
    public function forget(): void
    {
        foreach ($this->handles as $handle) {
            $handle->release();
        }

        $this->handles = [];
        $this->current = [];
    }

    public function createTable(): void
    {
        $schema = $this->configureSchema(new Schema(), static fn (): bool => true);

        foreach ($schema->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->connection->executeStatement($sql);
        }
    }

    /**
     * Adds the table to the schema if it doesn't exist.
     *
     * @param-immediately-invoked-callable $isSameDatabase
     */
    public function configureSchema(Schema $schema, \Closure $isSameDatabase): Schema
    {
        if ($schema->hasTable($this->table)) {
            return $schema;
        }

        if (!$isSameDatabase($this->connection->executeStatement(...))) {
            return $schema;
        }

        if (method_exists($schema, 'edit')) {
            return $schema->edit()->addTable($this->buildSchemaTable())->create();
        }

        $this->configureSchemaTable($schema->createTable($this->table));

        return $schema;
    }

    private function buildSchemaTable(): Table
    {
        return Table::editor()
            ->setUnquotedName($this->table)
            ->addColumn(Column::editor()->setUnquotedName('id')->setTypeName('binary')->setLength(16)->setFixed(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('scope')->setTypeName('string')->setLength(191)->create())
            ->addColumn(Column::editor()->setUnquotedName('key_material')->setTypeName('blob')->create())
            ->addColumn(Column::editor()->setUnquotedName('master_key_id')->setTypeName('string')->setLength(255)->create())
            ->addColumn(Column::editor()->setUnquotedName('client')->setTypeName('string')->setLength(64)->create())
            ->addPrimaryKeyConstraint(new PrimaryKeyConstraint(null, [new UnqualifiedName(Identifier::unquoted('id'))], true))
            ->addIndex(Index::editor()
                ->setUnquotedName($this->table.'_scope_idx')
                ->setColumnNames(new UnqualifiedName(Identifier::unquoted('scope')), new UnqualifiedName(Identifier::unquoted('id'))))
            ->create();
    }

    /**
     * To be removed when doctrine/dbal minimum is bumped to ^4.5.
     */
    private function configureSchemaTable(Table $table): void
    {
        $table->addColumn('id', 'binary', ['length' => 16, 'fixed' => true]);
        $table->addColumn('scope', 'string', ['length' => 191]);
        $table->addColumn('key_material', 'blob');
        $table->addColumn('master_key_id', 'string', ['length' => 255]);
        $table->addColumn('client', 'string', ['length' => 64]);
        $table->addPrimaryKeyConstraint(new PrimaryKeyConstraint(null, [new UnqualifiedName(Identifier::unquoted('id'))], true));
        $table->addIndex(['scope', 'id'], $this->table.'_scope_idx');
    }

    /**
     * The remembered key of a scope, as long as its plaintext is still held and its age has not
     * caught up with it.
     */
    private function remembered(string $scope): ?DataKeyHandle
    {
        $reference = $this->current[$scope] ?? null;

        if (null === $reference || !isset($this->handles[$reference]) || $this->handles[$reference]->isReleased()) {
            return null;
        }

        return $this->isRetired($reference) ? null : $this->handles[$reference];
    }

    private function handleFor(StoredDataKey $row): DataKeyHandle
    {
        if (isset($this->handles[$row->reference]) && !$this->handles[$row->reference]->isReleased()) {
            return $this->handles[$row->reference];
        }

        $dataKey = $this->clientFor($row->client)->unwrapDataKey($row->wrapped);

        return $this->handles[$row->reference] = new DataKeyHandle($row->reference, $dataKey);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): StoredDataKey
    {
        return new StoredDataKey(
            self::bytes($row['id']),
            (string) $row['scope'],
            new Ciphertext(self::bytes($row['key_material']), (string) $row['master_key_id']),
            (string) $row['client'],
        );
    }

    private function clientFor(string $name): DataKeyGeneratorInterface
    {
        if (!$this->clients->has($name)) {
            throw new LogicException(\sprintf('No KMS client named "%s" is registered on the data key store.', $name));
        }

        $client = $this->clients->get($name);
        if (!$client instanceof DataKeyGeneratorInterface) {
            throw new LogicException(\sprintf('The KMS client "%s" cannot generate data keys, so it cannot back a data key store.', $name));
        }

        return $client;
    }

    /**
     * The creation instant is read back from the UUIDv7 reference, which is why the table needs no
     * timestamp of its own, and why the age of the remembered key can be checked without going back
     * to the database.
     */
    private function isRetired(string $reference): bool
    {
        if (null === $this->maxAgeSeconds) {
            return false;
        }

        $uid = Uuid::fromBinary($reference);

        if (!$uid instanceof TimeBasedUidInterface) {
            return false;
        }

        // A UUIDv7 minted while others share its millisecond carries an instant pushed forward to
        // keep the ordering, which can land ahead of the clock read here. Clamping the age at zero
        // keeps such a key from counting as not yet born, so a max age of zero still rotates.
        return max(0, time() - $uid->getDateTime()->getTimestamp()) >= $this->maxAgeSeconds;
    }
}
