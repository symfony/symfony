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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\ChoiceList\ORMQueryBuilderLoader;
use Symfony\Bridge\Doctrine\Test\DoctrineTestHelper;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Version;

class ORMQueryBuilderLoaderTest extends TestCase
{
    public function testIdentifierTypeIsStringArray()
    {
        $this->checkIdentifierType('Symfony\Bridge\Doctrine\Tests\Fixtures\SingleStringIdEntity', Connection::PARAM_STR_ARRAY);
    }

    public function testIdentifierTypeIsIntegerArray()
    {
        $this->checkIdentifierType('Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity', Connection::PARAM_INT_ARRAY);
    }

    protected function checkIdentifierType($classname, $expectedType)
    {
        $em = DoctrineTestHelper::createTestEntityManager();

        $query = $this->getMockBuilder('QueryMock')
            ->setMethods(array('setParameter', 'getResult', 'getSql', '_doExecute'))
            ->getMock();

        $query->expects($this->once())
            ->method('setParameter')
            ->with('ORMQueryBuilderLoader_getEntitiesByIds_id', array(1, 2), $expectedType)
            ->willReturn($query);

        $qb = $this->getMockBuilder('Doctrine\ORM\QueryBuilder')
            ->setConstructorArgs(array($em))
            ->setMethods(array('getQuery'))
            ->getMock();

        $qb->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $qb->select('e')
            ->from($classname, 'e');

        $loader = new ORMQueryBuilderLoader($qb);
        $loader->getEntitiesByIds('id', array(1, 2));
    }

    public function testFilterNonIntegerValues()
    {
        $em = DoctrineTestHelper::createTestEntityManager();

        $query = $this->getMockBuilder('QueryMock')
            ->setMethods(array('setParameter', 'getResult', 'getSql', '_doExecute'))
            ->getMock();

        $query->expects($this->once())
            ->method('setParameter')
            ->with('ORMQueryBuilderLoader_getEntitiesByIds_id', array(1, 2, 3, '9223372036854775808'), Connection::PARAM_INT_ARRAY)
            ->willReturn($query);

        $qb = $this->getMockBuilder('Doctrine\ORM\QueryBuilder')
            ->setConstructorArgs(array($em))
            ->setMethods(array('getQuery'))
            ->getMock();

        $qb->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $qb->select('e')
            ->from('Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity', 'e');

        $loader = new ORMQueryBuilderLoader($qb);
        $loader->getEntitiesByIds('id', array(1, '', 2, 3, 'foo', '9223372036854775808'));
    }

    /**
     * @dataProvider provideGuidEntityClasses
     */
    public function testFilterEmptyUuids($entityClass)
    {
        $em = DoctrineTestHelper::createTestEntityManager();

        $query = $this->getMockBuilder('QueryMock')
            ->setMethods(array('setParameter', 'getResult', 'getSql', '_doExecute'))
            ->getMock();

        $query->expects($this->once())
            ->method('setParameter')
            ->with('ORMQueryBuilderLoader_getEntitiesByIds_id', array('71c5fd46-3f16-4abb-bad7-90ac1e654a2d', 'b98e8e11-2897-44df-ad24-d2627eb7f499'), Connection::PARAM_STR_ARRAY)
            ->willReturn($query);

        $qb = $this->getMockBuilder('Doctrine\ORM\QueryBuilder')
            ->setConstructorArgs(array($em))
            ->setMethods(array('getQuery'))
            ->getMock();

        $qb->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $qb->select('e')
            ->from($entityClass, 'e');

        $loader = new ORMQueryBuilderLoader($qb);
        $loader->getEntitiesByIds('id', array('71c5fd46-3f16-4abb-bad7-90ac1e654a2d', '', 'b98e8e11-2897-44df-ad24-d2627eb7f499'));
    }

    public function testEmbeddedIdentifierName()
    {
        if (Version::compare('2.5.0') > 0) {
            $this->markTestSkipped('Applicable only for Doctrine >= 2.5.0');

            return;
        }

        $em = DoctrineTestHelper::createTestEntityManager();

        $query = $this->getMockBuilder('QueryMock')
            ->setMethods(array('setParameter', 'getResult', 'getSql', '_doExecute'))
            ->getMock();

        $query->expects($this->once())
            ->method('setParameter')
            ->with('ORMQueryBuilderLoader_getEntitiesByIds_id_value', array(1, 2, 3), Connection::PARAM_INT_ARRAY)
            ->willReturn($query);

        $qb = $this->getMockBuilder('Doctrine\ORM\QueryBuilder')
            ->setConstructorArgs(array($em))
            ->setMethods(array('getQuery'))
            ->getMock();
        $qb->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $qb->select('e')
            ->from('Symfony\Bridge\Doctrine\Tests\Fixtures\EmbeddedIdentifierEntity', 'e');

        $loader = new ORMQueryBuilderLoader($qb);
        $loader->getEntitiesByIds('id.value', array(1, '', 2, 3, 'foo'));
    }

    public function provideGuidEntityClasses()
    {
        return array(
            array('Symfony\Bridge\Doctrine\Tests\Fixtures\GuidIdEntity'),
            array('Symfony\Bridge\Doctrine\Tests\Fixtures\UuidIdEntity'),
        );
    }
}
