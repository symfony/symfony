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

class PdoSessionHandlerTest extends \PHPUnit_Framework_TestCase
{
    private $pdo;

    protected function setUp()
    {
        if (!class_exists('PDO') || !in_array('sqlite', \PDO::getAvailableDrivers())) {
            $this->markTestSkipped('This test requires SQLite support in your environment');
        }

        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $sql = 'CREATE TABLE sessions (sess_id VARCHAR(128) PRIMARY KEY, sess_data BLOB, sess_lifetime MEDIUMINT, sess_time INTEGER)';
        $this->pdo->exec($sql);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testWrongPdoErrMode()
    {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);

        $storage = new PdoSessionHandler($this->pdo);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testInexistentTable()
    {
        $storage = new PdoSessionHandler($this->pdo, array('db_table' => 'inexistent_table'));
        $storage->open('', 'sid');
        $storage->read('id');
        $storage->write('id', 'data');
        $storage->close();
    }

    public function testWithLazyDnsConnection()
    {
        $dbFile = tempnam(sys_get_temp_dir(), 'sf2_sqlite_sessions');
        if (file_exists($dbFile)) {
            @unlink($dbFile);
        }

        $pdo = new \PDO('sqlite:' . $dbFile);
        $sql = 'CREATE TABLE sessions (sess_id VARCHAR(128) PRIMARY KEY, sess_data BLOB, sess_lifetime MEDIUMINT, sess_time INTEGER)';
        $pdo->exec($sql);
        $pdo = null;

        $storage = new PdoSessionHandler('sqlite:' . $dbFile);
        $storage->open('', 'sid');
        $data = $storage->read('id');
        $storage->write('id', 'data');
        $storage->close();
        $this->assertSame('', $data, 'New session returns empty string data');

        $storage->open('', 'sid');
        $data = $storage->read('id');
        $storage->close();
        $this->assertSame('data', $data, 'Written value can be read back correctly');

        @unlink($dbFile);
    }

    public function testWithLazySavePathConnection()
    {
        $dbFile = tempnam(sys_get_temp_dir(), 'sf2_sqlite_sessions');
        if (file_exists($dbFile)) {
            @unlink($dbFile);
        }

        $pdo = new \PDO('sqlite:' . $dbFile);
        $sql = 'CREATE TABLE sessions (sess_id VARCHAR(128) PRIMARY KEY, sess_data BLOB, sess_lifetime MEDIUMINT, sess_time INTEGER)';
        $pdo->exec($sql);
        $pdo = null;

        // Open is called with what ini_set('session.save_path', 'sqlite:' . $dbFile) would mean
        $storage = new PdoSessionHandler(null);
        $storage->open('sqlite:' . $dbFile, 'sid');
        $data = $storage->read('id');
        $storage->write('id', 'data');
        $storage->close();
        $this->assertSame('', $data, 'New session returns empty string data');

        $storage->open('sqlite:' . $dbFile, 'sid');
        $data = $storage->read('id');
        $storage->close();
        $this->assertSame('data', $data, 'Written value can be read back correctly');

        @unlink($dbFile);
    }

    public function testReadWriteReadWithNullByte()
    {
        $sessionData = 'da' . "\0" . 'ta';

        $storage = new PdoSessionHandler($this->pdo);
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

    /**
     * Simulates session_regenerate_id(true) which will require an INSERT or UPDATE (replace)
     */
    public function testWriteDifferentSessionIdThanRead()
    {
        $storage = new PdoSessionHandler($this->pdo);
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
        $storage = new PdoSessionHandler($this->pdo);
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
        $storage = new PdoSessionHandler($this->pdo);

        $storage->open('', 'sid');
        $storage->read('id');
        $storage->write('id', 'data');
        $storage->close();
        $this->assertEquals(1, $this->pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn());

        $storage->open('', 'sid');
        $storage->read('id');
        $storage->destroy('id');
        $storage->close();
        $this->assertEquals(0, $this->pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn());

        $storage->open('', 'sid');
        $data = $storage->read('id');
        $storage->close();
        $this->assertSame('', $data, 'Destroyed session returns empty string');
    }

    public function testSessionGC()
    {
        $previousLifeTime = ini_set('session.gc_maxlifetime', 1000);
        $storage = new PdoSessionHandler($this->pdo);

        $storage->open('', 'sid');
        $storage->read('id');
        $storage->write('id', 'data');
        $storage->close();

        $storage->open('', 'sid');
        $storage->read('gc_id');
        ini_set('session.gc_maxlifetime', -1); // test that you can set lifetime of a session after it has been read
        $storage->write('gc_id', 'data');
        $storage->close();
        $this->assertEquals(2, $this->pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn(), 'No session pruned because gc not called');

        $storage->open('', 'sid');
        $data = $storage->read('gc_id');
        $storage->gc(-1);
        $storage->close();

        ini_set('session.gc_maxlifetime', $previousLifeTime);

        $this->assertSame('', $data, 'Session already considered garbage, so not returning data even if it is not pruned yet');
        $this->assertEquals(1, $this->pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn(), 'Expired session is pruned');
    }

    public function testGetConnection()
    {
        $storage = new PdoSessionHandler($this->pdo);

        $method = new \ReflectionMethod($storage, 'getConnection');
        $method->setAccessible(true);

        $this->assertInstanceOf('\PDO', $method->invoke($storage));
    }
}
