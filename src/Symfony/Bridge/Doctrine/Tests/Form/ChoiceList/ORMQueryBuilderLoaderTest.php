<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Form\ChoiceList;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\GuidType;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\ChoiceList\ORMQueryBuilderLoader;
use Symfony\Bridge\Doctrine\Tests\DoctrineTestHelper;
use Symfony\Bridge\Doctrine\Tests\Fixtures\CustomUuidIdType;
use Symfony\Bridge\Doctrine\Tests\Fixtures\EmbeddedIdentifierEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\GuidIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\LegacyQueryMock;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleStringIdEntity;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Uid\Uuid;

class ORMQueryBuilderLoaderTest extends TestCase
{
    protected function tearDown(): void
    {
        DoctrineTestHelper::registerTypes(new Configuration(), ['uuid' => GuidType::class]);
    }

    public function testIdentifierTypeIsStringArray()
    {
        $this->checkIdentifierType(SingleStringIdEntity::class, ArrayParameterType::STRING);
    }

    public function testIdentifierTypeIsIntegerArray()
    {
        $this->checkIdentifierType(SingleIntIdEntity::class, ArrayParameterType::INTEGER);
    }

    protected function checkIdentifierType(string $classname, $expectedType)
    {
        $em = DoctrineTestHelper::createTestEntityManager();

        $query = $this->getQueryMock();

        $query
            ->method('getResult')
            ->willReturn([]);

        $query->expects($this->once())
            ->method('setParameter')
            ->with('ORMQueryBuilderLoader_getEntitiesByIds_id', [1, 2], $expectedType)
            ->willReturn($query);

        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->setConstructorArgs([$em])
            ->onlyMethods(['getQuery'])
            ->getMock();

        $qb->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $qb->select('e')
            ->from($classname, 'e');

        $loader = new ORMQueryBuilderLoader($qb);
        $loader->getEntitiesByIds('id', [1, 2]);
    }

    public function testFilterNonIntegerValues()
    {
        $em = DoctrineTestHelper::createTestEntityManager();

        $query = $this->getQueryMock();

        $query
            ->method('getResult')
            ->willReturn([]);

        $query->expects($this->once())
            ->method('setParameter')
            ->with('ORMQueryBuilderLoader_getEntitiesByIds_id', [1, 2, 3, '9223372036854775808'], ArrayParameterType::INTEGER)
            ->willReturn($query);

        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->setConstructorArgs([$em])
            ->onlyMethods(['getQuery'])
            ->getMock();

        $qb->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $qb->select('e')
            ->from('Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity', 'e');

        $loader = new ORMQueryBuilderLoader($qb);
        $loader->getEntitiesByIds('id', [1, '', 2, 3, 'foo', '9223372036854775808']);
    }

    #[DataProvider('provideGuidEntityClasses')]
    public function testFilterEmptyUuids(string $entityClass)
    {
        $em = DoctrineTestHelper::createTestEntityManager();
        $loader = $this->createLoader($em, $entityClass, ['71c5fd46-3f16-4abb-bad7-90ac1e654a2d', 'b98e8e11-2897-44df-ad24-d2627eb7f499']);

        $loader->getEntitiesByIds('id', ['71c5fd46-3f16-4abb-bad7-90ac1e654a2d', '', 'b98e8e11-2897-44df-ad24-d2627eb7f499']);
    }

    #[DataProvider('provideGuidEntityClasses')]
    public function testFilterInvalidGuidsOnNativeGuidPlatform(string $entityClass)
    {
        $config = DoctrineTestHelper::createTestConfiguration();
        DoctrineTestHelper::registerTypes($config, ['uuid' => GuidType::class]);

        $em = $this->createEntityManagerWithPlatform(new PostgreSQLPlatform(), $config);
        $loader = $this->createLoader($em, $entityClass, [
            '71c5fd46-3f16-4abb-bad7-90ac1e654a2d',
            'B98E8E11-2897-44DF-AD24-D2627EB7F499',
        ]);

        $loader->getEntitiesByIds('id', [
            '71c5fd46-3f16-4abb-bad7-90ac1e654a2d',
            'B98E8E11-2897-44DF-AD24-D2627EB7F499',
            'foo',
            1,
            '71c5fd463f164abbbad790ac1e654a2d',
            '{4d0a0dcb-2ba2-4b30-b4b5-2b3e26b45ae7}',
            '{4d0a0dcb-2ba2-4b30-b4b5-2b3e26b45ae7',
            "71c5fd46-3f16-4abb-bad7-90ac1e654a2d\n",
        ]);
    }

    public function testReturnEmptyWhenAllGuidsInvalidOnNativeGuidPlatform()
    {
        $em = $this->createEntityManagerWithPlatform(new PostgreSQLPlatform());
        $loader = $this->createLoader($em, GuidIdEntity::class);

        $this->assertSame([], $loader->getEntitiesByIds('id', ['foo', '123']));
    }

    #[DataProvider('provideGuidEntityClasses')]
    public function testDoesNotFilterNonGuidValuesOnSqlite(string $entityClass)
    {
        $config = DoctrineTestHelper::createTestConfiguration();
        DoctrineTestHelper::registerTypes($config, ['uuid' => GuidType::class]);

        $em = DoctrineTestHelper::createTestEntityManager($config);
        $loader = $this->createLoader($em, $entityClass, ['71c5fd46-3f16-4abb-bad7-90ac1e654a2d', 'foo', 1]);

        $loader->getEntitiesByIds('id', ['71c5fd46-3f16-4abb-bad7-90ac1e654a2d', '', 'foo', 1]);
    }

    #[DataProvider('provideUidEntityClasses')]
    public function testFilterUid(string $entityClass)
    {
        $config = DoctrineTestHelper::createTestConfiguration();
        DoctrineTestHelper::registerTypes($config, [
            'uuid' => UuidType::class,
            'ulid' => UlidType::class,
            CustomUuidIdType::class => CustomUuidIdType::class,
        ]);

        $em = DoctrineTestHelper::createTestEntityManager($config);

        $query = $this->getQueryMock();

        $query
            ->method('getResult')
            ->willReturn([]);

        $query->expects($this->once())
            ->method('setParameter')
            ->with('ORMQueryBuilderLoader_getEntitiesByIds_id', [Uuid::fromString('71c5fd46-3f16-4abb-bad7-90ac1e654a2d')->toBinary(), Uuid::fromString('b98e8e11-2897-44df-ad24-d2627eb7f499')->toBinary()], ArrayParameterType::STRING)
            ->willReturn($query);

        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->setConstructorArgs([$em])
            ->onlyMethods(['getQuery'])
            ->getMock();

        $qb->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $qb->select('e')
            ->from($entityClass, 'e');

        $loader = new ORMQueryBuilderLoader($qb);
        $loader->getEntitiesByIds('id', ['71c5fd46-3f16-4abb-bad7-90ac1e654a2d', '', 'b98e8e11-2897-44df-ad24-d2627eb7f499']);
    }

    #[DataProvider('provideUidEntityClasses')]
    public function testUidThrowProperException(string $entityClass)
    {
        $config = DoctrineTestHelper::createTestConfiguration();
        DoctrineTestHelper::registerTypes($config, [
            'uuid' => UuidType::class,
            'ulid' => UlidType::class,
            CustomUuidIdType::class => CustomUuidIdType::class,
        ]);

        $em = DoctrineTestHelper::createTestEntityManager($config);

        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->setConstructorArgs([$em])
            ->onlyMethods(['getQuery'])
            ->getMock();

        $qb->expects($this->never())
            ->method('getQuery');

        $qb->select('e')
            ->from($entityClass, 'e');

        $loader = new ORMQueryBuilderLoader($qb);

        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessageMatches('/^Failed to transform "hello" into "(uuid|ulid|Symfony\\\\Bridge\\\\Doctrine\\\\Tests\\\\Fixtures\\\\CustomUuidIdType)"\.$/');

        $loader->getEntitiesByIds('id', ['hello']);
    }

    public function testEmbeddedIdentifierName()
    {
        $em = DoctrineTestHelper::createTestEntityManager();

        $query = $this->getQueryMock();

        $query
            ->method('getResult')
            ->willReturn([]);

        $query->expects($this->once())
            ->method('setParameter')
            ->with('ORMQueryBuilderLoader_getEntitiesByIds_id_value', [1, 2, 3], ArrayParameterType::INTEGER)
            ->willReturn($query);

        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->setConstructorArgs([$em])
            ->onlyMethods(['getQuery'])
            ->getMock();
        $qb->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $qb->select('e')
            ->from(EmbeddedIdentifierEntity::class, 'e');

        $loader = new ORMQueryBuilderLoader($qb);
        $loader->getEntitiesByIds('id.value', [1, '', 2, 3, 'foo']);
    }

    public static function provideGuidEntityClasses(): array
    {
        return [
            ['Symfony\Bridge\Doctrine\Tests\Fixtures\GuidIdEntity'],
            ['Symfony\Bridge\Doctrine\Tests\Fixtures\UuidIdEntity'],
        ];
    }

    public static function provideUidEntityClasses(): array
    {
        return [
            ['Symfony\Bridge\Doctrine\Tests\Fixtures\UuidIdEntity'],
            ['Symfony\Bridge\Doctrine\Tests\Fixtures\UlidIdEntity'],
            ['Symfony\Bridge\Doctrine\Tests\Fixtures\CustomUuidIdEntity'],
        ];
    }

    private function createEntityManagerWithPlatform(AbstractPlatform $platform, ?Configuration $config = null): EntityManager
    {
        $config ??= DoctrineTestHelper::createTestConfiguration();
        if (\PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }

        $eventManager = new EventManager();
        $connection = $this->createStub(Connection::class);
        $connection->method('getConfiguration')->willReturn($config);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        if (method_exists(Connection::class, 'getEventManager')) {
            $connection->method('getEventManager')->willReturn($eventManager);
        }

        return new EntityManager($connection, $config, $eventManager);
    }

    private function createLoader(EntityManager $em, string $entityClass, ?array $expectedIds = null): ORMQueryBuilderLoader
    {
        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->setConstructorArgs([$em])
            ->onlyMethods(['getQuery'])
            ->getMock();

        if (null === $expectedIds) {
            $qb->expects($this->never())
                ->method('getQuery');
        } else {
            $query = $this->getQueryMock();
            $query
                ->method('getResult')
                ->willReturn([]);

            $query->expects($this->once())
                ->method('setParameter')
                ->with('ORMQueryBuilderLoader_getEntitiesByIds_id', $expectedIds, ArrayParameterType::STRING)
                ->willReturn($query);

            $qb->expects($this->once())
                ->method('getQuery')
                ->willReturn($query);
        }

        $qb->select('e')
            ->from($entityClass, 'e');

        return new ORMQueryBuilderLoader($qb);
    }

    /**
     * @return (LegacyQueryMock&MockObject)|(Query&MockObject)
     */
    private function getQueryMock(): AbstractQuery
    {
        $class = ((new \ReflectionClass(Query::class))->isFinal()) ? LegacyQueryMock::class : Query::class;

        return $this->createMock($class);
    }
}
