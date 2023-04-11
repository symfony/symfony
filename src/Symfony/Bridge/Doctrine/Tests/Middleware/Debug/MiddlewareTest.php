<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Middleware\Debug;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Bridge\Doctrine\Middleware\Debug\Middleware;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @requires extension pdo_sqlite
 */
class MiddlewareTest extends TestCase
{
    private DebugDataHolder $debugDataHolder;
    private Connection $conn;
    private ?Stopwatch $stopwatch;

    protected function setUp(): void
    {
        parent::setUp();

        if (!interface_exists(MiddlewareInterface::class)) {
            $this->markTestSkipped(sprintf('%s needed to run this test', MiddlewareInterface::class));
        }

        ClockMock::withClockMock(false);
    }

    private function init(bool $withStopwatch = true): void
    {
        $this->stopwatch = $withStopwatch ? new Stopwatch() : null;

        $configuration = new Configuration();
        $this->debugDataHolder = new DebugDataHolder();
        $configuration->setMiddlewares([new Middleware($this->debugDataHolder, $this->stopwatch)]);

        $this->conn = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $configuration);

        $this->conn->executeQuery(<<<EOT
CREATE TABLE products (
	id INTEGER PRIMARY KEY,
	name TEXT NOT NULL,
	price REAL NOT NULL,
	stock INTEGER NOT NULL,
	picture BLOB NULL,
	tags TEXT NULL,
	created_at TEXT NULL
);
EOT);
    }

    private function getResourceFromString(string $str)
    {
        $res = fopen('php://temp', 'r+');
        fwrite($res, $str);

        return $res;
    }

    public static function provideExecuteMethod(): array
    {
        return [
            'executeStatement' => [
                static fn (Statement|Connection $target, mixed ...$args) => $target->executeStatement(...$args),
            ],
            'executeQuery' => [
                static fn (Statement|Connection $target, mixed ...$args) => $target->executeQuery(...$args),
            ],
        ];
    }

    /**
     * @dataProvider provideExecuteMethod
     */
    public function testWithoutBinding(callable $executeMethod)
    {
        $this->init();

        $executeMethod($this->conn, 'INSERT INTO products(name, price, stock) VALUES ("product1", 12.5, 5)');

        $debug = $this->debugDataHolder->getData()['default'] ?? [];
        $this->assertCount(2, $debug);
        $this->assertSame('INSERT INTO products(name, price, stock) VALUES ("product1", 12.5, 5)', $debug[1]['sql']);
        $this->assertSame([], $debug[1]['params']);
        $this->assertSame([], $debug[1]['types']);
        $this->assertGreaterThan(0, $debug[1]['executionMS']);
    }

    /**
     * @dataProvider provideExecuteMethod
     */
    public function testWithValueBound(callable $executeMethod)
    {
        $this->init();

        $sql = <<<EOT
INSERT INTO products(name, price, stock, picture, tags, created_at)
VALUES (?, ?, ?, ?, ?, ?)
EOT;

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(1, 'product1');
        $stmt->bindValue(2, 12.5);
        $stmt->bindValue(3, 5, ParameterType::INTEGER);
        $stmt->bindValue(4, $res = $this->getResourceFromString('mydata'), ParameterType::BINARY);
        $stmt->bindValue(5, ['foo', 'bar'], Types::SIMPLE_ARRAY);
        $stmt->bindValue(6, new \DateTime('2022-06-12 11:00:00'), Types::DATETIME_MUTABLE);

        $executeMethod($stmt);

        $debug = $this->debugDataHolder->getData()['default'] ?? [];
        $this->assertCount(2, $debug);
        $this->assertSame($sql, $debug[1]['sql']);
        $this->assertSame(['product1', 12.5, 5, $res, 'foo,bar', '2022-06-12 11:00:00'], $debug[1]['params']);
        $this->assertSame([ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::BINARY, ParameterType::STRING, ParameterType::STRING], $debug[1]['types']);
        $this->assertGreaterThan(0, $debug[1]['executionMS']);
    }

    /**
     * @dataProvider provideExecuteMethod
     */
    public function testWithParamBound(callable $executeMethod)
    {
        $this->init();

        $product = 'product1';
        $price = 12.5;
        $stock = 5;

        $stmt = $this->conn->prepare('INSERT INTO products(name, price, stock) VALUES (?, ?, ?)');
        $stmt->bindParam(1, $product);
        $stmt->bindParam(2, $price);
        $stmt->bindParam(3, $stock, ParameterType::INTEGER);

        $executeMethod($stmt);

        // Debug data should not be affected by these changes
        $debug = $this->debugDataHolder->getData()['default'] ?? [];
        $this->assertCount(2, $debug);
        $this->assertSame('INSERT INTO products(name, price, stock) VALUES (?, ?, ?)', $debug[1]['sql']);
        $this->assertSame(['product1', '12.5', 5], $debug[1]['params']);
        $this->assertSame([ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER], $debug[1]['types']);
        $this->assertGreaterThan(0, $debug[1]['executionMS']);
    }

    public static function provideEndTransactionMethod(): array
    {
        return [
            'commit' => [static fn (Connection $conn) => $conn->commit(), '"COMMIT"'],
            'rollback' => [static fn (Connection $conn) => $conn->rollBack(), '"ROLLBACK"'],
        ];
    }

    /**
     * @dataProvider provideEndTransactionMethod
     */
    public function testTransaction(callable $endTransactionMethod, string $expectedEndTransactionDebug)
    {
        $this->init();

        $this->conn->beginTransaction();
        $this->conn->beginTransaction();
        $this->conn->executeStatement('INSERT INTO products(name, price, stock) VALUES ("product1", 12.5, 5)');
        $endTransactionMethod($this->conn);
        $endTransactionMethod($this->conn);
        $this->conn->beginTransaction();
        $this->conn->executeStatement('INSERT INTO products(name, price, stock) VALUES ("product2", 15.5, 12)');
        $endTransactionMethod($this->conn);

        $debug = $this->debugDataHolder->getData()['default'] ?? [];
        $this->assertCount(7, $debug);
        $this->assertSame('"START TRANSACTION"', $debug[1]['sql']);
        $this->assertGreaterThan(0, $debug[1]['executionMS']);
        $this->assertSame('INSERT INTO products(name, price, stock) VALUES ("product1", 12.5, 5)', $debug[2]['sql']);
        $this->assertGreaterThan(0, $debug[2]['executionMS']);
        $this->assertSame($expectedEndTransactionDebug, $debug[3]['sql']);
        $this->assertGreaterThan(0, $debug[3]['executionMS']);
        $this->assertSame('"START TRANSACTION"', $debug[4]['sql']);
        $this->assertGreaterThan(0, $debug[4]['executionMS']);
        $this->assertSame('INSERT INTO products(name, price, stock) VALUES ("product2", 15.5, 12)', $debug[5]['sql']);
        $this->assertGreaterThan(0, $debug[5]['executionMS']);
        $this->assertSame($expectedEndTransactionDebug, $debug[6]['sql']);
        $this->assertGreaterThan(0, $debug[6]['executionMS']);
    }

    public static function provideExecuteAndEndTransactionMethods(): array
    {
        return [
            'commit and exec' => [
                static fn (Connection $conn, string $sql) => $conn->executeStatement($sql),
                static fn (Connection $conn) => $conn->commit(),
            ],
            'rollback and query' => [
                static fn (Connection $conn, string $sql) => $conn->executeQuery($sql),
                static fn (Connection $conn) => $conn->rollBack(),
            ],
            'prepared statement' => [
                static function (Connection $conn, string $sql): Result {
                    return $conn->prepare($sql)->executeQuery();
                },
                static function (Connection $conn): bool {
                    return $conn->commit();
                },
            ],
        ];
    }

    /**
     * @dataProvider provideExecuteAndEndTransactionMethods
     */
    public function testGlobalDoctrineDuration(callable $sqlMethod, callable $endTransactionMethod)
    {
        $this->init();

        $periods = $this->stopwatch->getEvent('doctrine')->getPeriods();
        $this->assertCount(1, $periods);

        $this->conn->beginTransaction();

        $this->assertFalse($this->stopwatch->getEvent('doctrine')->isStarted());
        $this->assertCount(2, $this->stopwatch->getEvent('doctrine')->getPeriods());

        $sqlMethod($this->conn, 'SELECT * FROM products');

        $this->assertFalse($this->stopwatch->getEvent('doctrine')->isStarted());
        $this->assertCount(3, $this->stopwatch->getEvent('doctrine')->getPeriods());

        $endTransactionMethod($this->conn);

        $this->assertFalse($this->stopwatch->getEvent('doctrine')->isStarted());
        $this->assertCount(4, $this->stopwatch->getEvent('doctrine')->getPeriods());
    }

    /**
     * @dataProvider provideExecuteAndEndTransactionMethods
     */
    public function testWithoutStopwatch(callable $sqlMethod, callable $endTransactionMethod)
    {
        $this->init(false);

        $this->conn->beginTransaction();
        $sqlMethod($this->conn, 'SELECT * FROM products');
        $endTransactionMethod($this->conn);
    }
}
