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
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\DataCollector\DoctrineDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Dumper\CliDumper;

// Doctrine DBAL 2 compatibility
class_exists(\Doctrine\DBAL\Platforms\MySqlPlatform::class);

/**
 * @group legacy
 */
class DoctrineDataCollectorWithDebugStackTest extends TestCase
{
    use DoctrineDataCollectorTestTrait;

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

        $this->assertEquals(['default' => []], $c->getQueries());
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

        $this->assertEquals($explainable, $collectedQueries['default'][0]['explainable']);
        $this->assertSame($runnable, $collectedQueries['default'][0]['runnable']);
    }

    /**
     * @dataProvider paramProvider
     */
    public function testSerialization($param, array $types, $expected, $explainable, bool $runnable = true)
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

        $this->assertEquals($explainable, $collectedQueries['default'][0]['explainable']);
        $this->assertSame($runnable, $collectedQueries['default'][0]['runnable']);
    }

    public static function paramProvider(): array
    {
        return [
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
            ['this is not a date', ['date'], "⚠ Could not convert PHP value 'this is not a date'%S to type %Sdate%S. Expected one of the following types: null, DateTime", false, false],
            [
                new \stdClass(),
                ['date'],
                <<<EOTXT
{#%d
  ⚠: "Could not convert PHP value of type %SstdClass%S to type %Sdate%S. Expected one of the following types: null, DateTime"
}
EOTXT
                ,
                false,
                false,
            ],
        ];
    }

    private function createCollector(array $queries): DoctrineDataCollector
    {
        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $connection->expects($this->any())
            ->method('getDatabasePlatform')
            ->willReturn(new MySqlPlatform());

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

        $collector = new DoctrineDataCollector($registry);
        $logger = $this->createMock(DebugStack::class);
        $logger->queries = $queries;
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
