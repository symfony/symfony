<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\DataCollector;

use Doctrine\DBAL\Platforms\MySqlPlatform;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DoctrineDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testCollectConnections()
    {
        $c = $this->createCollector(array());
        $c->collect(new Request(), new Response());
        $this->assertEquals(array('default' => 'doctrine.dbal.default_connection'), $c->getConnections());
    }

    public function testCollectManagers()
    {
        $c = $this->createCollector(array());
        $c->collect(new Request(), new Response());
        $this->assertEquals(array('default' => 'doctrine.orm.default_entity_manager'), $c->getManagers());
    }

    public function testCollectQueryCount()
    {
        $c = $this->createCollector(array());
        $c->collect(new Request(), new Response());
        $this->assertEquals(0, $c->getQueryCount());

        $queries = array(
            array('sql' => 'SELECT * FROM table1', 'params' => array(), 'types' => array(), 'executionMS' => 0),
        );
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());
        $this->assertEquals(1, $c->getQueryCount());
    }

    public function testCollectTime()
    {
        $c = $this->createCollector(array());
        $c->collect(new Request(), new Response());
        $this->assertEquals(0, $c->getTime());

        $queries = array(
            array('sql' => 'SELECT * FROM table1', 'params' => array(), 'types' => array(), 'executionMS' => 1),
        );
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());
        $this->assertEquals(1, $c->getTime());

        $queries = array(
            array('sql' => 'SELECT * FROM table1', 'params' => array(), 'types' => array(), 'executionMS' => 1),
            array('sql' => 'SELECT * FROM table2', 'params' => array(), 'types' => array(), 'executionMS' => 2),
        );
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());
        $this->assertEquals(3, $c->getTime());
    }

    /**
     * @dataProvider paramProvider
     */
    public function testCollectQueries($param, $types, $expected, $explainable)
    {
        $queries = array(
            array('sql' => 'SELECT * FROM table1 WHERE field1 = ?1', 'params' => array($param), 'types' => $types, 'executionMS' => 1),
        );
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());

        $collectedQueries = $c->getQueries();
        $this->assertEquals($expected, $collectedQueries['default'][0]['params'][0]);
        $this->assertEquals($explainable, $collectedQueries['default'][0]['explainable']);
    }

    public function testCollectQueryWithNoParams()
    {
        $queries = array(
            array('sql' => 'SELECT * FROM table1', 'params' => array(), 'types' => array(), 'executionMS' => 1),
            array('sql' => 'SELECT * FROM table1', 'params' => null, 'types' => null, 'executionMS' => 1),
        );
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());

        $collectedQueries = $c->getQueries();
        $this->assertEquals(array(), $collectedQueries['default'][0]['params']);
        $this->assertTrue($collectedQueries['default'][0]['explainable']);
        $this->assertEquals(array(), $collectedQueries['default'][1]['params']);
        $this->assertTrue($collectedQueries['default'][1]['explainable']);
    }

    /**
     * @dataProvider paramProvider
     */
    public function testSerialization($param, $types, $expected, $explainable)
    {
        $queries = array(
            array('sql' => 'SELECT * FROM table1 WHERE field1 = ?1', 'params' => array($param), 'types' => $types, 'executionMS' => 1),
        );
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());
        $c = unserialize(serialize($c));

        $collectedQueries = $c->getQueries();
        $this->assertEquals($expected, $collectedQueries['default'][0]['params'][0]);
        $this->assertEquals($explainable, $collectedQueries['default'][0]['explainable']);
    }

    public function paramProvider()
    {
        return array(
            array('some value', array(), 'some value', true),
            array(1, array(), 1, true),
            array(true, array(), true, true),
            array(null, array(), null, true),
            array(new \DateTime('2011-09-11'), array('date'), '2011-09-11', true),
            array(fopen(__FILE__, 'r'), array(), 'Resource(stream)', false),
            array(new \SplFileInfo(__FILE__), array(), 'Object(SplFileInfo)', false),
        );
    }

    private function createCollector($queries)
    {
        $connection = $this->getMockBuilder('Doctrine\DBAL\Connection')
            ->disableOriginalConstructor()
            ->getMock();
        $connection->expects($this->any())
            ->method('getDatabasePlatform')
            ->will($this->returnValue(new MySqlPlatform()));

        $registry = $this->getMock('Doctrine\Common\Persistence\ManagerRegistry');
        $registry
                ->expects($this->any())
                ->method('getConnectionNames')
                ->will($this->returnValue(array('default' => 'doctrine.dbal.default_connection')));
        $registry
                ->expects($this->any())
                ->method('getManagerNames')
                ->will($this->returnValue(array('default' => 'doctrine.orm.default_entity_manager')));
        $registry->expects($this->any())
            ->method('getConnection')
            ->will($this->returnValue($connection));

        $logger = $this->getMock('Doctrine\DBAL\Logging\DebugStack');
        $logger->queries = $queries;

        $collector = new DoctrineDataCollector($registry);
        $collector->addLogger('default', $logger);

        return $collector;
    }
}
