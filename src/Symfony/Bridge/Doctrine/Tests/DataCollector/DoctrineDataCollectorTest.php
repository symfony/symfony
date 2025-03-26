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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Bridge\Doctrine\Middleware\Debug\Query;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Dumper\CliDumper;

class DoctrineDataCollectorTest extends TestCase
{
    protected function setUp(): void
    {
        ClockMock::register(self::class);
        ClockMock::withClockMock(1500000000);
    }

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

    public function testCollectTimeWithFloatExecutionMS()
    {
        $queries = [
            ['sql' => 'SELECT * FROM table1', 'params' => [], 'types' => [], 'executionMS' => 0.23],
        ];
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());
        $c = unserialize(serialize($c));
        $this->assertEqualsWithDelta(0.23, $c->getTime(), .01);

        $queries = [
            ['sql' => 'SELECT * FROM table1', 'params' => [], 'types' => [], 'executionMS' => 1.02],
            ['sql' => 'SELECT * FROM table2', 'params' => [], 'types' => [], 'executionMS' => 0.75],
        ];
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());
        $c = unserialize(serialize($c));
        $this->assertEqualsWithDelta(1.77, $c->getTime(), .01);

        $queries = [
            ['sql' => 'SELECT * FROM table1', 'params' => [], 'types' => [], 'executionMS' => 0.15],
            ['sql' => 'SELECT * FROM table2', 'params' => [], 'types' => [], 'executionMS' => 0.32],
            ['sql' => 'SELECT * FROM table3', 'params' => [], 'types' => [], 'executionMS' => 0.07],
        ];
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());
        $c = unserialize(serialize($c));
        $this->assertEqualsWithDelta(0.54, $c->getTime(), .01);
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

    public function testReset()
    {
        $queries = [
            ['sql' => 'SELECT * FROM table1', 'params' => [], 'types' => [], 'executionMS' => 1],
        ];
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());

        $c->reset();
        $c->collect(new Request(), new Response());
        $c = unserialize(serialize($c));

        $this->assertEquals([], $c->getQueries());
    }

    /**
     * @dataProvider paramProvider
     */
    public function testCollectQueries($param, $types, $expected)
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
            $dumper = new CliDumper($out = fopen('php://memory', 'r+'));
            $dumper->setColors(false);
            $collectedParam->dump($dumper);
            $this->assertStringMatchesFormat($expected, print_r(stream_get_contents($out, -1, 0), true));
        } elseif (\is_string($expected)) {
            $this->assertStringMatchesFormat($expected, $collectedParam);
        } else {
            $this->assertEquals($expected, $collectedParam);
        }

        $this->assertTrue($collectedQueries['default'][0]['explainable']);
        $this->assertTrue($collectedQueries['default'][0]['runnable']);
    }

    public function testCollectQueryWithNoParams()
    {
        $queries = [
            ['sql' => 'SELECT * FROM table1', 'params' => [], 'types' => [], 'executionMS' => 1],
            ['sql' => 'SELECT * FROM table1', 'params' => null, 'types' => null, 'executionMS' => 1],
        ];
        $c = $this->createCollector($queries);
        $c->collect(new Request(), new Response());
        $c = unserialize(serialize($c));

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

    /**
     * @dataProvider paramProvider
     */
    public function testSerialization($param, array $types, $expected)
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
            $dumper = new CliDumper($out = fopen('php://memory', 'r+'));
            $dumper->setColors(false);
            $collectedParam->dump($dumper);
            $this->assertStringMatchesFormat($expected, print_r(stream_get_contents($out, -1, 0), true));
        } elseif (\is_string($expected)) {
            $this->assertStringMatchesFormat($expected, $collectedParam);
        } else {
            $this->assertEquals($expected, $collectedParam);
        }

        $this->assertTrue($collectedQueries['default'][0]['explainable']);
        $this->assertTrue($collectedQueries['default'][0]['runnable']);
    }

    public static function paramProvider(): array
    {
        return [
            ['some value', [], 'some value'],
            [1, [], 1],
            [true, [], true],
            [null, [], null],
        ];
    }

    private function createCollector(array $queries): DoctrineDataCollector
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $connection->expects($this->any())
            ->method('getDatabasePlatform')
            ->willReturn(new MySQLPlatform());

        $registry = $this->createMock(ManagerRegistry::class);
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

        $debugDataHolder = new DebugDataHolder();
        $collector = new DoctrineDataCollector($registry, $debugDataHolder);
        foreach ($queries as $queryData) {
            $query = new Query($queryData['sql'] ?? '');
            foreach (($queryData['params'] ?? []) as $key => $value) {
                if (\is_int($key)) {
                    ++$key;
                }

                $query->setValue($key, $value, $queryData['type'][$key] ?? ParameterType::STRING);
            }

            $query->start();

            $debugDataHolder->addQuery('default', $query);

            if (isset($queryData['executionMS'])) {
                usleep($queryData['executionMS'] * 1000000);
            }
            $query->stop();
        }

        return $collector;
    }
}
