<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Doctrine\Transport;

use Doctrine\DBAL\Abstraction\Result as AbstractionResult;
use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\Synchronizer\SchemaSynchronizer;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 *
 * @author Vincent Touzet <vincent.touzet@gmail.com>
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Connection implements ResetInterface
{
    protected const TABLE_OPTION_NAME = '_symfony_messenger_table_name';

    protected const DEFAULT_OPTIONS = [
        'table_name' => 'messenger_messages',
        'queue_name' => 'default',
        'redeliver_timeout' => 3600,
        'auto_setup' => true,
    ];

    /**
     * Configuration of the connection.
     *
     * Available options:
     *
     * * table_name: name of the table
     * * connection: name of the Doctrine's entity manager
     * * queue_name: name of the queue
     * * redeliver_timeout: Timeout before redeliver messages still in handling state (i.e: delivered_at is not null and message is still in table). Default: 3600
     * * auto_setup: Whether the table should be created automatically during send / get. Default: true
     */
    protected array $configuration;
    protected DBALConnection $driverConnection;
    protected ?float $queueEmptiedAt = null;

    private ?SchemaSynchronizer $schemaSynchronizer;
    private bool $autoSetup;

    public function __construct(array $configuration, DBALConnection $driverConnection, ?SchemaSynchronizer $schemaSynchronizer = null)
    {
        $this->configuration = array_replace_recursive(static::DEFAULT_OPTIONS, $configuration);
        $this->driverConnection = $driverConnection;
        $this->schemaSynchronizer = $schemaSynchronizer;
        $this->autoSetup = $this->configuration['auto_setup'];
    }

    public function reset(): void
    {
        $this->queueEmptiedAt = null;
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public static function buildConfiguration(#[\SensitiveParameter] string $dsn, array $options = []): array
    {
        if (false === $params = parse_url($dsn)) {
            throw new InvalidArgumentException('The given Doctrine Messenger DSN is invalid.');
        }

        $query = [];
        if (isset($params['query'])) {
            parse_str($params['query'], $query);
        }

        $configuration = ['connection' => $params['host']];
        $configuration += $query + $options + static::DEFAULT_OPTIONS;

        $configuration['auto_setup'] = filter_var($configuration['auto_setup'], \FILTER_VALIDATE_BOOL);

        // check for extra keys in options
        $optionsExtraKeys = array_diff(array_keys($options), array_keys(static::DEFAULT_OPTIONS));
        if (0 < \count($optionsExtraKeys)) {
            throw new InvalidArgumentException(sprintf('Unknown option found: [%s]. Allowed options are [%s].', implode(', ', $optionsExtraKeys), implode(', ', array_keys(static::DEFAULT_OPTIONS))));
        }

        // check for extra keys in options
        $queryExtraKeys = array_diff(array_keys($query), array_keys(static::DEFAULT_OPTIONS));
        if (0 < \count($queryExtraKeys)) {
            throw new InvalidArgumentException(sprintf('Unknown option found in DSN: [%s]. Allowed options are [%s].', implode(', ', $queryExtraKeys), implode(', ', array_keys(static::DEFAULT_OPTIONS))));
        }

        return $configuration;
    }

    /**
     * @param int $delay The delay in milliseconds
     *
     * @return string The inserted id
     *
     * @throws DBALException
     */
    public function send(string $body, array $headers, int $delay = 0): string
    {
        $now = new \DateTimeImmutable('UTC');
        $availableAt = $now->modify(sprintf('%+d seconds', $delay / 1000));

        $queryBuilder = $this->driverConnection->createQueryBuilder()
            ->insert($this->configuration['table_name'])
            ->values([
                'body' => '?',
                'headers' => '?',
                'queue_name' => '?',
                'created_at' => '?',
                'available_at' => '?',
            ]);

        return $this->executeInsert($queryBuilder->getSQL(), [
            $body,
            json_encode($headers),
            $this->configuration['queue_name'],
            $now,
            $availableAt,
        ], [
            Types::STRING,
            Types::STRING,
            Types::STRING,
            Types::DATETIME_IMMUTABLE,
            Types::DATETIME_IMMUTABLE,
        ]);
    }

    public function get(): ?array
    {
        if ($this->driverConnection->getDatabasePlatform() instanceof MySQLPlatform) {
            try {
                $this->driverConnection->delete($this->configuration['table_name'], ['delivered_at' => '9999-12-31 23:59:59']);
            } catch (DriverException $e) {
                // Ignore the exception
            } catch (TableNotFoundException $e) {
                if ($this->autoSetup) {
                    $this->setup();
                }
            }
        }

        get:
        $this->driverConnection->beginTransaction();
        try {
            $query = $this->createAvailableMessagesQueryBuilder()
                ->orderBy('available_at', 'ASC')
                ->setMaxResults(1);

            if ($this->driverConnection->getDatabasePlatform() instanceof OraclePlatform) {
                $query->select('m.id');
            }

            // Append pessimistic write lock to FROM clause if db platform supports it
            $sql = $query->getSQL();

            // Wrap the rownum query in a sub-query to allow writelocks without ORA-02014 error
            if ($this->driverConnection->getDatabasePlatform() instanceof OraclePlatform) {
                $query = $this->createQueryBuilder('w')
                    ->where('w.id IN ('.str_replace('SELECT a.* FROM', 'SELECT a.id FROM', $sql).')')
                    ->setParameters($query->getParameters(), $query->getParameterTypes());

                if (method_exists(QueryBuilder::class, 'forUpdate')) {
                    $query->forUpdate();
                }

                $sql = $query->getSQL();
            } elseif (method_exists(QueryBuilder::class, 'forUpdate')) {
                $query->forUpdate();
                try {
                    $sql = $query->getSQL();
                } catch (DBALException $e) {
                }
            } elseif (preg_match('/FROM (.+) WHERE/', (string) $sql, $matches)) {
                $fromClause = $matches[1];
                $sql = str_replace(
                    sprintf('FROM %s WHERE', $fromClause),
                    sprintf('FROM %s WHERE', $this->driverConnection->getDatabasePlatform()->appendLockHint($fromClause, LockMode::PESSIMISTIC_WRITE)),
                    $sql
                );
            }

            // use SELECT ... FOR UPDATE to lock table
            if (!method_exists(QueryBuilder::class, 'forUpdate')) {
                $sql .= ' '.$this->driverConnection->getDatabasePlatform()->getWriteLockSQL();
            }

            $stmt = $this->executeQuery(
                $sql,
                $query->getParameters(),
                $query->getParameterTypes()
            );
            $doctrineEnvelope = $stmt instanceof Result ? $stmt->fetchAssociative() : $stmt->fetch();

            if (false === $doctrineEnvelope) {
                $this->driverConnection->commit();
                $this->queueEmptiedAt = microtime(true) * 1000;

                return null;
            }
            // Postgres can "group" notifications having the same channel and payload
            // We need to be sure to empty the queue before blocking again
            $this->queueEmptiedAt = null;

            $doctrineEnvelope = $this->decodeEnvelopeHeaders($doctrineEnvelope);

            $queryBuilder = $this->driverConnection->createQueryBuilder()
                ->update($this->configuration['table_name'])
                ->set('delivered_at', '?')
                ->where('id = ?');
            $now = new \DateTimeImmutable('UTC');
            $this->executeStatement($queryBuilder->getSQL(), [
                $now,
                $doctrineEnvelope['id'],
            ], [
                Types::DATETIME_IMMUTABLE,
            ]);

            $this->driverConnection->commit();

            return $doctrineEnvelope;
        } catch (\Throwable $e) {
            $this->driverConnection->rollBack();

            if ($this->autoSetup && $e instanceof TableNotFoundException) {
                $this->setup();
                goto get;
            }

            throw $e;
        }
    }

    public function ack(string $id): bool
    {
        try {
            if ($this->driverConnection->getDatabasePlatform() instanceof MySQLPlatform) {
                return $this->driverConnection->update($this->configuration['table_name'], ['delivered_at' => '9999-12-31 23:59:59'], ['id' => $id]) > 0;
            }

            return $this->driverConnection->delete($this->configuration['table_name'], ['id' => $id]) > 0;
        } catch (DBALException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    public function reject(string $id): bool
    {
        try {
            if ($this->driverConnection->getDatabasePlatform() instanceof MySQLPlatform) {
                return $this->driverConnection->update($this->configuration['table_name'], ['delivered_at' => '9999-12-31 23:59:59'], ['id' => $id]) > 0;
            }

            return $this->driverConnection->delete($this->configuration['table_name'], ['id' => $id]) > 0;
        } catch (DBALException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    public function setup(): void
    {
        $configuration = $this->driverConnection->getConfiguration();
        $assetFilter = $configuration->getSchemaAssetsFilter();
        $configuration->setSchemaAssetsFilter(static fn () => true);
        $this->updateSchema();
        $configuration->setSchemaAssetsFilter($assetFilter);
        $this->autoSetup = false;
    }

    public function getMessageCount(): int
    {
        $queryBuilder = $this->createAvailableMessagesQueryBuilder()
            ->select('COUNT(m.id) AS message_count')
            ->setMaxResults(1);

        $stmt = $this->executeQuery($queryBuilder->getSQL(), $queryBuilder->getParameters(), $queryBuilder->getParameterTypes());

        return $stmt instanceof Result ? $stmt->fetchOne() : $stmt->fetchColumn();
    }

    public function findAll(?int $limit = null): array
    {
        $queryBuilder = $this->createAvailableMessagesQueryBuilder();

        if (null !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        $stmt = $this->executeQuery($queryBuilder->getSQL(), $queryBuilder->getParameters(), $queryBuilder->getParameterTypes());
        $data = $stmt instanceof Result ? $stmt->fetchAllAssociative() : $stmt->fetchAll();

        return array_map(fn ($doctrineEnvelope) => $this->decodeEnvelopeHeaders($doctrineEnvelope), $data);
    }

    public function find(mixed $id): ?array
    {
        $queryBuilder = $this->createQueryBuilder()
            ->where('m.id = ? and m.queue_name = ?');

        $stmt = $this->executeQuery($queryBuilder->getSQL(), [$id, $this->configuration['queue_name']]);
        $data = $stmt instanceof Result ? $stmt->fetchAssociative() : $stmt->fetch();

        return false === $data ? null : $this->decodeEnvelopeHeaders($data);
    }

    /**
     * @internal
     */
    public function configureSchema(Schema $schema, DBALConnection $forConnection, \Closure $isSameDatabase): void
    {
        if ($schema->hasTable($this->configuration['table_name'])) {
            return;
        }

        if ($forConnection !== $this->driverConnection && !$isSameDatabase($this->executeStatement(...))) {
            return;
        }

        $this->addTableToSchema($schema);
    }

    /**
     * @internal
     */
    public function getExtraSetupSqlForTable(Table $createdTable): array
    {
        return [];
    }

    private function createAvailableMessagesQueryBuilder(): QueryBuilder
    {
        $now = new \DateTimeImmutable('UTC');
        $redeliverLimit = $now->modify(sprintf('-%d seconds', $this->configuration['redeliver_timeout']));

        return $this->createQueryBuilder()
            ->where('m.queue_name = ?')
            ->andWhere('m.delivered_at is null OR m.delivered_at < ?')
            ->andWhere('m.available_at <= ?')
            ->setParameters([
                $this->configuration['queue_name'],
                $redeliverLimit,
                $now,
            ], [
                Types::STRING,
                Types::DATETIME_IMMUTABLE,
                Types::DATETIME_IMMUTABLE,
            ]);
    }

    private function createQueryBuilder(string $alias = 'm'): QueryBuilder
    {
        $queryBuilder = $this->driverConnection->createQueryBuilder()
            ->from($this->configuration['table_name'], $alias);

        $alias .= '.';

        if (!$this->driverConnection->getDatabasePlatform() instanceof OraclePlatform) {
            return $queryBuilder->select($alias.'*');
        }

        // Oracle databases use UPPER CASE on tables and column identifiers.
        // Column alias is added to force the result to be lowercase even when the actual field is all caps.

        return $queryBuilder->select(str_replace(', ', ', '.$alias,
            $alias.'id AS "id", body AS "body", headers AS "headers", queue_name AS "queue_name", '.
            'created_at AS "created_at", available_at AS "available_at", '.
            'delivered_at AS "delivered_at"'
        ));
    }

    private function executeQuery(string $sql, array $parameters = [], array $types = []): Result|AbstractionResult|ResultStatement
    {
        try {
            $stmt = $this->driverConnection->executeQuery($sql, $parameters, $types);
        } catch (TableNotFoundException $e) {
            if (!$this->autoSetup || $this->driverConnection->isTransactionActive()) {
                throw $e;
            }

            $this->setup();

            $stmt = $this->driverConnection->executeQuery($sql, $parameters, $types);
        }

        return $stmt;
    }

    protected function executeStatement(string $sql, array $parameters = [], array $types = []): int|string
    {
        try {
            $stmt = $this->driverConnection->executeStatement($sql, $parameters, $types);
        } catch (TableNotFoundException $e) {
            if (!$this->autoSetup || $this->driverConnection->isTransactionActive()) {
                throw $e;
            }

            $this->setup();

            $stmt = $this->driverConnection->executeStatement($sql, $parameters, $types);
        }

        return $stmt;
    }

    private function executeInsert(string $sql, array $parameters = [], array $types = []): string
    {
        // Use PostgreSQL RETURNING clause instead of lastInsertId() to get the
        // inserted id in one operation instead of two.
        if ($this->driverConnection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $sql .= ' RETURNING id';
        }

        insert:
        $this->driverConnection->beginTransaction();

        try {
            if ($this->driverConnection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $first = $this->driverConnection->fetchFirstColumn($sql, $parameters, $types);

                $id = $first[0] ?? null;

                if (!$id) {
                    throw new TransportException('no id was returned by PostgreSQL from RETURNING clause.');
                }
            } else {
                $this->driverConnection->executeStatement($sql, $parameters, $types);

                if (!$id = $this->driverConnection->lastInsertId()) {
                    throw new TransportException('lastInsertId() returned false, no id was returned.');
                }
            }

            $this->driverConnection->commit();
        } catch (\Throwable $e) {
            $this->driverConnection->rollBack();

            // handle setup after transaction is no longer open
            if ($this->autoSetup && $e instanceof TableNotFoundException) {
                $this->setup();
                goto insert;
            }

            throw $e;
        }

        return $id;
    }

    private function getSchema(): Schema
    {
        $schema = new Schema([], [], $this->createSchemaManager()->createSchemaConfig());
        $this->addTableToSchema($schema);

        return $schema;
    }

    private function addTableToSchema(Schema $schema): void
    {
        $table = $schema->createTable($this->configuration['table_name']);
        // add an internal option to mark that we created this & the non-namespaced table name
        $table->addOption(self::TABLE_OPTION_NAME, $this->configuration['table_name']);
        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true);
        $table->addColumn('body', Types::TEXT)
            ->setNotnull(true);
        $table->addColumn('headers', Types::TEXT)
            ->setNotnull(true);
        $table->addColumn('queue_name', Types::STRING)
            ->setLength(190) // MySQL 5.6 only supports 191 characters on an indexed column in utf8mb4 mode
            ->setNotnull(true);
        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(true);
        $table->addColumn('available_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(true);
        $table->addColumn('delivered_at', Types::DATETIME_IMMUTABLE)
            ->setNotnull(false);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['queue_name']);
        $table->addIndex(['available_at']);
        $table->addIndex(['delivered_at']);
    }

    private function decodeEnvelopeHeaders(array $doctrineEnvelope): array
    {
        $doctrineEnvelope['headers'] = json_decode($doctrineEnvelope['headers'], true);

        return $doctrineEnvelope;
    }

    private function updateSchema(): void
    {
        if (null !== $this->schemaSynchronizer) {
            $this->schemaSynchronizer->updateSchema($this->getSchema(), true);

            return;
        }

        $schemaManager = $this->createSchemaManager();
        $comparator = $this->createComparator($schemaManager);
        $schemaDiff = $this->compareSchemas($comparator, method_exists($schemaManager, 'introspectSchema') ? $schemaManager->introspectSchema() : $schemaManager->createSchema(), $this->getSchema());
        $platform = $this->driverConnection->getDatabasePlatform();

        if (!method_exists(SchemaDiff::class, 'getCreatedSchemas')) {
            foreach ($schemaDiff->toSaveSql($platform) as $sql) {
                $this->driverConnection->executeStatement($sql);
            }

            return;
        }

        if ($platform->supportsSchemas()) {
            foreach ($schemaDiff->getCreatedSchemas() as $schema) {
                $this->driverConnection->executeStatement($platform->getCreateSchemaSQL($schema));
            }
        }

        if ($platform->supportsSequences()) {
            foreach ($schemaDiff->getAlteredSequences() as $sequence) {
                $this->driverConnection->executeStatement($platform->getAlterSequenceSQL($sequence));
            }

            foreach ($schemaDiff->getCreatedSequences() as $sequence) {
                $this->driverConnection->executeStatement($platform->getCreateSequenceSQL($sequence));
            }
        }

        foreach ($platform->getCreateTablesSQL($schemaDiff->getCreatedTables()) as $sql) {
            $this->driverConnection->executeStatement($sql);
        }

        foreach ($schemaDiff->getAlteredTables() as $tableDiff) {
            foreach ($platform->getAlterTableSQL($tableDiff) as $sql) {
                $this->driverConnection->executeStatement($sql);
            }
        }
    }

    private function createSchemaManager(): AbstractSchemaManager
    {
        return method_exists($this->driverConnection, 'createSchemaManager')
            ? $this->driverConnection->createSchemaManager()
            : $this->driverConnection->getSchemaManager();
    }

    private function createComparator(AbstractSchemaManager $schemaManager): Comparator
    {
        return method_exists($schemaManager, 'createComparator')
            ? $schemaManager->createComparator()
            : new Comparator();
    }

    private function compareSchemas(Comparator $comparator, Schema $from, Schema $to): SchemaDiff
    {
        return method_exists($comparator, 'compareSchemas') || method_exists($comparator, 'doCompareSchemas')
            ? $comparator->compareSchemas($from, $to)
            : $comparator->compare($from, $to);
    }
}
