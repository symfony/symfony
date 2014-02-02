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

use Symfony\Bridge\Doctrine\Form\ChoiceList\ORMQueryBuilderLoader;
use Symfony\Bridge\Doctrine\Tests\DoctrineOrmTestCase;
use Doctrine\DBAL\Connection;

class ORMQueryBuilderLoaderTest extends DoctrineOrmTestCase
{
    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testItOnlyWorksWithQueryBuilderOrClosure()
    {
        new ORMQueryBuilderLoader(new \stdClass());
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testClosureRequiresTheEntityManager()
    {
        $closure = function () {};

        new ORMQueryBuilderLoader($closure);
    }

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
        $em = $this->createTestEntityManager();

        $query = $this->getMockBuilder('QueryMock')
            ->setMethods(array('setParameter', 'getResult', 'getSql', '_doExecute'))
            ->getMock();

        $query->expects($this->once())
            ->method('setParameter')
            ->with('ORMQueryBuilderLoader_getEntitiesByIds_id', array(), $expectedType)
            ->will($this->returnValue($query));

        $qb = $this->getMockBuilder('Doctrine\ORM\QueryBuilder')
            ->setConstructorArgs(array($em))
            ->setMethods(array('getQuery'))
            ->getMock();

        $qb->expects($this->once())
            ->method('getQuery')
            ->will($this->returnValue($query));

        $qb->select('e')
            ->from($classname, 'e');

        $loader = new ORMQueryBuilderLoader($qb);
        $loader->getEntitiesByIds('id', array());
    }
}
