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

        $this->pdo = new \PDO("sqlite::memory:");
        $sql = "CREATE TABLE sessions (sess_id VARCHAR(255) PRIMARY KEY, sess_data TEXT, sess_time INTEGER)";
        $this->pdo->exec($sql);
    }

    public function testMultipleInstances()
    {
        $storage1 = new PdoSessionHandler($this->pdo, array('db_table' => 'sessions'), array());
        $storage1->write('foo', 'bar');

        $storage2 = new PdoSessionHandler($this->pdo, array('db_table' => 'sessions'), array());
        $this->assertEquals('bar', $storage2->read('foo'), 'values persist between instances');
    }

    public function testSessionDestroy()
    {
        $storage = new PdoSessionHandler($this->pdo, array('db_table' => 'sessions'), array());
        $storage->write('foo', 'bar');
        $this->assertEquals(1, count($this->pdo->query('SELECT * FROM sessions')->fetchAll()));

        $storage->destroy('foo');

        $this->assertEquals(0, count($this->pdo->query('SELECT * FROM sessions')->fetchAll()));
    }

    public function testSessionGC()
    {
        $storage = new PdoSessionHandler($this->pdo, array('db_table' => 'sessions'), array());

        $storage->write('foo', 'bar');
        $storage->write('baz', 'bar');

        $this->assertEquals(2, count($this->pdo->query('SELECT * FROM sessions')->fetchAll()));

        $storage->gc(-1);
        $this->assertEquals(0, count($this->pdo->query('SELECT * FROM sessions')->fetchAll()));
    }
}
