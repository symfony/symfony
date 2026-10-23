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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\MongoDbSessionHandler;

require_once __DIR__.'/stubs/mongodb.php';

/**
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
#[RequiresPhpExtension('mongodb')]
#[Group('integration')]
#[Group('time-sensitive')]
class MongoDbSessionHandlerTest extends TestCase
{
    private const DABASE_NAME = 'sf-test';
    private const COLLECTION_NAME = 'session-test';

    public array $options;
    private Manager $manager;
    private MongoDbSessionHandler $storage;

    protected function setUp(): void
    {
        $this->manager = new Manager(getenv('MONGODB_URI'));

        try {
            $this->manager->executeCommand(self::DABASE_NAME, new Command(['ping' => 1]));
        } catch (ConnectionException $e) {
            $this->markTestSkipped(\sprintf('MongoDB Server "%s" not running: %s', getenv('MONGODB_URI'), $e->getMessage()));
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

    #[DataProvider('provideInvalidOptions')]
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

    #[DataProvider('provideCreateFromDsn')]
    public function testCreateFromDsn(string $dsn, string $expectedNamespace, ?int $expectedTtl = null)
    {
        $storage = MongoDbSessionHandler::createFromDsn($dsn);
        $reflection = new \ReflectionObject($storage);

        $this->assertSame($expectedNamespace, $reflection->getProperty('namespace')->getValue($storage));
        if (null !== $expectedTtl) {
            $this->assertSame($expectedTtl, $reflection->getProperty('ttl')->getValue($storage));
        }
    }

    public static function provideCreateFromDsn(): iterable
    {
        yield 'bare DSN' => ['mongodb://localhost', 'app.sessions'];
        yield 'path and TTL' => ['mongodb://localhost/app?collection=sessions&ttl=3600&retryWrites=true', 'app.sessions', 3600];
        yield 'database from the query' => ['mongodb://localhost?database=app&collection=my_sessions', 'app.my_sessions'];
        yield 'multiple hosts with ports' => ['mongodb://host1:27017,host2:27017/app?collection=sessions', 'app.sessions'];
        yield 'multiple hosts without ports' => ['mongodb://host1,host2/app', 'app.sessions'];
        yield 'multiple hosts with credentials' => ['mongodb://user:pass@host1:27017,host2:27017/app', 'app.sessions'];
        yield 'question mark in a value' => ['mongodb://localhost/app?collection=sessions&appName=a?b', 'app.sessions'];
        yield 'path takes precedence over the query' => ['mongodb://localhost/path_db?database=query_db', 'path_db.sessions'];
        yield 'repeated keys use the last value' => ['mongodb://localhost/app?collection=a&collection=b', 'app.b'];
        yield 'percent-encoded database in the path' => ['mongodb://localhost/my%2Ddb', 'my-db.sessions'];
        yield 'database named zero' => ['mongodb://localhost/0', '0.sessions'];
    }

    #[DataProvider('provideEmptyOptions')]
    public function testEmptyOptionsAreRejected(string $dsn, array $options)
    {
        $this->expectException(\InvalidArgumentException::class);

        MongoDbSessionHandler::createFromDsn($dsn, $options);
    }

    #[DataProvider('provideInvalidDsn')]
    public function testInvalidDsnIsRejected(string $dsn, string $expectedMessage)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        MongoDbSessionHandler::createFromDsn($dsn);
    }

    public static function provideInvalidDsn(): iterable
    {
        yield 'wrong scheme' => ['redis://localhost', 'Expecting "mongodb://" or "mongodb+srv://".'];
        yield 'last host without port' => ['mongodb://host1:27017,host2/app', 'The given MongoDB DSN is invalid.'];
        yield 'question mark in user information' => ['mongodb://user:p?ss@localhost/app', 'The given MongoDB DSN is invalid.'];
    }

    public static function provideEmptyOptions(): iterable
    {
        yield 'empty collection in the DSN' => ['mongodb://localhost/app?collection=', []];
        yield 'empty database in the DSN' => ['mongodb://localhost/app?database=', []];
        yield 'collection without value in the DSN' => ['mongodb://localhost/app?collection', []];
        yield 'empty collection option' => ['mongodb://localhost/app', ['collection' => '']];
        yield 'empty database option' => ['mongodb://localhost/app', ['database' => '']];
    }

    public function testInvalidTtlIsRejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "ttl" option must be an integer or a Closure.');

        MongoDbSessionHandler::createFromDsn('mongodb://localhost/app?ttl=abc');
    }

    public function testInvalidUriIsReportedByTheDriver()
    {
        $this->expectException(\MongoDB\Driver\Exception\InvalidArgumentException::class);

        MongoDbSessionHandler::createFromDsn('mongodb://localhost/app?retryWrites=maybe');
    }

    #[DataProvider('provideDsnOptionsStripping')]
    public function testDsnOptionsAreStripped(string $dsn, string $expected)
    {
        $this->assertSame($expected, (new \ReflectionMethod(MongoDbSessionHandler::class, 'parseDsn'))->invoke(null, $dsn)[0]);
    }

    public static function provideDsnOptionsStripping(): iterable
    {
        yield 'driver options are kept in order' => [
            'mongodb://localhost/app?retryWrites=true&w=majority',
            'mongodb://localhost/app?retryWrites=true&w=majority',
        ];

        yield 'Symfony options are removed from the middle' => [
            'mongodb://localhost/app?retryWrites=true&ttl=3600&w=majority',
            'mongodb://localhost/app?retryWrites=true&w=majority',
        ];

        yield 'all Symfony options are removed' => [
            'mongodb://localhost/app?collection=sessions&id_field=_id&expiry_field=eol',
            'mongodb://localhost/app',
        ];

        yield 'repeated Symfony options are all removed' => [
            'mongodb://localhost/app?collection=a&ttl=1&collection=b&w=1',
            'mongodb://localhost/app?w=1',
        ];

        yield 'repeated driver options are kept in order' => [
            'mongodb://localhost/app?readPreferenceTags=dc:ny&collection=sessions&readPreferenceTags=dc:sf',
            'mongodb://localhost/app?readPreferenceTags=dc:ny&readPreferenceTags=dc:sf',
        ];

        yield 'encoded ampersand in a value is kept' => [
            'mongodb://localhost/app?appName=my%26app&collection=sessions&w=majority',
            'mongodb://localhost/app?appName=my%26app&w=majority',
        ];

        yield 'equals sign in a value is kept' => [
            'mongodb://localhost/app?appName=a=b&collection=sessions',
            'mongodb://localhost/app?appName=a=b',
        ];
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

    public function testUpdateTimestampWithEmptyDataDoesNotReviveExpiredSession()
    {
        $this->insertSession('foo', 'bar', -100_000);

        $this->storage->open('test', 'test');
        $this->assertSame('', $this->storage->read('foo'));

        $this->assertTrue($this->storage->updateTimestamp('foo', ''));
        $this->assertSame('', $this->storage->read('foo'));
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
