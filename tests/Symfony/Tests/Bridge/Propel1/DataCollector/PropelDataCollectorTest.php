<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Bridge\Propel1\DataCollector;

use Symfony\Bridge\Propel1\DataCollector\PropelDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Tests\Bridge\Propel1\Propel1TestCase;

class PropelDataCollectorTest extends Propel1TestCase
{
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
            "time: 0.000 sec | mem: 1.4 MB | SET NAMES 'utf8'",
        );

        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());

        $this->assertEquals(array(
            array(
                'sql'       => "SET NAMES 'utf8'",
                'time'      => '0.000 sec',
                'memory'    => '1.4 MB'
            )
        ), $c->getQueries());
        $this->assertEquals(1, $c->getQueryCount());
    }

    public function testCollectWithMultipleData()
    {
        $queries = array(
            "time: 0.000 sec | mem: 1.4 MB | SET NAMES 'utf8'",
            "time: 0.012 sec | mem: 2.4 MB | SELECT tags.NAME, image.FILENAME FROM tags LEFT JOIN image ON tags.IMAGEID = image.ID WHERE image.ID = 12"
        );

        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());

        $this->assertEquals(array(
            array(
                'sql'       => "SET NAMES 'utf8'",
                'time'      => '0.000 sec',
                'memory'    => '1.4 MB'
            ),
            array(
                'sql'       => "SELECT tags.NAME, image.FILENAME FROM tags LEFT JOIN image ON tags.IMAGEID = image.ID WHERE image.ID = 12",
                'time'      => '0.012 sec',
                'memory'    => '2.4 MB'
            )
        ), $c->getQueries());
        $this->assertEquals(2, $c->getQueryCount());
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
