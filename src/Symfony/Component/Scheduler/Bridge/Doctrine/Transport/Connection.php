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
use Symfony\Component\Scheduler\Exception\AlreadyScheduledTaskException;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\Exception\TransportException;
use Symfony\Component\Scheduler\ExecutionModeOrchestrator;
use Symfony\Component\Scheduler\Task\AbstractTask;
use Symfony\Component\Scheduler\Task\TaskFactoryInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskList;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Transport\ConnectionInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Connection implements ConnectionInterface
{
    private const DEFAULT_OPTIONS = [
        'table_name' => 'sf_app_scheduler_tasks',
        'auto_setup' => true,
    ];

    private $autoSetup;
    private $configuration;
    private $driverConnection;
    private $schemaSynchronizer;
    private $taskFactory;
    private $orchestrator;
    private $serializer;

    public function __construct(TaskFactoryInterface $taskFactory, array $configuration, DBALConnection $driverConnection, SerializerInterface $serializer)
    {
        $this->taskFactory = $taskFactory;
        $this->configuration = array_replace_recursive(static::DEFAULT_OPTIONS, $configuration);
        $this->driverConnection = $driverConnection;
        $this->schemaSynchronizer = $schemaSynchronizer ?? new SingleDatabaseSynchronizer($this->driverConnection);
        $this->autoSetup = $this->configuration['auto_setup'];
        $this->serializer = $serializer;
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

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        $queryBuilder = $this->createQueryBuilder()->where('t.task_name = ?');
        $data = $this->executeQuery($queryBuilder->getSQL(), [$taskName], [Types::STRING])->fetch();

        if (null === $data) {
            throw new LogicException('The desired task cannot be found.');
        }

        return $this->serializer->deserialize($data, TaskInterface::class, 'json');
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        $list = $this->list();

        if (\array_key_exists($task->getName(), $list->toArray())) {
            throw new AlreadyScheduledTaskException(sprintf('The following task "%s" has already been scheduled!', $task->getName()));
        }

        try {
            $data = $this->serializer->serialize($task, 'json');

            $this->driverConnection->beginTransaction();
            $affectedRows = $this->driverConnection->insert($this->configuration['table_name'], [
                'task_name' => $task->getName(),
                'body' => $data,
            ], [
                'task_name' => Types::STRING,
                'body' => Types::TEXT,
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
            'body' => $this->serializer->serialize($updatedTask, 'json'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        $task = $this->get($taskName);

        $task->set('state', AbstractTask::PAUSED);
        $this->update($taskName, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        $task = $this->get($taskName);

        $task->set('state', AbstractTask::ENABLED);
        $this->update($taskName, $task);
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
                throw new InvalidArgumentException('The given identifier is invalid.');
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
                'body' => Types::TEXT,
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

    public function configureSchema(Schema $schema, DbalConnection $connection): void
    {
        if ($connection !== $this->driverConnection) {
            return;
        }

        if ($schema->hasTable($this->configuration['table_name'])) {
            return;
        }

        $this->addTableToSchema($schema);
    }

    private function getSchema(): Schema
    {
        $schema = new Schema([], [], $this->driverConnection->getSchemaManager()->createSchemaConfig());
        $this->addTableToSchema($schema);

        return $schema;
    }

    private function addTableToSchema(Schema $schema): void
    {
        $table = $schema->createTable($this->configuration['table_name']);
        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true)
        ;
        $table->addColumn('task_name', Types::STRING)
            ->setNotnull(true)
        ;
        $table->addColumn('body', Types::TEXT)
            ->setNotnull(true)
        ;

        $table->setPrimaryKey(['id']);
        $table->addIndex(['task_name'], 'task_name');
    }
}
