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
use Doctrine\DBAL\Version;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Dumper\CliDumper;

class DoctrineDataCollectorTest extends TestCase
{
    public function testCollectConnections()
    {
        $c = $this->createCollector([]);
        $c->collect(new Request(), new Response());
        $this->assertEquals(['default' => 'doctrine.dbal.default_connection'], $c->getConnections());
    }

    public function testCollectManagers()
    {
        $c = $this->createCollector([]);
        $c->collect(new Request(), new Response());
        $this->assertEquals(['default' => 'doctrine.orm.default_entity_manager'], $c->getManagers());
    }

    public function testCollectQueryCount()
    {
        $c = $this->createCollector([]);
        $c->collect(new Request(), new Response());
        $this->assertEquals(0, $c->getQueryCount());

        $queries = [
            ['sql' => 'SELECT * FROM table1', 'params' => [], 'types' => [], 'executionMS' => 0],
        ];
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());
        $this->assertEquals(1, $c->getQueryCount());
    }

    public function testCollectTime()
    {
        $c = $this->createCollector([]);
        $c->collect(new Request(), new Response());
        $this->assertEquals(0, $c->getTime());

        $queries = [
            ['sql' => 'SELECT * FROM table1', 'params' => [], 'types' => [], 'executionMS' => 1],
        ];
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());
        $this->assertEquals(1, $c->getTime());

        $queries = [
            ['sql' => 'SELECT * FROM table1', 'params' => [], 'types' => [], 'executionMS' => 1],
            ['sql' => 'SELECT * FROM table2', 'params' => [], 'types' => [], 'executionMS' => 2],
        ];
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());
        $this->assertEquals(3, $c->getTime());
    }

    /**
     * @dataProvider paramProvider
     */
    public function testCollectQueries($param, $types, $expected, $explainable, bool $runnable = true)
    {
        $queries = [
            ['sql' => 'SELECT * FROM table1 WHERE field1 = ?1', 'params' => [$param], 'types' => $types, 'executionMS' => 1],
        ];
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());

        $collectedQueries = $c->getQueries();

        $collectedParam = $collectedQueries['default'][0]['params'][0];
        if ($collectedParam instanceof Data) {
            $dumper = new CliDumper($out = fopen('php://memory', 'r+b'));
            $dumper->setColors(false);
            $collectedParam->dump($dumper);
            $this->assertStringMatchesFormat($expected, print_r(stream_get_contents($out, -1, 0), true));
        } else {
            $this->assertEquals($expected, $collectedParam);
        }

        $this->assertEquals($explainable, $collectedQueries['default'][0]['explainable']);
        $this->assertSame($runnable, $collectedQueries['default'][0]['runnable']);
    }

    public function testCollectQueryWithNoParams()
    {
        $queries = [
            ['sql' => 'SELECT * FROM table1', 'params' => [], 'types' => [], 'executionMS' => 1],
            ['sql' => 'SELECT * FROM table1', 'params' => null, 'types' => null, 'executionMS' => 1],
        ];
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());

        $collectedQueries = $c->getQueries();
        $this->assertInstanceOf(Data::class, $collectedQueries['default'][0]['params']);
        $this->assertEquals([], $collectedQueries['default'][0]['params']->getValue());
        $this->assertTrue($collectedQueries['default'][0]['explainable']);
        $this->assertTrue($collectedQueries['default'][0]['runnable']);
        $this->assertInstanceOf(Data::class, $collectedQueries['default'][1]['params']);
        $this->assertEquals([], $collectedQueries['default'][1]['params']->getValue());
        $this->assertTrue($collectedQueries['default'][1]['explainable']);
        $this->assertTrue($collectedQueries['default'][1]['runnable']);
    }

    public function testCollectQueryWithNoTypes()
    {
        $queries = [
            ['sql' => 'SET sql_mode=(SELECT REPLACE(@@sql_mode, \'ONLY_FULL_GROUP_BY\', \'\'))', 'params' => [], 'types' => null, 'executionMS' => 1],
        ];
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());

        $collectedQueries = $c->getQueries();
        $this->assertSame([], $collectedQueries['default'][0]['types']);
    }

    public function testReset()
    {
        $queries = [
            ['sql' => 'SELECT * FROM table1', 'params' => [], 'types' => [], 'executionMS' => 1],
        ];
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());

        $c->reset();
        $c->collect(new Request(), new Response());

        $this->assertEquals(['default' => []], $c->getQueries());
    }

    /**
     * @dataProvider paramProvider
     */
    public function testSerialization($param, $types, $expected, $explainable, bool $runnable = true)
    {
        $queries = [
            ['sql' => 'SELECT * FROM table1 WHERE field1 = ?1', 'params' => [$param], 'types' => $types, 'executionMS' => 1],
        ];
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());
        $c = unserialize(serialize($c));

        $collectedQueries = $c->getQueries();

        $collectedParam = $collectedQueries['default'][0]['params'][0];
        if ($collectedParam instanceof Data) {
            $dumper = new CliDumper($out = fopen('php://memory', 'r+b'));
            $dumper->setColors(false);
            $collectedParam->dump($dumper);
            $this->assertStringMatchesFormat($expected, print_r(stream_get_contents($out, -1, 0), true));
        } else {
            $this->assertEquals($expected, $collectedParam);
        }

        $this->assertEquals($explainable, $collectedQueries['default'][0]['explainable']);
        $this->assertSame($runnable, $collectedQueries['default'][0]['runnable']);
    }

    public function paramProvider()
    {
        $tests = [
            ['some value', [], 'some value', true],
            [1, [], 1, true],
            [true, [], true, true],
            [null, [], null, true],
            [new \DateTime('2011-09-11'), ['date'], '2011-09-11', true],
            [fopen(__FILE__, 'r'), [], '/* Resource(stream) */', false, false],
            [
                new \stdClass(),
                [],
                <<<EOTXT
{#%d
  ⚠: "Object of class "stdClass" could not be converted to string."
}
EOTXT
                ,
                false,
                false,
            ],
            [
                new StringRepresentableClass(),
                [],
                <<<EOTXT
Symfony\Bridge\Doctrine\Tests\DataCollector\StringRepresentableClass {#%d
  __toString(): "string representation"
}
EOTXT
                ,
                false,
            ],
        ];

        if (version_compare(Version::VERSION, '2.6', '>=')) {
            $tests[] = ['this is not a date', ['date'], "⚠ Could not convert PHP value 'this is not a date' of type 'string' to type 'date'. Expected one of the following types: null, DateTime", false, false];
            $tests[] = [
                new \stdClass(),
                ['date'],
                <<<EOTXT
{#%d
  ⚠: "Could not convert PHP value of type 'stdClass' to type 'date'. Expected one of the following types: null, DateTime"
}
EOTXT
                ,
                false,
                false,
            ];
        }

        return $tests;
    }

    private function createCollector($queries)
    {
        $connection = $this->getMockBuilder('Doctrine\DBAL\Connection')
            ->disableOriginalConstructor()
            ->getMock();
        $connection->expects($this->any())
            ->method('getDatabasePlatform')
            ->willReturn(new MySqlPlatform());

        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $registry
            ->expects($this->any())
            ->method('getConnectionNames')
            ->willReturn(['default' => 'doctrine.dbal.default_connection']);
        $registry
            ->expects($this->any())
            ->method('getManagerNames')
            ->willReturn(['default' => 'doctrine.orm.default_entity_manager']);
        $registry->expects($this->any())
            ->method('getConnection')
            ->willReturn($connection);

        $logger = $this->getMockBuilder('Doctrine\DBAL\Logging\DebugStack')->getMock();
        $logger->queries = $queries;

        $collector = new DoctrineDataCollector($registry);
        $collector->addLogger('default', $logger);

        return $collector;
    }
}

class StringRepresentableClass
{
    public function __toString(): string
    {
        return 'string representation';
    }
}
