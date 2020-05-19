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
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\Exception\TransportException;
use Symfony\Component\Scheduler\ExecutionModeOrchestrator;
use Symfony\Component\Scheduler\Task\AbstractTask;
use Symfony\Component\Scheduler\Task\TaskFactoryInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskList;
use Symfony\Component\Scheduler\Task\TaskListInterface;
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
    private $taskFactory;
    private $orchestrator;

    public function __construct(TaskFactoryInterface $taskFactory, array $configuration, DBALConnection $driverConnection)
    {
        $this->taskFactory = $taskFactory;
        $this->configuration = array_replace_recursive(static::DEFAULT_OPTIONS, $configuration);
        $this->driverConnection = $driverConnection;
        $this->schemaSynchronizer = $schemaSynchronizer ?? new SingleDatabaseSynchronizer($this->driverConnection);
        $this->autoSetup = $this->configuration['auto_setup'];
        $this->orchestrator = new ExecutionModeOrchestrator($configuration['execution_mode'] ?? ExecutionModeOrchestrator::FIFO);
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        $tasks = [];
        $data = $this->executeQuery($this->createQueryBuilder()->getSQL())->fetchAll();

        if (0 === \count($data)) {
            return new TaskList();
        }

        foreach ($data as $task) {
            if (\array_key_exists('task_name', $task)) {
                $task['name'] = $task['task_name'];
                unset($task['task_name'], $task['id']);
            }

            $tasks[] = $this->taskFactory->create($task);
        }

        return new TaskList($this->orchestrator->sort($tasks));
    }

    public function get(string $taskName): TaskInterface
    {
        $queryBuilder = $this->createQueryBuilder()->where('t.task_name = ?');
        $data = $this->executeQuery($queryBuilder->getSQL(), [$taskName], [Types::STRING])->fetch();

        if (null === $data) {
            throw new LogicException('The desired task cannot be found.');
        }

        if (\array_key_exists('task_name', $data)) {
            $data['name'] = $data['task_name'];
            unset($data['task_name']);
        }

        return $this->taskFactory->create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        try {
            $this->driverConnection->beginTransaction();
            $affectedRows = $this->driverConnection->insert($this->configuration['table_name'], [
                'task_name' => $task->getName(),
                'expression' => $task->getExpression(),
                'state' => $task->get('state'),
                'options' => $task->getOptions(),
                'type' => $task->getType(),
            ], [
                'task_name' => Types::STRING,
                'expression' => Types::STRING,
                'state' => Types::STRING,
                'options' => Types::ARRAY,
                'type' => Types::STRING,
            ]);

            if (1 !== $affectedRows) {
                throw new DBALException('The given data are invalid.');
            }

            $this->driverConnection->commit();
        } catch (\Throwable | \Exception $exception) {
            $this->driverConnection->rollBack();
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $updatedTask): void
    {
        $this->prepareUpdate($taskName, [
            'task_name' => $updatedTask->getName(),
            'expression' => $updatedTask->get('expression'),
            'options' => $updatedTask->getOptions(),
            'state' => $updatedTask->get('state'),
            'type' => $updatedTask->get('type'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        $this->prepareUpdate($taskName, ['state' => AbstractTask::PAUSED], ['state' => Types::STRING]);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        $this->prepareUpdate($taskName, ['state' => AbstractTask::ENABLED], ['state' => Types::STRING]);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {
        try {
            $this->driverConnection->beginTransaction();
            $affectedRows = $this->driverConnection->delete($this->configuration['table_name'],
                ['task_name' => $taskName],
                ['task_name' => Types::STRING]
            );
            if (1 !== $affectedRows) {
                throw new DBALException('The given identifier is invalid.');
            }

            $this->driverConnection->commit();
        } catch (DBALException $exception) {
            $this->driverConnection->rollBack();
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function empty(): void
    {
        try {
            $this->driverConnection->beginTransaction();
            $this->driverConnection->exec(sprintf('DELETE * FROM %s', $this->configuration['table_name']));
            $this->driverConnection->commit();
        } catch (\Throwable | \Exception $exception) {
            $this->driverConnection->rollBack();
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

        $hasFilterCallback
            ? $this->driverConnection->getConfiguration()->setSchemaAssetsFilter($assetFilter)
            : $this->driverConnection->getConfiguration()->setFilterSchemaAssetsExpression($assetFilter)
        ;

        $this->autoSetup = false;
    }

    private function prepareUpdate(string $taskName, array $data, array $identifiers = []): void
    {
        try {
            $this->driverConnection->beginTransaction();
            $affectedRows = $this->driverConnection->update($this->configuration['table_name'], $data, ['task_name' => $taskName], $identifiers ?? [
                'task_name' => Types::STRING,
                'expression' => Types::STRING,
                'options' => Types::ARRAY,
                'state' => Types::STRING,
                'type' => Types::STRING,
            ]);
            if (1 !== $affectedRows) {
                throw new DBALException('The given identifier is invalid.');
            }

            $this->driverConnection->commit();
        } catch (\Throwable | \Exception $exception) {
            $this->driverConnection->rollBack();
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
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
        $table->addColumn('state', Types::STRING)
            ->setNotnull(true);
        $table->addColumn('type', Types::TEXT)
            ->setNotnull(true);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['task_name'], 'task_name');
        $table->addIndex(['expression'], 'task_expression');
        $table->addIndex(['state'], 'task_state');
        $table->addIndex(['type'], 'task_type');

        return $schema;
    }
}
