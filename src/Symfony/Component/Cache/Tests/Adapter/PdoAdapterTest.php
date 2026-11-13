<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Adapter;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

#[RequiresPhpExtension('pdo_sqlite')]
#[Group('time-sensitive')]
class PdoAdapterTest extends AbstractPdoAdapterTestCase
{
    public function createCachePool(int $defaultLifetime = 0): CacheItemPoolInterface
    {
        return new PdoAdapter('sqlite:'.self::$dbFile, 'ns', $defaultLifetime);
    }

    public function testWalDsnUsesTheDatabaseFileAndNormalSynchronousMode()
    {
        $file = tempnam(sys_get_temp_dir(), 'sf_sqlite_cache');
        @unlink($file);

        try {
            $cache = new PdoAdapter('sqlite:'.$file.'?wal=1');
            $this->assertTrue($cache->save($cache->getItem('key')->set('value')));

            $connection = (new \ReflectionMethod($cache, 'getConnection'))->invoke($cache);
            $this->assertSame('wal', $connection->query('PRAGMA journal_mode')->fetchColumn());
            $this->assertSame(1, (int) $connection->query('PRAGMA synchronous')->fetchColumn());
            $this->assertFileExists($file);
            $this->assertFileDoesNotExist($file.'?wal=1');

            $cache->reset(true);
            $connection = (new \ReflectionMethod($cache, 'getConnection'))->invoke($cache);
            $this->assertSame(1, (int) $connection->query('PRAGMA synchronous')->fetchColumn());
        } finally {
            unset($connection, $cache);
            @unlink($file);
            @unlink($file.'?wal=1');
        }
    }

    public function testWalDsnPreservesSqliteUriOptions()
    {
        $file = tempnam(sys_get_temp_dir(), 'sf_sqlite_cache');
        @unlink($file);

        try {
            $uriPath = str_replace('\\', '/', $file);
            $cache = new PdoAdapter('sqlite:file:'.('/' === $uriPath[0] ? '' : '/').$uriPath.'?mode=rwc&wal=1');
            $this->assertTrue($cache->save($cache->getItem('key')->set('value')));
            $this->assertFileExists($file);
            $this->assertSame('wal', (new \ReflectionMethod($cache, 'getConnection'))->invoke($cache)->query('PRAGMA journal_mode')->fetchColumn());
        } finally {
            unset($cache);
            @unlink($file);
        }
    }

    public function testEagerWalConnection()
    {
        $file = tempnam(sys_get_temp_dir(), 'sf_sqlite_cache');
        @unlink($file);

        try {
            $connection = PdoAdapter::createConnection('sqlite:'.$file.'?wal=1', ['lazy' => false]);
            $this->assertInstanceOf(\PDO::class, $connection);
            $this->assertSame('wal', $connection->query('PRAGMA journal_mode')->fetchColumn());
            $this->assertSame(1, (int) $connection->query('PRAGMA synchronous')->fetchColumn());
            $this->assertFileExists($file);
        } finally {
            unset($connection);
            @unlink($file);
            @unlink($file.'?wal=1');
        }
    }

    public function testInvalidWalDsnOptionIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('wal');

        new PdoAdapter('sqlite::memory:?wal=maybe');
    }

    public function testWalOffKeepsTheDefaultJournalMode()
    {
        $file = tempnam(sys_get_temp_dir(), 'sf_sqlite_cache');
        @unlink($file);

        try {
            $baseline = new \PDO('sqlite:'.$file);
            $expectedSynchronous = (int) $baseline->query('PRAGMA synchronous')->fetchColumn();
            unset($baseline);

            $cache = new PdoAdapter('sqlite:'.$file.'?wal=0');
            $this->assertTrue($cache->save($cache->getItem('key')->set('value')));

            $connection = (new \ReflectionMethod($cache, 'getConnection'))->invoke($cache);
            $this->assertSame('delete', $connection->query('PRAGMA journal_mode')->fetchColumn());
            $this->assertSame($expectedSynchronous, (int) $connection->query('PRAGMA synchronous')->fetchColumn());
            $this->assertFileExists($file);
            $this->assertFileDoesNotExist($file.'?wal=0');
        } finally {
            unset($connection, $cache);
            @unlink($file);
            @unlink($file.'?wal=0');
        }
    }

    public function testWalCannotBeEnabledForAnInMemoryDatabase()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('WAL');

        PdoAdapter::createConnection('sqlite::memory:?wal=1', ['lazy' => false]);
    }

    public function testProvidedConnectionKeepsItsSynchronousMode()
    {
        $file = tempnam(sys_get_temp_dir(), 'sf_sqlite_cache');
        try {
            $connection = new \PDO('sqlite:'.$file);
            $connection->exec('PRAGMA journal_mode=WAL');
            $connection->exec('PRAGMA synchronous=FULL');
            $cache = new PdoAdapter($connection);
            $this->assertTrue($cache->save($cache->getItem('key')->set('value')));
            $this->assertSame(2, (int) $connection->query('PRAGMA synchronous')->fetchColumn());
        } finally {
            unset($connection, $cache);
            @unlink($file);
        }
    }

    public function testShutdownResetKeepsAConnectionPassedToTheConstructor()
    {
        $cache = new PdoAdapter($pdo = new \PDO('sqlite:'.self::$dbFile));
        $cache->reset(true);

        $this->assertSame($pdo, (new \ReflectionMethod($cache, 'getConnection'))->invoke($cache));
    }

    public function testOptionsAreNotExposedInTrace()
    {
        if (!class_exists(\SensitiveParameterValue::class)) {
            $this->markTestSkipped('Sensitive parameter support requires PHP 8.2.');
        }

        $previous = ini_set('zend.exception_ignore_args', '0');
        try {
            new PdoAdapter('mysql://localhost/db', '', 0, ['db_password' => 's3cr3t']);
            $this->fail('An exception should have been thrown.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringNotContainsString('s3cr3t', json_encode($e->getTrace(), \JSON_THROW_ON_ERROR));
        } finally {
            ini_set('zend.exception_ignore_args', $previous);
        }
    }
}
