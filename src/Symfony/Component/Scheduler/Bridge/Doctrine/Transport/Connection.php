<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Doctrine\Transport;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Synchronizer\SingleDatabaseSynchronizer;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Scheduler\Exception\TransportException;
use Symfony\Component\Scheduler\Task\TaskFactoryInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Transport\ConnectionInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Connection implements ConnectionInterface
{
    private const DEFAULT_OPTIONS = [
        'table_name' => 'scheduler_tasks',
        'auto_setup' => true,
    ];

    private $autoSetup;
    private $configuration;
    private $driverConnection;
    private $schemaSynchronizer;
    private $taskFactories;

    /**
     * @param iterable|TaskFactoryInterface[] $taskFactories
     */
    public function __construct(iterable $taskFactories, array $configuration, DBALConnection $driverConnection)
    {
        $this->taskFactories = $taskFactories;
        $this->configuration = array_replace_recursive(static::DEFAULT_OPTIONS, $configuration);
        $this->driverConnection = $driverConnection;
        $this->schemaSynchronizer = $schemaSynchronizer ?? new SingleDatabaseSynchronizer($this->driverConnection);
        $this->autoSetup = $this->configuration['auto_setup'];
    }

    /**
     * {@inheritdoc}
     */
    public function list(): array
    {
    }

    public function get(string $taskName): ?array
    {
        $queryBuilder = $this->createQueryBuilder()->where('t.task_name = ?');
        $data = $this->executeQuery($queryBuilder->getSQL(), [
            $taskName,
        ])->fetch();

        return !$data ? null : $this->buildTask($data);
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        $queryBuilder = $this->driverConnection->createQueryBuilder()
            ->insert($this->configuration['table_name'])
            ->values([
                'task_name' => '?',
                'expression' => '?',
                'options' => '?',
            ])
        ;

        $this->executeQuery($queryBuilder->getSQL(), [
            $task->getName(),
            $task->get('expression'),
            $task->getOptions(),
        ], [
            Types::STRING,
            Types::STRING,
            Types::ARRAY,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function empty(): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {
        try {
            $this->driverConnection->delete($this->configuration['table_name'], ['name' => $taskName]);
        } catch (DBALException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    public function setup(): void
    {
        $configuration = $this->driverConnection->getConfiguration();
        // Since Doctrine 2.9 the getFilterSchemaAssetsExpression is deprecated
        $hasFilterCallback = method_exists($configuration, 'getSchemaAssetsFilter');

        if ($hasFilterCallback) {
            $assetFilter = $this->driverConnection->getConfiguration()->getSchemaAssetsFilter();
            $this->driverConnection->getConfiguration()->setSchemaAssetsFilter(null);
        } else {
            $assetFilter = $this->driverConnection->getConfiguration()->getFilterSchemaAssetsExpression();
            $this->driverConnection->getConfiguration()->setFilterSchemaAssetsExpression(null);
        }

        $this->schemaSynchronizer->updateSchema($this->getSchema(), true);

        if ($hasFilterCallback) {
            $this->driverConnection->getConfiguration()->setSchemaAssetsFilter($assetFilter);
        } else {
            $this->driverConnection->getConfiguration()->setFilterSchemaAssetsExpression($assetFilter);
        }

        $this->autoSetup = false;
    }

    private function createQueryBuilder(): QueryBuilder
    {
        return $this->driverConnection->createQueryBuilder()
            ->select('t.*')
            ->from($this->configuration['table_name'], 't')
        ;
    }

    private function executeQuery(string $sql, array $parameters = [], array $types = []): ResultStatement
    {
        try {
            $stmt = $this->driverConnection->executeQuery($sql, $parameters, $types);
        } catch (TableNotFoundException $e) {
            if ($this->driverConnection->isTransactionActive()) {
                throw $e;
            }

            if ($this->autoSetup) {
                $this->setup();
            }

            $stmt = $this->driverConnection->executeQuery($sql, $parameters, $types);
        }

        return $stmt;
    }

    private function getSchema(): Schema
    {
        $schema = new Schema([], [], $this->driverConnection->getSchemaManager()->createSchemaConfig());
        $table = $schema->createTable($this->configuration['table_name']);
        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true);
        $table->addColumn('task_name', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('expression', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('options', Types::ARRAY)
            ->setNotnull(true);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['task_name'], 'task_name');
        $table->addIndex(['expression'], 'task_expression');

        return $schema;
    }
}
