<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Propel1\Tests\DataCollector;

use Symfony\Bridge\Propel1\DataCollector\PropelDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bridge\Propel1\Tests\Propel1TestCase;

class PropelDataCollectorTest extends Propel1TestCase
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\HttpFoundation\Request')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }
    }

    public function testCollectWithoutData()
    {
        $c = $this->createCollector(array());
        $c->collect(new Request(), new Response());

        $this->assertEquals(array(), $c->getQueries());
        $this->assertEquals(0, $c->getQueryCount());
    }

    public function testCollectWithData()
    {
        $queries = array(
            "time: 0.000 sec | mem: 1.4 MB | connection: default | SET NAMES 'utf8'",
        );

        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());

        $this->assertEquals(array(
            array(
                'sql' => "SET NAMES 'utf8'",
                'time' => '0.000 sec',
                'connection' => 'default',
                'memory' => '1.4 MB',
            ),
        ), $c->getQueries());
        $this->assertEquals(1, $c->getQueryCount());
    }

    public function testCollectWithMultipleData()
    {
        $queries = array(
            "time: 0.000 sec | mem: 1.4 MB | connection: default | SET NAMES 'utf8'",
            "time: 0.012 sec | mem: 2.4 MB | connection: default | SELECT tags.NAME, image.FILENAME FROM tags LEFT JOIN image ON tags.IMAGEID = image.ID WHERE image.ID = 12",
            "time: 0.012 sec | mem: 2.4 MB | connection: default | INSERT INTO `table` (`some_array`) VALUES ('| 1 | 2 | 3 |')",
        );

        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());

        $this->assertEquals(array(
            array(
                'sql' => "SET NAMES 'utf8'",
                'time' => '0.000 sec',
                'connection' => 'default',
                'memory' => '1.4 MB',
            ),
            array(
                'sql' => "SELECT tags.NAME, image.FILENAME FROM tags LEFT JOIN image ON tags.IMAGEID = image.ID WHERE image.ID = 12",
                'time' => '0.012 sec',
                'connection' => 'default',
                'memory' => '2.4 MB',
            ),
            array(
                'sql' => "INSERT INTO `table` (`some_array`) VALUES ('| 1 | 2 | 3 |')",
                'time' => '0.012 sec',
                'connection' => 'default',
                'memory' => '2.4 MB',
            ),
        ), $c->getQueries());
        $this->assertEquals(3, $c->getQueryCount());
        $this->assertEquals(0.024, $c->getTime());
    }

    private function createCollector($queries)
    {
        $config = $this->getMock('\PropelConfiguration');
        $config
            ->expects($this->any())
            ->method('getParameter')
            ->will($this->returnArgument(1))
            ;

        $logger = $this->getMock('\Symfony\Bridge\Propel1\Logger\PropelLogger');
        $logger
            ->expects($this->any())
            ->method('getQueries')
            ->will($this->returnValue($queries))
            ;

        return new PropelDataCollector($logger, $config);
    }
}
