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

// Doctrine DBAL 2 compatibility
class_exists(\Doctrine\DBAL\Platforms\MySqlPlatform::class);

class DoctrineDataCollectorTest extends TestCase
{
    use DoctrineDataCollectorTestTrait;

    protected function setUp(): void
    {
        ClockMock::register(self::class);
        ClockMock::withClockMock(1500000000);
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

        self::assertEquals([], $c->getQueries());
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
            self::assertStringMatchesFormat($expected, print_r(stream_get_contents($out, -1, 0), true));
        } elseif (\is_string($expected)) {
            self::assertStringMatchesFormat($expected, $collectedParam);
        } else {
            self::assertEquals($expected, $collectedParam);
        }

        self::assertTrue($collectedQueries['default'][0]['explainable']);
        self::assertTrue($collectedQueries['default'][0]['runnable']);
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
        self::assertInstanceOf(Data::class, $collectedQueries['default'][0]['params']);
        self::assertEquals([], $collectedQueries['default'][0]['params']->getValue());
        self::assertTrue($collectedQueries['default'][0]['explainable']);
        self::assertTrue($collectedQueries['default'][0]['runnable']);
        self::assertInstanceOf(Data::class, $collectedQueries['default'][1]['params']);
        self::assertEquals([], $collectedQueries['default'][1]['params']->getValue());
        self::assertTrue($collectedQueries['default'][1]['explainable']);
        self::assertTrue($collectedQueries['default'][1]['runnable']);
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
            self::assertStringMatchesFormat($expected, print_r(stream_get_contents($out, -1, 0), true));
        } elseif (\is_string($expected)) {
            self::assertStringMatchesFormat($expected, $collectedParam);
        } else {
            self::assertEquals($expected, $collectedParam);
        }

        self::assertTrue($collectedQueries['default'][0]['explainable']);
        self::assertTrue($collectedQueries['default'][0]['runnable']);
    }

    public function paramProvider(): array
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
        $connection = self::getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $connection->expects(self::any())
            ->method('getDatabasePlatform')
            ->willReturn(new MySqlPlatform());

        $registry = self::createMock(ManagerRegistry::class);
        $registry
            ->expects(self::any())
            ->method('getConnectionNames')
            ->willReturn(['default' => 'doctrine.dbal.default_connection']);
        $registry
            ->expects(self::any())
            ->method('getManagerNames')
            ->willReturn(['default' => 'doctrine.orm.default_entity_manager']);
        $registry->expects(self::any())
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
                sleep($queryData['executionMS']);
            }
            $query->stop();
        }

        return $collector;
    }
}
