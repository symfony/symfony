<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Doctrine\Tests\Transport;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Schema\Synchronizer\SchemaSynchronizer;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Bridge\Doctrine\Transport\Connection as DoctrineConnection;
use Symfony\Component\Scheduler\Exception\LogicException;
use Symfony\Component\Scheduler\Exception\TransportException;
use Symfony\Component\Scheduler\Task\AbstractTask;
use Symfony\Component\Scheduler\Task\NullFactory;
use Symfony\Component\Scheduler\Task\TaskFactory;
use Symfony\Component\Scheduler\Task\TaskFactoryInterface;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ConnectionTest extends TestCase
{
    public function testConnectionCanSortATaskList(): void
    {
        $taskFactory = new TaskFactory([new NullFactory()]);
        $queryBuilder = $this->getQueryBuilderMock();

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $statement = $this->getStatementMock([
            [
                'id' => 1,
                'task_name' => 'foo',
                'expression' => '* * * * *',
                'options' => [
                    'priority' => 1,
                    'tracked' => true,
                ],
                'type' => 'null',
            ],
            [
                'id' => 2,
                'task_name' => 'bar',
                'expression' => '* * * * *',
                'options' => [
                    'priority' => 2,
                    'tracked' => false,
                ],
                'type' => 'null',
            ],
        ], true);

        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('SELECT * FROM scheduler_tasks');
        $queryBuilder->expects(self::never())->method('getParameterTypes');
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);

        $connection = new DoctrineConnection($taskFactory, [], $driverConnection);
        $taskList = $connection->list();

        static::assertNotEmpty($taskList);
        static::assertInstanceOf(TaskInterface::class, $taskList->get('bar'));

        $list = $taskList->toArray(false);
        static::assertSame('bar', $list[0]->getName());
        static::assertSame('foo', $list[1]->getName());
    }

    public function testConnectionCanReturnAnEmptyTaskList(): void
    {
        $taskFactory = new TaskFactory([new NullFactory()]);
        $queryBuilder = $this->getQueryBuilderMock();

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $statement = $this->getStatementMock([], true);

        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('SELECT * FROM scheduler_tasks');
        $queryBuilder->expects(self::never())->method('getParameterTypes');
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);

        $connection = new DoctrineConnection($taskFactory, [], $driverConnection);
        $taskList = $connection->list();

        static::assertEmpty($taskList);
    }

    public function testConnectionCanReturnATaskList(): void
    {
        $taskFactory = new TaskFactory([new NullFactory()]);
        $queryBuilder = $this->getQueryBuilderMock();

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $statement = $this->getStatementMock([
            [
                'id' => 1,
                'task_name' => 'foo',
                'expression' => '* * * * *',
                'options' => [],
                'type' => 'null',
            ],
            [
                'id' => 2,
                'task_name' => 'bar',
                'expression' => '* * * * *',
                'options' => [],
                'type' => 'null',
            ],
        ], true);

        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('SELECT * FROM scheduler_tasks');
        $queryBuilder->expects(self::never())->method('getParameterTypes');
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);

        $connection = new DoctrineConnection($taskFactory, [], $driverConnection);
        $taskList = $connection->list();

        static::assertNotEmpty($taskList);
        static::assertInstanceOf(TaskInterface::class, $taskList->get('foo'));
        static::assertSame('foo', $taskList->get('foo')->getName());
        static::assertSame('* * * * *', $taskList->get('foo')->getExpression());
    }

    public function testConnectionCannotReturnAnInvalidTask(): void
    {
        $taskFactory = new TaskFactory([new NullFactory()]);
        $queryBuilder = $this->getQueryBuilderMock();

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $statement = $this->getStatementMock(null);

        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('SELECT * FROM scheduler_tasks WHERE task_name = "foo"');
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);

        $connection = new DoctrineConnection($taskFactory, [], $driverConnection);

        static::expectException(LogicException::class);
        $connection->get('foo');
    }

    public function testConnectionCanReturnASingleTask(): void
    {
        $taskFactory = new TaskFactory([new NullFactory()]);
        $queryBuilder = $this->getQueryBuilderMock();

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $statement = $this->getStatementMock([
            'id' => 1,
            'task_name' => 'foo',
            'expression' => '* * * * *',
            'options' => [],
            'type' => 'null',
        ]);

        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('SELECT * FROM scheduler_tasks WHERE task_name = "foo"');
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);

        $connection = new DoctrineConnection($taskFactory, [], $driverConnection);
        $task = $connection->get('foo');

        static::assertInstanceOf(TaskInterface::class, $task);
        static::assertSame('foo', $task->getName());
        static::assertSame('* * * * *', $task->getExpression());
    }

    public function testConnectionCannotInsertASingleTaskWithInvalidIdentifier(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::once())->method('get')->with(self::equalTo('state'))->willReturn('paused');
        $task->expects(self::once())->method('getOptions')->willReturn([]);
        $task->expects(self::once())->method('getType')->willReturn('null');

        $taskFactory = $this->createMock(TaskFactoryInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('beginTransaction');
        $driverConnection->expects(self::once())->method('insert')->with('scheduler_tasks', [
            'task_name' => 'foo',
            'expression' => '* * * * *',
            'state' => 'paused',
            'options' => [],
            'type' => 'null',
        ], [
            'task_name' => Types::STRING,
            'expression' => Types::STRING,
            'state' => Types::STRING,
            'options' => Types::ARRAY,
            'type' => Types::STRING,
        ])->willReturn(2);
        $driverConnection->expects(self::once())->method('rollBack');

        $connection = new DoctrineConnection($taskFactory, [], $driverConnection);

        static::expectException(TransportException::class);
        $connection->create($task);
    }

    public function testConnectionCannotInsertASingleTaskWithValidIdentifier(): void
    {
        $task = $this->createMock(TaskInterface::class);
        $task->expects(self::once())->method('getName')->willReturn('foo');
        $task->expects(self::once())->method('getExpression')->willReturn('* * * * *');
        $task->expects(self::once())->method('get')->with(self::equalTo('state'))->willReturn('paused');
        $task->expects(self::once())->method('getOptions')->willReturn([]);
        $task->expects(self::once())->method('getType')->willReturn('null');

        $taskFactory = $this->createMock(TaskFactoryInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('beginTransaction');
        $driverConnection->expects(self::once())->method('insert')->with('scheduler_tasks', [
            'task_name' => 'foo',
            'expression' => '* * * * *',
            'state' => 'paused',
            'options' => [],
            'type' => 'null',
        ], [
            'task_name' => Types::STRING,
            'expression' => Types::STRING,
            'state' => Types::STRING,
            'options' => Types::ARRAY,
            'type' => Types::STRING,
        ])->willReturn(1);
        $driverConnection->expects(self::never())->method('rollBack');
        $driverConnection->expects(self::once())->method('commit');

        $connection = new DoctrineConnection($taskFactory, [], $driverConnection);
        $connection->create($task);
    }

    public function testConnectionCannotPauseASingleTaskWithInvalidIdentifier(): void
    {
        $taskFactory = $this->createMock(TaskFactoryInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('beginTransaction');
        $driverConnection->expects(self::once())->method('update')->with('scheduler_tasks', ['state' => AbstractTask::PAUSED], ['task_name' => 'foo'], ['state' => Types::STRING])->willReturn(2);
        $driverConnection->expects(self::once())->method('rollBack');

        $connection = new DoctrineConnection($taskFactory, [], $driverConnection);

        static::expectException(TransportException::class);
        $connection->pause('foo');
    }

    public function testConnectionCannotPauseASingleTaskWithValidIdentifier(): void
    {
        $taskFactory = $this->createMock(TaskFactoryInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('beginTransaction');
        $driverConnection->expects(self::once())->method('update')->with('scheduler_tasks', ['state' => AbstractTask::PAUSED], ['task_name' => 'foo'], ['state' => Types::STRING])->willReturn(1);
        $driverConnection->expects(self::never())->method('rollBack');
        $driverConnection->expects(self::once())->method('commit');

        $connection = new DoctrineConnection($taskFactory, [], $driverConnection);
        $connection->pause('foo');
    }

    public function testConnectionCannotResumeASingleTaskWithInvalidIdentifier(): void
    {
        $taskFactory = $this->createMock(TaskFactoryInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('beginTransaction');
        $driverConnection->expects(self::once())->method('update')->with('scheduler_tasks', ['state' => AbstractTask::ENABLED], ['task_name' => 'foo'], ['state' => Types::STRING])->willReturn(2);
        $driverConnection->expects(self::once())->method('rollBack');

        $connection = new DoctrineConnection($taskFactory, [], $driverConnection);

        static::expectException(TransportException::class);
        $connection->resume('foo');
    }

    public function testConnectionCannotResumeASingleTaskWithValidIdentifier(): void
    {
        $taskFactory = $this->createMock(TaskFactoryInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('beginTransaction');
        $driverConnection->expects(self::once())->method('update')->with('scheduler_tasks', ['state' => AbstractTask::ENABLED], ['task_name' => 'foo'], ['state' => Types::STRING])->willReturn(1);
        $driverConnection->expects(self::never())->method('rollBack');
        $driverConnection->expects(self::once())->method('commit');

        $connection = new DoctrineConnection($taskFactory, [], $driverConnection);
        $connection->resume('foo');
    }

    public function testConnectionCannotDeleteASingleTaskWithInvalidIdentifier(): void
    {
        $taskFactory = $this->createMock(TaskFactoryInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('beginTransaction');
        $driverConnection->expects(self::once())->method('delete')->with('scheduler_tasks', ['task_name' => 'foo'], ['task_name' => Types::STRING])->willReturn(2);
        $driverConnection->expects(self::once())->method('rollBack');

        $connection = new DoctrineConnection($taskFactory, [], $driverConnection);

        static::expectException(TransportException::class);
        $connection->delete('foo');
    }

    public function testConnectionCannotDeleteASingleTaskWithValidIdentifier(): void
    {
        $taskFactory = $this->createMock(TaskFactoryInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('beginTransaction');
        $driverConnection->expects(self::once())->method('delete')->with('scheduler_tasks', ['task_name' => 'foo'], ['task_name' => Types::STRING])->willReturn(1);
        $driverConnection->expects(self::never())->method('rollBack');
        $driverConnection->expects(self::once())->method('commit');

        $connection = new DoctrineConnection($taskFactory, [], $driverConnection);
        $connection->delete('foo');
    }

    public function testConnectionCannotEmptyWithInvalidIdentifier(): void
    {
        $taskFactory = $this->createMock(TaskFactoryInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('beginTransaction');
        $driverConnection->expects(self::once())->method('exec')->with('DELETE * FROM scheduler_tasks')->willThrowException(new DBALException());
        $driverConnection->expects(self::once())->method('rollBack');

        $connection = new DoctrineConnection($taskFactory, [], $driverConnection);

        static::expectException(TransportException::class);
        $connection->empty();
    }

    public function testConnectionCannotEmptyWithValidIdentifier(): void
    {
        $taskFactory = $this->createMock(TaskFactoryInterface::class);

        $driverConnection = $this->getDBALConnectionMock();
        $driverConnection->expects(self::once())->method('beginTransaction');
        $driverConnection->expects(self::once())->method('exec')->with('DELETE * FROM scheduler_tasks');
        $driverConnection->expects(self::never())->method('rollBack');
        $driverConnection->expects(self::once())->method('commit');

        $connection = new DoctrineConnection($taskFactory, [], $driverConnection);
        $connection->empty();
    }

    private function getDBALConnectionMock()
    {
        $driverConnection = $this->createMock(Connection::class);
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method('getWriteLockSQL')->willReturn('FOR UPDATE');
        $configuration = $this->createMock(Configuration::class);
        $driverConnection->method('getDatabasePlatform')->willReturn($platform);
        $driverConnection->method('getConfiguration')->willReturn($configuration);

        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaConfig = $this->createMock(SchemaConfig::class);
        $schemaConfig->method('getMaxIdentifierLength')->willReturn(63);
        $schemaConfig->method('getDefaultTableOptions')->willReturn([]);
        $schemaManager->method('createSchemaConfig')->willReturn($schemaConfig);
        $driverConnection->method('getSchemaManager')->willReturn($schemaManager);

        return $driverConnection;
    }

    private function getQueryBuilderMock()
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);

        $queryBuilder->method('select')->willReturn($queryBuilder);
        $queryBuilder->method('update')->willReturn($queryBuilder);
        $queryBuilder->method('from')->willReturn($queryBuilder);
        $queryBuilder->method('set')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturn($queryBuilder);
        $queryBuilder->method('andWhere')->willReturn($queryBuilder);
        $queryBuilder->method('orderBy')->willReturn($queryBuilder);
        $queryBuilder->method('setMaxResults')->willReturn($queryBuilder);
        $queryBuilder->method('setParameter')->willReturn($queryBuilder);
        $queryBuilder->method('setParameters')->willReturn($queryBuilder);

        return $queryBuilder;
    }

    private function getStatementMock($expectedResult, bool $list = false): Statement
    {
        $stmt = $this->createMock(Statement::class);
        $list ? $stmt->expects(self::once())->method('fetchAll')->willReturn($expectedResult) : $stmt->expects(self::once())->method('fetch')->willReturn($expectedResult);

        return $stmt;
    }

    private function getSchemaSynchronizerMock(): SchemaSynchronizer
    {
        return $this->createMock(SchemaSynchronizer::class);
    }
}
