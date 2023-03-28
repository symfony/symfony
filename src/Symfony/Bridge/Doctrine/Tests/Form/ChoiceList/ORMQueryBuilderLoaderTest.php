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

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Types\GuidType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\AbstractQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\ChoiceList\ORMQueryBuilderLoader;
use Symfony\Bridge\Doctrine\Tests\DoctrineTestHelper;
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
        if (Type::hasType('uuid')) {
            Type::overrideType('uuid', GuidType::class);
        }
    }

    public function testIdentifierTypeIsStringArray()
    {
        $this->checkIdentifierType(SingleStringIdEntity::class, class_exists(ArrayParameterType::class) ? ArrayParameterType::STRING : Connection::PARAM_STR_ARRAY);
    }

    public function testIdentifierTypeIsIntegerArray()
    {
        $this->checkIdentifierType(SingleIntIdEntity::class, class_exists(ArrayParameterType::class) ? ArrayParameterType::INTEGER : Connection::PARAM_INT_ARRAY);
    }

    protected function checkIdentifierType($classname, $expectedType)
    {
        $em = DoctrineTestHelper::createTestEntityManager();

        $query = $this->getMockBuilder(QueryMock::class)
            ->onlyMethods(['setParameter', 'getResult', 'getSql', '_doExecute'])
            ->getMock();

        $query
            ->method('getResult')
            ->willReturn([]);

        $query->expects($this->once())
            ->method('setParameter')
            ->with('ORMQueryBuilderLoader_getEntitiesByIds_id', [1, 2], $expectedType)
            ->willReturn($query);

        $qb = $this->getMockBuilder(\Doctrine\ORM\QueryBuilder::class)
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

        $query = $this->getMockBuilder(QueryMock::class)
            ->onlyMethods(['setParameter', 'getResult', 'getSql', '_doExecute'])
            ->getMock();

        $query
            ->method('getResult')
            ->willReturn([]);

        $query->expects($this->once())
            ->method('setParameter')
            ->with('ORMQueryBuilderLoader_getEntitiesByIds_id', [1, 2, 3, '9223372036854775808'], class_exists(ArrayParameterType::class) ? ArrayParameterType::INTEGER : Connection::PARAM_INT_ARRAY)
            ->willReturn($query);

        $qb = $this->getMockBuilder(\Doctrine\ORM\QueryBuilder::class)
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

    /**
     * @dataProvider provideGuidEntityClasses
     */
    public function testFilterEmptyUuids($entityClass)
    {
        $em = DoctrineTestHelper::createTestEntityManager();

        $query = $this->getMockBuilder(QueryMock::class)
            ->onlyMethods(['setParameter', 'getResult', 'getSql', '_doExecute'])
            ->getMock();

        $query
            ->method('getResult')
            ->willReturn([]);

        $query->expects($this->once())
            ->method('setParameter')
            ->with('ORMQueryBuilderLoader_getEntitiesByIds_id', ['71c5fd46-3f16-4abb-bad7-90ac1e654a2d', 'b98e8e11-2897-44df-ad24-d2627eb7f499'], class_exists(ArrayParameterType::class) ? ArrayParameterType::STRING : Connection::PARAM_STR_ARRAY)
            ->willReturn($query);

        $qb = $this->getMockBuilder(\Doctrine\ORM\QueryBuilder::class)
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

    /**
     * @dataProvider provideUidEntityClasses
     */
    public function testFilterUid($entityClass)
    {
        if (Type::hasType('uuid')) {
            Type::overrideType('uuid', UuidType::class);
        } else {
            Type::addType('uuid', UuidType::class);
        }
        if (!Type::hasType('ulid')) {
            Type::addType('ulid', UlidType::class);
        }

        $em = DoctrineTestHelper::createTestEntityManager();

        $query = $this->getMockBuilder(QueryMock::class)
            ->onlyMethods(['setParameter', 'getResult', 'getSql', '_doExecute'])
            ->getMock();

        $query
            ->method('getResult')
            ->willReturn([]);

        $query->expects($this->once())
            ->method('setParameter')
            ->with('ORMQueryBuilderLoader_getEntitiesByIds_id', [Uuid::fromString('71c5fd46-3f16-4abb-bad7-90ac1e654a2d')->toBinary(), Uuid::fromString('b98e8e11-2897-44df-ad24-d2627eb7f499')->toBinary()], class_exists(ArrayParameterType::class) ? ArrayParameterType::STRING : Connection::PARAM_STR_ARRAY)
            ->willReturn($query);

        $qb = $this->getMockBuilder(\Doctrine\ORM\QueryBuilder::class)
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

    /**
     * @dataProvider provideUidEntityClasses
     */
    public function testUidThrowProperException($entityClass)
    {
        if (Type::hasType('uuid')) {
            Type::overrideType('uuid', UuidType::class);
        } else {
            Type::addType('uuid', UuidType::class);
        }
        if (!Type::hasType('ulid')) {
            Type::addType('ulid', UlidType::class);
        }

        $em = DoctrineTestHelper::createTestEntityManager();

        $qb = $this->getMockBuilder(\Doctrine\ORM\QueryBuilder::class)
            ->setConstructorArgs([$em])
            ->onlyMethods(['getQuery'])
            ->getMock();

        $qb->expects($this->never())
            ->method('getQuery');

        $qb->select('e')
            ->from($entityClass, 'e');

        $loader = new ORMQueryBuilderLoader($qb);

        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessageMatches('/^Failed to transform "hello" into "(uuid|ulid)"\.$/');

        $loader->getEntitiesByIds('id', ['hello']);
    }

    public function testEmbeddedIdentifierName()
    {
        $em = DoctrineTestHelper::createTestEntityManager();

        $query = $this->getMockBuilder(QueryMock::class)
            ->onlyMethods(['setParameter', 'getResult', 'getSql', '_doExecute'])
            ->getMock();

        $query
            ->method('getResult')
            ->willReturn([]);

        $query->expects($this->once())
            ->method('setParameter')
            ->with('ORMQueryBuilderLoader_getEntitiesByIds_id_value', [1, 2, 3], class_exists(ArrayParameterType::class) ? ArrayParameterType::INTEGER : Connection::PARAM_INT_ARRAY)
            ->willReturn($query);

        $qb = $this->getMockBuilder(\Doctrine\ORM\QueryBuilder::class)
            ->setConstructorArgs([$em])
            ->onlyMethods(['getQuery'])
            ->getMock();
        $qb->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $qb->select('e')
            ->from('Symfony\Bridge\Doctrine\Tests\Fixtures\EmbeddedIdentifierEntity', 'e');

        $loader = new ORMQueryBuilderLoader($qb);
        $loader->getEntitiesByIds('id.value', [1, '', 2, 3, 'foo']);
    }

    public static function provideGuidEntityClasses()
    {
        return [
            ['Symfony\Bridge\Doctrine\Tests\Fixtures\GuidIdEntity'],
            ['Symfony\Bridge\Doctrine\Tests\Fixtures\UuidIdEntity'],
        ];
    }

    public static function provideUidEntityClasses()
    {
        return [
            ['Symfony\Bridge\Doctrine\Tests\Fixtures\UuidIdEntity'],
            ['Symfony\Bridge\Doctrine\Tests\Fixtures\UlidIdEntity'],
        ];
    }
}

class QueryMock extends AbstractQuery
{
    public function __construct()
    {
    }

    public function getSQL(): array|string
    {
    }

    protected function _doExecute(): Result|int
    {
    }
}
