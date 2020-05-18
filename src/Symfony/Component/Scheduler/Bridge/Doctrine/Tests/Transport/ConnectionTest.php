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
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\SchemaConfig;
use Doctrine\DBAL\Schema\Synchronizer\SchemaSynchronizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Bridge\Doctrine\Transport\Connection as DoctrineConnection;
use Symfony\Component\Scheduler\Task\NullFactory;
use Symfony\Component\Scheduler\Task\TaskFactory;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ConnectionTest extends TestCase
{
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

        $queryBuilder->expects(self::once())->method('getSQL')->willReturn('');
        $queryBuilder->method('getParameters')->willReturn(['task_name' => 'foo']);
        $queryBuilder->method('getParameterTypes')->willReturn([]);
        $driverConnection->expects(self::once())->method('executeQuery')->willReturn($statement);

        $connection = new DoctrineConnection($taskFactory, [], $driverConnection);
        $task = $connection->get('foo');

        static::assertInstanceOf(TaskInterface::class, $task);
        static::assertSame('foo', $task->getName());
        static::assertSame('* * * * *', $task->getExpression());
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

    private function getStatementMock($expectedResult): Statement
    {
        $stmt = $this->createMock(Statement::class);
        $stmt->expects(self::once())->method('fetch')->willReturn($expectedResult);

        return $stmt;
    }

    private function getSchemaSynchronizerMock(): SchemaSynchronizer
    {
        return $this->createMock(SchemaSynchronizer::class);
    }
}
