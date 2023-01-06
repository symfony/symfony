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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait DoctrineDataCollectorTestTrait
{
    public function testCollectConnections()
    {
        $c = $this->createCollector([]);
        $c->collect(new Request(), new Response());
        $c = unserialize(serialize($c));
        $this->assertEquals(['default' => 'doctrine.dbal.default_connection'], $c->getConnections());
    }

    public function testCollectManagers()
    {
        $c = $this->createCollector([]);
        $c->collect(new Request(), new Response());
        $c = unserialize(serialize($c));
        $this->assertEquals(['default' => 'doctrine.orm.default_entity_manager'], $c->getManagers());
    }

    public function testCollectQueryCount()
    {
        $c = $this->createCollector([]);
        $c->collect(new Request(), new Response());
        $c = unserialize(serialize($c));
        $this->assertEquals(0, $c->getQueryCount());

        $queries = [
            ['sql' => 'SELECT * FROM table1', 'params' => [], 'types' => [], 'executionMS' => 0],
        ];
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());
        $c = unserialize(serialize($c));
        $this->assertEquals(1, $c->getQueryCount());
    }

    public function testCollectTime()
    {
        $c = $this->createCollector([]);
        $c->collect(new Request(), new Response());
        $c = unserialize(serialize($c));
        $this->assertEquals(0, $c->getTime());

        $queries = [
            ['sql' => 'SELECT * FROM table1', 'params' => [], 'types' => [], 'executionMS' => 1],
        ];
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());
        $c = unserialize(serialize($c));
        $this->assertEquals(1, $c->getTime());

        $queries = [
            ['sql' => 'SELECT * FROM table1', 'params' => [], 'types' => [], 'executionMS' => 1],
            ['sql' => 'SELECT * FROM table2', 'params' => [], 'types' => [], 'executionMS' => 2],
        ];
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());
        $c = unserialize(serialize($c));
        $this->assertEquals(3, $c->getTime());
    }

    public function testCollectQueryWithNoTypes()
    {
        $queries = [
            ['sql' => 'SET sql_mode=(SELECT REPLACE(@@sql_mode, \'ONLY_FULL_GROUP_BY\', \'\'))', 'params' => [], 'types' => null, 'executionMS' => 1],
        ];
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());
        $c = unserialize(serialize($c));

        $collectedQueries = $c->getQueries();
        $this->assertSame([], $collectedQueries['default'][0]['types']);
    }
}
