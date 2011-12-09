<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Bridge\Doctrine\DataCollector;

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
            array('sql' => "SELECT * FROM table1", 'params' => array(), 'types' => array(), 'executionMS' => 0)
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
            array('sql' => "SELECT * FROM table1", 'params' => array(), 'types' => array(), 'executionMS' => 1)
        );
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());
        $this->assertEquals(1, $c->getTime());

        $queries = array(
            array('sql' => "SELECT * FROM table1", 'params' => array(), 'types' => array(), 'executionMS' => 1),
            array('sql' => "SELECT * FROM table2", 'params' => array(), 'types' => array(), 'executionMS' => 2)
        );
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());
        $this->assertEquals(3, $c->getTime());
    }

    /**
     * @dataProvider paramProvider
     */
    public function testCollectQueries($param, $expected)
    {
        $queries = array(
            array('sql' => "SELECT * FROM table1 WHERE field1 = ?1", 'params' => array($param), 'types' => array(), 'executionMS' => 1)
        );
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());

        $collected_queries = $c->getQueries();
        $this->assertEquals($expected, $collected_queries[0]['params'][0]);
    }

    /**
     * @dataProvider paramProvider
     */
    public function testSerialization($param, $expected)
    {
        $queries = array(
            array('sql' => "SELECT * FROM table1 WHERE field1 = ?1", 'params' => array($param), 'types' => array(), 'executionMS' => 1)
        );
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());
        $c = unserialize(serialize($c));

        $collected_queries = $c->getQueries();
        $this->assertEquals($expected, $collected_queries[0]['params'][0]);
    }

    public function paramProvider()
    {
        return array(
            array('some value', 'some value'),
            array(1, '1'),
            array(true, 'true'),
            array(null, 'null'),
            array(new \stdClass(), 'Object(stdClass)'),
            array(fopen(__FILE__, 'r'), 'Resource(stream)'),
            array(new \SplFileInfo(__FILE__), 'Object(SplFileInfo)'),
        );
    }

    private function createCollector($queries)
    {
        $registry = $this->getMock('Symfony\Bridge\Doctrine\RegistryInterface');
        $registry
                ->expects($this->any())
                ->method('getConnectionNames')
                ->will($this->returnValue(array('default' => 'doctrine.dbal.default_connection')));
        $registry
                ->expects($this->any())
                ->method('getEntityManagerNames')
                ->will($this->returnValue(array('default' => 'doctrine.orm.default_entity_manager')));

        $logger = $this->getMock('Symfony\Bridge\Doctrine\Logger\DbalLogger');
        $logger->queries = $queries;

        return new DoctrineDataCollector($registry, $logger);
    }
}
