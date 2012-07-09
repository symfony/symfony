<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation\SessionStorage;

use Symfony\Component\HttpFoundation\SessionStorage\PdoSessionStorage;

class PdoSessionStorageTest extends \PHPUnit_Framework_TestCase
{
    private $pdo;

    protected function setUp()
    {
        $this->pdo = new \PDO("sqlite::memory:");
        $sql = "CREATE TABLE sessions (sess_id VARCHAR(255) PRIMARY KEY, sess_data TEXT, sess_time INTEGER)";
        $this->pdo->exec($sql);
    }

    public function testMultipleInstances()
    {
        $storage1 = new PdoSessionStorage($this->pdo, array(), array('db_table' => 'sessions'));
        $storage1->sessionWrite('foo', 'bar');

        $storage2 = new PdoSessionStorage($this->pdo, array(), array('db_table' => 'sessions'));
        $this->assertEquals('bar', $storage2->sessionRead('foo'), 'values persist between instances');
    }

    public function testSessionDestroy()
    {
        $storage = new PdoSessionStorage($this->pdo, array(), array('db_table' => 'sessions'));
        $storage->sessionWrite('foo', 'bar');
        $this->assertEquals(1, count($this->pdo->query('SELECT * FROM sessions')->fetchAll()));

        $storage->sessionDestroy('foo');

        $this->assertEquals(0, count($this->pdo->query('SELECT * FROM sessions')->fetchAll()));
    }

    public function testSessionGC()
    {
        $storage = new PdoSessionStorage($this->pdo, array(), array('db_table' => 'sessions'));

        $storage->sessionWrite('foo', 'bar');
        $storage->sessionWrite('baz', 'bar');

        $this->assertEquals(2, count($this->pdo->query('SELECT * FROM sessions')->fetchAll()));

        $storage->sessionGC(-1);
        $this->assertEquals(0, count($this->pdo->query('SELECT * FROM sessions')->fetchAll()));
    }
}
