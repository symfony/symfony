<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Handler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

/**
 * @requires extension pdo_sqlite
 * @group time-sensitive
 */
class PdoSessionHandlerTest extends TestCase
{
    private $dbFile;

    protected function tearDown(): void
    {
        // make sure the temporary database file is deleted when it has been created (even when a test fails)
        if ($this->dbFile) {
            @unlink($this->dbFile);
        }
        parent::tearDown();
    }

    protected function getPersistentSqliteDsn()
    {
        $this->dbFile = tempnam(sys_get_temp_dir(), 'sf_sqlite_sessions');

        return 'sqlite:'.$this->dbFile;
    }

    protected function getMemorySqlitePdo()
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $storage = new PdoSessionHandler($pdo);
        $storage->createTable();

        return $pdo;
    }

    public function testWrongPdoErrMode()
    {
        $this->expectException('InvalidArgumentException');
        $pdo = $this->getMemorySqlitePdo();
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);

        new PdoSessionHandler($pdo);
    }

    public function testInexistentTable()
    {
        $this->expectException('RuntimeException');
        $storage = new PdoSessionHandler($this->getMemorySqlitePdo(), ['db_table' => 'inexistent_table']);
        $storage->open('', 'sid');
        $storage->read('id');
        $storage->write('id', 'data');
        $storage->close();
    }

    public function testCreateTableTwice()
    {
        $this->expectException('RuntimeException');
        $storage = new PdoSessionHandler($this->getMemorySqlitePdo());
        $storage->createTable();
    }

    public function testWithLazyDsnConnection()
    {
        $dsn = $this->getPersistentSqliteDsn();

        $storage = new PdoSessionHandler($dsn);
        $storage->createTable();
        $storage->open('', 'sid');
        $data = $storage->read('id');
        $storage->write('id', 'data');
        $storage->close();
        $this->assertSame('', $data, 'New session returns empty string data');

        $storage->open('', 'sid');
        $data = $storage->read('id');
        $storage->close();
        $this->assertSame('data', $data, 'Written value can be read back correctly');
    }

    public function testWithLazySavePathConnection()
    {
        $dsn = $this->getPersistentSqliteDsn();

        // Open is called with what ini_set('session.save_path', $dsn) would mean
        $storage = new PdoSessionHandler(null);
        $storage->open($dsn, 'sid');
        $storage->createTable();
        $data = $storage->read('id');
        $storage->write('id', 'data');
        $storage->close();
        $this->assertSame('', $data, 'New session returns empty string data');

        $storage->open($dsn, 'sid');
        $data = $storage->read('id');
        $storage->close();
        $this->assertSame('data', $data, 'Written value can be read back correctly');
    }

    public function testReadWriteReadWithNullByte()
    {
        $sessionData = 'da'."\0".'ta';

        $storage = new PdoSessionHandler($this->getMemorySqlitePdo());
        $storage->open('', 'sid');
        $readData = $storage->read('id');
        $storage->write('id', $sessionData);
        $storage->close();
        $this->assertSame('', $readData, 'New session returns empty string data');

        $storage->open('', 'sid');
        $readData = $storage->read('id');
        $storage->close();
        $this->assertSame($sessionData, $readData, 'Written value can be read back correctly');
    }

    public function testReadConvertsStreamToString()
    {
        $pdo = new MockPdo('pgsql');
        $pdo->prepareResult = $this->getMockBuilder('PDOStatement')->getMock();

        $content = 'foobar';
        $stream = $this->createStream($content);

        $pdo->prepareResult->expects($this->once())->method('fetchAll')
            ->willReturn([[$stream, 42, time()]]);

        $storage = new PdoSessionHandler($pdo);
        $result = $storage->read('foo');

        $this->assertSame($content, $result);
    }

    public function testReadLockedConvertsStreamToString()
    {
        if (filter_var(ini_get('session.use_strict_mode'), FILTER_VALIDATE_BOOLEAN)) {
            $this->markTestSkipped('Strict mode needs no locking for new sessions.');
        }

        $pdo = new MockPdo('pgsql');
        $selectStmt = $this->getMockBuilder('PDOStatement')->getMock();
        $insertStmt = $this->getMockBuilder('PDOStatement')->getMock();

        $pdo->prepareResult = function ($statement) use ($selectStmt, $insertStmt) {
            return 0 === strpos($statement, 'INSERT') ? $insertStmt : $selectStmt;
        };

        $content = 'foobar';
        $stream = $this->createStream($content);
        $exception = null;

        $selectStmt->expects($this->atLeast(2))->method('fetchAll')
            ->willReturnCallback(function () use (&$exception, $stream) {
                return $exception ? [[$stream, 42, time()]] : [];
            });

        $insertStmt->expects($this->once())->method('execute')
            ->willReturnCallback(function () use (&$exception) {
                throw $exception = new \PDOException('', '23');
            });

        $storage = new PdoSessionHandler($pdo);
        $result = $storage->read('foo');

        $this->assertSame($content, $result);
    }

    public function testReadingRequiresExactlySameId()
    {
        $storage = new PdoSessionHandler($this->getMemorySqlitePdo());
        $storage->open('', 'sid');
        $storage->write('id', 'data');
        $storage->write('test', 'data');
        $storage->write('space ', 'data');
        $storage->close();

        $storage->open('', 'sid');
        $readDataCaseSensitive = $storage->read('ID');
        $readDataNoCharFolding = $storage->read('tést');
        $readDataKeepSpace = $storage->read('space ');
        $readDataExtraSpace = $storage->read('space  ');
        $storage->close();

        $this->assertSame('', $readDataCaseSensitive, 'Retrieval by ID should be case-sensitive (collation setting)');
        $this->assertSame('', $readDataNoCharFolding, 'Retrieval by ID should not do character folding (collation setting)');
        $this->assertSame('data', $readDataKeepSpace, 'Retrieval by ID requires spaces as-is');
        $this->assertSame('', $readDataExtraSpace, 'Retrieval by ID requires spaces as-is');
    }

    /**
     * Simulates session_regenerate_id(true) which will require an INSERT or UPDATE (replace).
     */
    public function testWriteDifferentSessionIdThanRead()
    {
        $storage = new PdoSessionHandler($this->getMemorySqlitePdo());
        $storage->open('', 'sid');
        $storage->read('id');
        $storage->destroy('id');
        $storage->write('new_id', 'data_of_new_session_id');
        $storage->close();

        $storage->open('', 'sid');
        $data = $storage->read('new_id');
        $storage->close();

        $this->assertSame('data_of_new_session_id', $data, 'Data of regenerated session id is available');
    }

    public function testWrongUsageStillWorks()
    {
        // wrong method sequence that should no happen, but still works
        $storage = new PdoSessionHandler($this->getMemorySqlitePdo());
        $storage->write('id', 'data');
        $storage->write('other_id', 'other_data');
        $storage->destroy('inexistent');
        $storage->open('', 'sid');
        $data = $storage->read('id');
        $otherData = $storage->read('other_id');
        $storage->close();

        $this->assertSame('data', $data);
        $this->assertSame('other_data', $otherData);
    }

    public function testSessionDestroy()
    {
        $pdo = $this->getMemorySqlitePdo();
        $storage = new PdoSessionHandler($pdo);

        $storage->open('', 'sid');
        $storage->read('id');
        $storage->write('id', 'data');
        $storage->close();
        $this->assertEquals(1, $pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn());

        $storage->open('', 'sid');
        $storage->read('id');
        $storage->destroy('id');
        $storage->close();
        $this->assertEquals(0, $pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn());

        $storage->open('', 'sid');
        $data = $storage->read('id');
        $storage->close();
        $this->assertSame('', $data, 'Destroyed session returns empty string');
    }

    /**
     * @runInSeparateProcess
     */
    public function testSessionGC()
    {
        $previousLifeTime = ini_set('session.gc_maxlifetime', 1000);
        $pdo = $this->getMemorySqlitePdo();
        $storage = new PdoSessionHandler($pdo);

        $storage->open('', 'sid');
        $storage->read('id');
        $storage->write('id', 'data');
        $storage->close();

        $storage->open('', 'sid');
        $storage->read('gc_id');
        ini_set('session.gc_maxlifetime', -1); // test that you can set lifetime of a session after it has been read
        $storage->write('gc_id', 'data');
        $storage->close();
        $this->assertEquals(2, $pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn(), 'No session pruned because gc not called');

        $storage->open('', 'sid');
        $data = $storage->read('gc_id');
        $storage->gc(-1);
        $storage->close();

        ini_set('session.gc_maxlifetime', $previousLifeTime);

        $this->assertSame('', $data, 'Session already considered garbage, so not returning data even if it is not pruned yet');
        $this->assertEquals(1, $pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn(), 'Expired session is pruned');
    }

    public function testGetConnection()
    {
        $storage = new PdoSessionHandler($this->getMemorySqlitePdo());

        $method = new \ReflectionMethod($storage, 'getConnection');
        $method->setAccessible(true);

        $this->assertInstanceOf('\PDO', $method->invoke($storage));
    }

    public function testGetConnectionConnectsIfNeeded()
    {
        $storage = new PdoSessionHandler('sqlite::memory:');

        $method = new \ReflectionMethod($storage, 'getConnection');
        $method->setAccessible(true);

        $this->assertInstanceOf('\PDO', $method->invoke($storage));
    }

    /**
     * @dataProvider provideUrlDsnPairs
     */
    public function testUrlDsn($url, $expectedDsn, $expectedUser = null, $expectedPassword = null)
    {
        $storage = new PdoSessionHandler($url);
        $reflection = new \ReflectionClass(PdoSessionHandler::class);

        foreach (['dsn' => $expectedDsn, 'username' => $expectedUser, 'password' => $expectedPassword] as $property => $expectedValue) {
            if (!isset($expectedValue)) {
                continue;
            }
            $property = $reflection->getProperty($property);
            $property->setAccessible(true);
            $this->assertSame($expectedValue, $property->getValue($storage));
        }
    }

    public function provideUrlDsnPairs()
    {
        yield ['mysql://localhost/test', 'mysql:host=localhost;dbname=test;'];
        yield ['mysql://localhost:56/test', 'mysql:host=localhost;port=56;dbname=test;'];
        yield ['mysql2://root:pwd@localhost/test', 'mysql:host=localhost;dbname=test;', 'root', 'pwd'];
        yield ['postgres://localhost/test', 'pgsql:host=localhost;dbname=test;'];
        yield ['postgresql://localhost:5634/test', 'pgsql:host=localhost;port=5634;dbname=test;'];
        yield ['postgres://root:pwd@localhost/test', 'pgsql:host=localhost;dbname=test;', 'root', 'pwd'];
        yield 'sqlite relative path' => ['sqlite://localhost/tmp/test', 'sqlite:tmp/test'];
        yield 'sqlite absolute path' => ['sqlite://localhost//tmp/test', 'sqlite:/tmp/test'];
        yield 'sqlite relative path without host' => ['sqlite:///tmp/test', 'sqlite:tmp/test'];
        yield 'sqlite absolute path without host' => ['sqlite3:////tmp/test', 'sqlite:/tmp/test'];
        yield ['sqlite://localhost/:memory:', 'sqlite::memory:'];
        yield ['mssql://localhost/test', 'sqlsrv:server=localhost;Database=test'];
        yield ['mssql://localhost:56/test', 'sqlsrv:server=localhost,56;Database=test'];
    }

    /**
     * @return resource
     */
    private function createStream($content)
    {
        $stream = tmpfile();
        fwrite($stream, $content);
        fseek($stream, 0);

        return $stream;
    }
}

class MockPdo extends \PDO
{
    public $prepareResult;
    private $driverName;
    private $errorMode;

    public function __construct(string $driverName = null, int $errorMode = null)
    {
        $this->driverName = $driverName;
        $this->errorMode = null !== $errorMode ?: \PDO::ERRMODE_EXCEPTION;
    }

    public function getAttribute($attribute)
    {
        if (\PDO::ATTR_ERRMODE === $attribute) {
            return $this->errorMode;
        }

        if (\PDO::ATTR_DRIVER_NAME === $attribute) {
            return $this->driverName;
        }

        return parent::getAttribute($attribute);
    }

    public function prepare($statement, $driverOptions = [])
    {
        return \is_callable($this->prepareResult)
            ? ($this->prepareResult)($statement, $driverOptions)
            : $this->prepareResult;
    }

    public function beginTransaction()
    {
    }

    public function rollBack()
    {
    }
}
