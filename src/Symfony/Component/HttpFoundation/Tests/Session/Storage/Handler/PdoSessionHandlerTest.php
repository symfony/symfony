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

use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

/**
 * @requires extension pdo_sqlite
 * @group time-sensitive
 */
class PdoSessionHandlerTest extends \PHPUnit_Framework_TestCase
{
    private $dbFile;

    protected function tearDown()
    {
        // make sure the temporary database file is deleted when it has been created (even when a test fails)
        if ($this->dbFile) {
            @unlink($this->dbFile);
        }
        parent::tearDown();
    }

    protected function getPersistentSqliteDsn()
    {
        $this->dbFile = tempnam(sys_get_temp_dir(), 'sf2_sqlite_sessions');

        return 'sqlite:'.$this->dbFile;
    }

    protected function getPdoMemorySqlite(array $attributes = array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION))
    {
        $pdo = new \PDO('sqlite::memory:');

        foreach ($attributes as $i => $v) {
            $pdo->setAttribute($i, $v);
        }

        return $pdo;
    }

    private function getSessionHandler($pdoOrDsn = null, array $options = array('db_lifetime_col' => false), $createTable = true)
    {
        if (null === $pdoOrDsn) {
            $pdoOrDsn = $this->getPdoMemorySqlite();
        }

        $storage = new PdoSessionHandler($pdoOrDsn, $options);

        if (true === $createTable) {
            $storage->createTable();
        }

        return $storage;
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testWrongPdoErrMode()
    {
        $this->getSessionHandler($this->getPdoMemorySqlite(array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT)));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testNonexistentTable()
    {
        $this->doTestNonexistentTable($this->getSessionHandler(null, array('db_lifetime_col' => false, 'db_table' => 'nonexistent_table'), false));
    }

    /**
     * @group legacy
     * @expectedDeprecation The "%s" column is deprecated since version 3.3 and won't be used anymore in 4.0. Migrate your session database then set the "db_lifetime_col" option to false to opt-in for the new behavior.
     * @expectedException \RuntimeException
     */
    public function testLegacyNonexistentTable()
    {
        $this->doTestNonexistentTable($this->getSessionHandler(null, array('db_lifetime_col' => 'foobar', 'db_table' => 'nonexistent_table'), false));
    }

    private function doTestNonexistentTable(PdoSessionHandler $storage)
    {
        $storage->open('', 'sid');
        $storage->read('id');
        $storage->write('id', 'data');
        $storage->close();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testCreateTableTwice()
    {
        $storage = $this->getSessionHandler();
        $storage->createTable();
    }

    /**
     * @group legacy
     * @expectedException \RuntimeException
     */
    public function testLegacyCreateTableTwice()
    {
        $storage = $this->getSessionHandler();
        $storage->createTable();
    }

    public function testWithLazyDsnConnection()
    {
        $storage = $this->getSessionHandler($this->getPersistentSqliteDsn());
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
        $this->doTestReadWriteReadWithNullByte($this->getSessionHandler(null, array('db_lifetime_col' => false), false));
    }

    /**
     * @group legacy
     * @expectedDeprecation The "%s" column is deprecated since version 3.3 and won't be used anymore in 4.0. Migrate your session database then set the "db_lifetime_col" option to false to opt-in for the new behavior.
     */
    public function testLegacyWithLazySavePathConnection()
    {
        $this->doTestReadWriteReadWithNullByte($this->getSessionHandler(null, array('db_lifetime_col' => 'foobar'), false));
    }

    private function doTestWithLazySavePathConnection(PdoSessionHandler $storage)
    {
        $dsn = $this->getPersistentSqliteDsn();

        // Open is called with what ini_set('session.save_path', $dsn) would mean
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
        $this->doTestReadWriteReadWithNullByte($this->getSessionHandler(null, array('db_lifetime_col' => false), false));
    }

    /**
     * @group legacy
     * @expectedDeprecation The "%s" column is deprecated since version 3.3 and won't be used anymore in 4.0. Migrate your session database then set the "db_lifetime_col" option to false to opt-in for the new behavior.
     */
    public function testLegacyReadWriteReadWithNullByte()
    {
        $this->doTestReadWriteReadWithNullByte($this->getSessionHandler(null, array('db_lifetime_col' => 'foobar'), false));
    }

    private function doTestReadWriteReadWithNullByte(PdoSessionHandler $storage)
    {
        $sessionData = 'da'."\0".'ta';

        $storage->open('', 'sid');
        $storage->createTable();
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
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('PHPUnit_MockObject cannot mock the PDOStatement class on HHVM. See https://github.com/sebastianbergmann/phpunit-mock-objects/pull/289');
        }

        $pdo = new MockPdo('pgsql');
        $pdo->prepareResult = $this->getMockBuilder('PDOStatement')->getMock();

        $content = 'foobar';
        $stream = $this->createStream($content);

        $pdo->prepareResult->expects($this->once())->method('fetchAll')
            ->will($this->returnValue(array(array($stream, 42 + time()))));

        $storage = new PdoSessionHandler($pdo, array('db_lifetime_col' => false));
        $result = $storage->read('foo');

        $this->assertSame($content, $result);
    }

    public function testReadLockedConvertsStreamToString()
    {
        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('PHPUnit_MockObject cannot mock the PDOStatement class on HHVM. See https://github.com/sebastianbergmann/phpunit-mock-objects/pull/289');
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
            ->will($this->returnCallback(function () use (&$exception, $stream) {
                return $exception ? array(array($stream, 42 + time())) : array();
            }));

        $insertStmt->expects($this->once())->method('execute')
            ->will($this->returnCallback(function () use (&$exception) {
                throw $exception = new \PDOException('', '23');
            }));

        $storage = new PdoSessionHandler($pdo, array('db_lifetime_col' => false));
        $result = $storage->read('foo');

        $this->assertSame($content, $result);
    }

    public function testReadingRequiresExactlySameId()
    {
        $this->doTestReadingRequiresExactlySameId($this->getSessionHandler());
    }

    /**
     * @group legacy
     * @expectedDeprecation The "%s" column is deprecated since version 3.3 and won't be used anymore in 4.0. Migrate your session database then set the "db_lifetime_col" option to false to opt-in for the new behavior.
     */
    public function testLegacyReadingRequiresExactlySameId()
    {
        $this->doTestReadingRequiresExactlySameId($this->getSessionHandler(null, array('db_lifetime_col' => 'foobar')));
    }

    private function doTestReadingRequiresExactlySameId(PdoSessionHandler $storage)
    {
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

    public function testWriteDifferentSessionIdThanRead()
    {
        $this->doTestWriteDifferentSessionIdThanRead($this->getSessionHandler());
    }

    /**
     * @group legacy
     * @expectedDeprecation The "%s" column is deprecated since version 3.3 and won't be used anymore in 4.0. Migrate your session database then set the "db_lifetime_col" option to false to opt-in for the new behavior.
     */
    public function testLegacyWriteDifferentSessionIdThanRead()
    {
        $this->doTestWriteDifferentSessionIdThanRead($this->getSessionHandler(null, array('db_lifetime_col' => 'foobar')));
    }

    /**
     * Simulates session_regenerate_id(true) which will require an INSERT or UPDATE (replace).
     */
    private function doTestWriteDifferentSessionIdThanRead(PdoSessionHandler $storage)
    {
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
        $this->doTestWrongUsageStillWorks($this->getSessionHandler());
    }

    /**
     * @group legacy
     * @expectedDeprecation The "%s" column is deprecated since version 3.3 and won't be used anymore in 4.0. Migrate your session database then set the "db_lifetime_col" option to false to opt-in for the new behavior.
     */
    public function testLegacyWrongUsageStillWorks()
    {
        $this->doTestWrongUsageStillWorks($this->getSessionHandler(null, array('db_lifetime_col' => 'foobar')));
    }

    private function doTestWrongUsageStillWorks(PdoSessionHandler $storage)
    {
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
        $storage = $this->getSessionHandler($pdo = $this->getPdoMemorySqlite());

        $this->doTestSessionDestroy($pdo, $storage);
    }

    /**
     * @group legacy
     * @expectedDeprecation The "%s" column is deprecated since version 3.3 and won't be used anymore in 4.0. Migrate your session database then set the "db_lifetime_col" option to false to opt-in for the new behavior.
     */
    public function testLegacySessionDestroy()
    {
        $storage = $this->getSessionHandler($pdo = $this->getPdoMemorySqlite(), array('db_lifetime_col' => 'foobar'));

        $this->doTestSessionDestroy($pdo, $storage);
    }

    private function doTestSessionDestroy(\PDO $pdo, PdoSessionHandler $storage)
    {
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

    public function testSessionGC()
    {
        $storage = $this->getSessionHandler($pdo = $this->getPdoMemorySqlite());

        $this->doTestSessionGC($pdo, $storage);
    }

    /**
     * @group legacy
     * @expectedDeprecation The "%s" column is deprecated since version 3.3 and won't be used anymore in 4.0. Migrate your session database then set the "db_lifetime_col" option to false to opt-in for the new behavior.
     */
    public function testLegacySessionGC()
    {
        $storage = $this->getSessionHandler($pdo = $this->getPdoMemorySqlite(), array('db_lifetime_col' => 'foobar'));

        $this->doTestSessionGC($pdo, $storage);
    }

    private function doTestSessionGC(\PDO $pdo, PdoSessionHandler $storage)
    {
        $previousLifeTime = ini_set('session.gc_maxlifetime', 1000);

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
        $storage = $this->getSessionHandler();

        $method = new \ReflectionMethod($storage, 'getConnection');
        $method->setAccessible(true);

        $this->assertInstanceOf('\PDO', $method->invoke($storage));
    }

    public function testGetConnectionConnectsIfNeeded()
    {
        $storage = new PdoSessionHandler('sqlite::memory:', array('db_lifetime_col' => false));

        $method = new \ReflectionMethod($storage, 'getConnection');
        $method->setAccessible(true);

        $this->assertInstanceOf('\PDO', $method->invoke($storage));
    }

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

    public function __construct($driverName = null, $errorMode = null)
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

    public function prepare($statement, $driverOptions = array())
    {
        return is_callable($this->prepareResult)
            ? call_user_func($this->prepareResult, $statement, $driverOptions)
            : $this->prepareResult;
    }

    public function beginTransaction()
    {
    }

    public function rollBack()
    {
    }
}
