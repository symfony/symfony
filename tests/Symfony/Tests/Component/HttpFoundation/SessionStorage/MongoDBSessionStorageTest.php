<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation\SessionStorage;

use Symfony\Component\HttpFoundation\SessionStorage\MongoDBSessionStorage;

/**
 * MongoDBSessionStorage.
 *
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 */
class MongoDBSessionStorageTest extends \PHPUnit_Framework_TestCase
{
    private $storage;
    private $db;
    private $collection = 'session';

    protected function setUp()
    {
        if (!class_exists('Mongo')) {
            $this->markTestSkipped('Mongo extension is disabled');
        }

        try {
            $mongo  = new \Mongo();
        } catch (\MongoConnectionException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $this->db = $mongo->selectDB('test');
        $this->storage = new MongoDBSessionStorage($this->db, array(
            'collection' => $this->collection,
        ));

        $this->db->selectCollection($this->collection)->drop();
    }

    public function testOpen()
    {
        $this->assertTrue($this->storage->sessionOpen());
    }

    public function testClose()
    {
        $this->assertTrue($this->storage->sessionClose());
    }

    public function testDestroy()
    {
        $id         = '112';
        $collection = $this->db->selectCollection($this->collection);

        $collection->insert(array(
            'sess_id' => $id
        ));

        $this->assertNotNull($collection->findOne(array(
            'sess_id' => $id
        )));

        $this->storage->sessionDestroy($id);

        $this->assertNull($collection->findOne(array(
            'sess_id' => $id
        )));
    }

    public function testGC()
    {
        $collection = $this->db->selectCollection($this->collection);
        $lifetime   = 3600;
        $sessions   = array();
        $oldSessCnt = 0;

        for ($i = 0; $i < 20; $i++) {
            $session = array(
                'sess_id'   => (string) $i,
                'sess_data' => 'some data',
                'sess_time' => new \MongoDate(time() - rand(0, $lifetime * 2)),
            );

            if ($session['sess_time']->sec < time() - $lifetime) {
                $oldSessCnt++;
            }

            $sessions[] = $session;
        }

        $collection->batchInsert($sessions);

        $this->assertEquals(20, $collection->find(array())->count());

        $this->assertTrue($this->storage->sessionGC($lifetime));

        $this->assertEquals(20 - $oldSessCnt, $collection->find(array())->count());
    }

    public function testReadNonExistentSession()
    {
        $id = '123';

        $collection = $this->db->selectCollection($this->collection);

        $this->assertEquals(null, $collection->findOne(array('sess_id' => $id)));

        $this->assertEquals('', $this->storage->sessionRead($id));

        $session = $collection->findOne(array('sess_id' => $id));
        $this->assertNotNull($session);
        $this->assertEquals($id, $session['sess_id']);
        $this->assertEquals('', $session['sess_data']);
        $this->assertInstanceOf('MongoDate', $session['sess_time']);
        $this->assertEquals(0, round(time() - $session['sess_time']->sec));
    }

    public function testReadExistingSession()
    {
        $id = '123';
        $data = 'some session data';

        $this->db->selectCollection($this->collection)->insert(array(
            'sess_id'   => $id,
            'sess_data' => $data,
            'sess_time' => new \MongoDate(time() - 300),
        ));

        $this->assertEquals($data, $this->storage->sessionRead($id));
    }

    public function testWrite()
    {
        $id         = '123';
        $data       = 'some session data';
        $collection = $this->db->selectCollection($this->collection);

        $this->assertTrue($this->storage->sessionWrite($id, $data));

        $this->assertNull($session = $collection->findOne(array('sess_id' => $id)));

        $collection->insert(array(
            'sess_id'   => $id,
            'sess_data' => '',
            'sess_time' => new \MongoDate(),
        ));

        $this->assertNotNull($collection->findOne(array('sess_id' => $id)));

        $this->assertTrue($this->storage->sessionWrite($id, $data));

        $session = $collection->findOne(array('sess_id' => $id));

        $this->assertNotNull($session);
        $this->assertEquals($data, $session['sess_data']);
        $this->assertInstanceOf('MongoDate', $session['sess_time']);
        $this->assertEquals(0, round(time() - $session['sess_time']->sec));
    }
}
