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

use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use MongoDB\Driver\Exception\CommandException;
use MongoDB\Driver\Exception\ConnectionException;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\MongoDbSessionHandler;

require_once __DIR__.'/stubs/mongodb.php';

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 *
 * @group integration
 * @group time-sensitive
 *
 * @requires extension mongodb
 */
class MongoDbSessionHandlerTest extends TestCase
{
    private const DABASE_NAME = 'sf-test';
    private const COLLECTION_NAME = 'session-test';

    public array $options;
    private Manager $manager;
    private MongoDbSessionHandler $storage;

    protected function setUp(): void
    {
        $this->manager = new Manager('mongodb://'.getenv('MONGODB_HOST'));

        try {
            $this->manager->executeCommand(self::DABASE_NAME, new Command(['ping' => 1]));
        } catch (ConnectionException $e) {
            $this->markTestSkipped(\sprintf('MongoDB Server "%s" not running: %s', getenv('MONGODB_HOST'), $e->getMessage()));
        }

        $this->options = [
            'id_field' => '_id',
            'data_field' => 'data',
            'time_field' => 'time',
            'expiry_field' => 'expires_at',
            'database' => self::DABASE_NAME,
            'collection' => self::COLLECTION_NAME,
        ];

        $this->storage = new MongoDbSessionHandler($this->manager, $this->options);
    }

    public function testCreateFromClient()
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('getManager')
            ->willReturn($this->manager);

        $this->storage = new MongoDbSessionHandler($client, $this->options);
        $this->storage->write('foo', 'bar');

        $this->assertCount(1, $this->getSessions());
    }

    protected function tearDown(): void
    {
        try {
            $this->manager->executeCommand(self::DABASE_NAME, new Command(['drop' => self::COLLECTION_NAME]));
        } catch (CommandException $e) {
            // The server may return a NamespaceNotFound error if the collection does not exist
            if (26 !== $e->getCode()) {
                throw $e;
            }
        }
    }

    /** @dataProvider provideInvalidOptions */
    public function testConstructorShouldThrowExceptionForMissingOptions(array $options)
    {
        $this->expectException(\InvalidArgumentException::class);
        new MongoDbSessionHandler($this->manager, $options);
    }

    public static function provideInvalidOptions(): iterable
    {
        yield 'empty' => [[]];
        yield 'collection missing' => [['database' => 'foo']];
        yield 'database missing' => [['collection' => 'foo']];
    }

    public function testOpenMethodAlwaysReturnTrue()
    {
        $this->assertTrue($this->storage->open('test', 'test'), 'The "open" method should always return true');
    }

    public function testCloseMethodAlwaysReturnTrue()
    {
        $this->assertTrue($this->storage->close(), 'The "close" method should always return true');
    }

    public function testRead()
    {
        $this->insertSession('foo', 'bar', 0);
        $this->assertEquals('bar', $this->storage->read('foo'));
    }

    public function testReadNotFound()
    {
        $this->insertSession('foo', 'bar', 0);
        $this->assertEquals('', $this->storage->read('foobar'));
    }

    public function testReadExpired()
    {
        $this->insertSession('foo', 'bar', -100_000);
        $this->assertEquals('', $this->storage->read('foo'));
    }

    public function testWrite()
    {
        $expectedTime = (new \DateTimeImmutable())->getTimestamp();
        $expectedExpiry = $expectedTime + (int) \ini_get('session.gc_maxlifetime');
        $this->assertTrue($this->storage->write('foo', 'bar'));

        $sessions = $this->getSessions();
        $this->assertCount(1, $sessions);
        $this->assertEquals('foo', $sessions[0]->_id);
        $this->assertInstanceOf(Binary::class, $sessions[0]->data);
        $this->assertEquals('bar', $sessions[0]->data->getData());
        $this->assertInstanceOf(UTCDateTime::class, $sessions[0]->time);
        $this->assertGreaterThanOrEqual($expectedTime, round((string) $sessions[0]->time / 1000));
        $this->assertInstanceOf(UTCDateTime::class, $sessions[0]->expires_at);
        $this->assertGreaterThanOrEqual($expectedExpiry, round((string) $sessions[0]->expires_at / 1000));
    }

    public function testReplaceSessionData()
    {
        $this->storage->write('foo', 'bar');
        $this->storage->write('baz', 'qux');
        $this->storage->write('foo', 'foobar');

        $sessions = $this->getSessions();
        $this->assertCount(2, $sessions);
        $this->assertEquals('foobar', $sessions[0]->data->getData());
    }

    public function testDestroy()
    {
        $this->storage->write('foo', 'bar');
        $this->storage->write('baz', 'qux');

        $this->storage->open('test', 'test');

        $this->assertTrue($this->storage->destroy('foo'));

        $sessions = $this->getSessions();
        $this->assertCount(1, $sessions);
        $this->assertEquals('baz', $sessions[0]->_id);
    }

    public function testGc()
    {
        $this->insertSession('foo', 'bar', -100_000);
        $this->insertSession('bar', 'bar', -100_000);
        $this->insertSession('qux', 'bar', -300);
        $this->insertSession('baz', 'bar', 0);

        $this->assertSame(2, $this->storage->gc(1));
        $this->assertCount(2, $this->getSessions());
    }

    /**
     * @return list<object{_id:string,data:Binary,time:UTCDateTime,expires_at:UTCDateTime>
     */
    private function getSessions(): array
    {
        return $this->manager->executeQuery(self::DABASE_NAME.'.'.self::COLLECTION_NAME, new Query([]))->toArray();
    }

    private function insertSession(string $sessionId, string $data, int $timeDiff): void
    {
        $time = time() + $timeDiff;

        $write = new BulkWrite();
        $write->insert([
            '_id' => $sessionId,
            'data' => new Binary($data, Binary::TYPE_GENERIC),
            'time' => new UTCDateTime($time * 1000),
            'expires_at' => new UTCDateTime(($time + (int) \ini_get('session.gc_maxlifetime')) * 1000),
        ]);

        $this->manager->executeBulkWrite(self::DABASE_NAME.'.'.self::COLLECTION_NAME, $write);
    }
}
