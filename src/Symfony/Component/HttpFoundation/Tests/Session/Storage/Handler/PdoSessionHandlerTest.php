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
        $sql = 'CREATE TABLE sessions (sess_id VARCHAR(128) PRIMARY KEY, sess_data TEXT, sess_time INTEGER)';
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

    public function testReadWriteRead()
    {
        $storage = new PdoSessionHandler($this->pdo);
        $storage->open('', 'sid');
        $this->assertSame('', $storage->read('id'), 'New session returns empty string data');
        $storage->write('id', 'data');
        $storage->close();

        $storage->open('', 'sid');
        $this->assertSame('data', $storage->read('id'), 'Written value can be read back correctly');
        $storage->close();
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
        $this->assertSame('data_of_new_session_id', $storage->read('new_id'), 'Data of regenerated session id is available');
        $storage->close();
    }

    /**
     * @expectedException \BadMethodCallException
     */
    public function testWrongUsage()
    {
        $storage = new PdoSessionHandler($this->pdo);
        $storage->open('', 'sid');
        $storage->read('id');
        $storage->read('id');
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
        $this->assertSame('', $storage->read('id'), 'Destroyed session returns empty string');
        $storage->close();
    }

    public function testSessionGC()
    {
        $previousLifeTime = ini_set('session.gc_maxlifetime', 0);
        $storage = new PdoSessionHandler($this->pdo);

        $storage->open('', 'sid');
        $storage->read('id');
        $storage->write('id', 'data');
        $storage->close();
        $this->assertEquals(1, $this->pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn());

        $storage->open('', 'sid');
        $this->assertSame('', $storage->read('id'), 'Session already considered garbage, so not returning data even if it is not pruned yet');
        $storage->gc(0);
        $storage->close();
        $this->assertEquals(0, $this->pdo->query('SELECT COUNT(*) FROM sessions')->fetchColumn());

        ini_set('session.gc_maxlifetime', $previousLifeTime);
    }

    public function testGetConnection()
    {
        $storage = new PdoSessionHandler($this->pdo, array('db_table' => 'sessions'), array());

        $method = new \ReflectionMethod($storage, 'getConnection');
        $method->setAccessible(true);

        $this->assertInstanceOf('\PDO', $method->invoke($storage));
    }
}
