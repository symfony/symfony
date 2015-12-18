<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Profiler;

use Doctrine\DBAL\Platforms\MySqlPlatform;
use Symfony\Bridge\Doctrine\Profiler\DoctrineDataCollector;

class DoctrineDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testCollectConnections()
    {
        $c = $this->createCollector(array());
        $data = $c->getCollectedData();
        $this->assertEquals(array('default' => 'doctrine.dbal.default_connection'), $data->getConnections());
    }

    public function testCollectManagers()
    {
        $c = $this->createCollector(array());
        $data = $c->getCollectedData();
        $this->assertEquals(array('default' => 'doctrine.orm.default_entity_manager'), $data->getManagers());
    }

    public function testCollectQueryCount()
    {
        $c = $this->createCollector(array());
        $data = $c->getCollectedData();
        $this->assertEquals(0, $data->getQueryCount());

        $queries = array(
            array('sql' => 'SELECT * FROM table1', 'params' => array(), 'types' => array(), 'executionMS' => 0),
        );
        $c = $this->createCollector($queries);
        $data = $c->getCollectedData();
        $this->assertEquals(1, $data->getQueryCount());
    }

    public function testCollectTime()
    {
        $c = $this->createCollector(array());
        $data = $c->getCollectedData();
        $this->assertEquals(0, $data->getTime());

        $queries = array(
            array('sql' => 'SELECT * FROM table1', 'params' => array(), 'types' => array(), 'executionMS' => 1),
        );
        $c = $this->createCollector($queries);
        $data = $c->getCollectedData();
        $this->assertEquals(1, $data->getTime());

        $queries = array(
            array('sql' => 'SELECT * FROM table1', 'params' => array(), 'types' => array(), 'executionMS' => 1),
            array('sql' => 'SELECT * FROM table2', 'params' => array(), 'types' => array(), 'executionMS' => 2),
        );
        $c = $this->createCollector($queries);
        $data = $c->getCollectedData();
        $this->assertEquals(3, $data->getTime());
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
        $data = $c->getCollectedData();

        $collected_queries = $data->getQueries();
        $this->assertEquals($expected, $collected_queries['default'][0]['params'][0]);
        $this->assertEquals($explainable, $collected_queries['default'][0]['explainable']);
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
        $data = $c->getCollectedData();
        $data = unserialize(serialize($data));

        $collected_queries = $data->getQueries();
        $this->assertEquals($expected, $collected_queries['default'][0]['params'][0]);
        $this->assertEquals($explainable, $collected_queries['default'][0]['explainable']);
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
            ->method('getManagers')
            ->will($this->returnValue(array()));

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
