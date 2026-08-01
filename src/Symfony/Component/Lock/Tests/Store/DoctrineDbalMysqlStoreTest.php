<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Tests\Store;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\DBAL\Tools\DsnParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\DoctrineDbalMysqlStore;
use Symfony\Component\Lock\Test\AbstractStoreTestCase;

/**
 * @author Ryan Pon <me@ryanpon.com>
 */
#[RequiresPhpExtension('pdo_mysql')]
#[Group('integration')]
class DoctrineDbalMysqlStoreTest extends AbstractStoreTestCase
{
    use BlockingStoreTestTrait;

    public function createMysqlConnection(array $driverOptions = []): Connection
    {
        return self::getDbalConnection(self::getMysqlDsn('pdo-mysql'), $driverOptions);
    }

    public function getStore(): PersistingStoreInterface
    {
        $conn = $this->createMysqlConnection();

        return new DoctrineDbalMysqlStore($conn);
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    #[DataProvider('getInvalidDrivers')]
    public function testInvalidDriver($connOrDsn)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The adapter "Symfony\Component\Lock\Store\DoctrineDbalMysqlStore" does not support');

        $store = new DoctrineDbalMysqlStore($connOrDsn);
        $store->exists(new Key('foo'));
    }

    public static function getInvalidDrivers()
    {
        yield ['sqlite:///tmp/foo.db'];
        yield [self::getDbalConnection('sqlite:///tmp/foo.db')];
    }

    #[DataProvider('getSupportedServerVersions')]
    public function testSupportedPlatforms(string $serverVersion)
    {
        $this->expectNotToPerformAssertions();

        new DoctrineDbalMysqlStore(DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => $serverVersion]));
    }

    public static function getSupportedServerVersions()
    {
        yield 'MySQL' => ['8.0.0'];
        yield 'MariaDB' => ['10.6.0-MariaDB'];
    }

    public function testSaveAfterConflict()
    {
        $store1 = $this->getStore();
        $store2 = $this->getStore();

        $key = new Key(__METHOD__);

        $store1->save($key);
        $this->assertTrue($store1->exists($key));

        $lockConflicted = false;
        try {
            $store2->save($key);
        } catch (LockConflictedException $lockConflictedException) {
            $lockConflicted = true;
        }

        $this->assertTrue($lockConflicted);
        $this->assertFalse($store2->exists($key));

        $store1->delete($key);

        $store2->save($key);
        $this->assertTrue($store2->exists($key));
    }

    public function testWaitAndSaveAfterConflictReleasesLockFromInternalStore()
    {
        $store1 = $this->getStore();
        $conn = $this->createMysqlConnection();
        $store2 = new DoctrineDbalMysqlStore($conn);

        $store1Key = new Key(__METHOD__);

        $store1->save($store1Key);

        // set a low time out then try to wait and save, which will fail
        // because the key is already set above.
        self::setStatementTimeout($conn, 0.001);
        $waitSaveError = null;
        try {
            $store2->waitAndSave(new Key(__METHOD__));
        } catch (DBALException $waitSaveError) {
        }
        $this->assertInstanceOf(DBALException::class, $waitSaveError, 'waitAndSave should have thrown');
        self::setStatementTimeout($conn, 0);

        $store1->delete($store1Key);
        $this->assertFalse($store1->exists($store1Key));

        $store2Key = new Key(__METHOD__);
        $lockConflicted = false;
        try {
            $store2->waitAndSave($store2Key);
        } catch (LockConflictedException $lockConflictedException) {
            $lockConflicted = true;
        }

        $this->assertFalse($lockConflicted, 'lock should be available now that its been remove from $store1');
        $this->assertTrue($store2->exists($store2Key));
    }

    public function testOtherConnConflictException()
    {
        $storeA = $this->getStore();
        $storeB = $this->getStore();

        $key = new Key('foo');
        $storeA->save($key);

        $this->assertFalse($storeB->exists($key));

        try {
            $storeB->save($key);
            $this->fail('Expected exception: '.LockConflictedException::class);
        } catch (LockConflictedException $e) {
            $this->assertStringContainsString('acquired by other', $e->getMessage());
        }
    }

    public function testExistsOnKeyClone()
    {
        $store = $this->getStore();

        $key = new Key('foo');
        $store->save($key);

        $this->assertTrue($store->exists($key));
        $this->assertTrue($store->exists(clone $key));
    }

    public function testDeleteKeyWhileNotOwned()
    {
        $store = $this->getStore();

        $keyA = new Key('foo');
        $keyB = new Key('foo');

        $store->save($keyA); // OK
        try {
            $store->save($keyB); // Conflict
            $this->fail('The store shouldn\'t save the second key');
        } catch (LockConflictedException $e) {
        }

        $store->delete($keyB);
        $this->assertTrue($store->exists($keyA));
        $this->assertFalse($store->exists($keyB));
    }

    public function testStoresAreStateless()
    {
        $conn = $this->createMysqlConnection();

        $storeA = new DoctrineDbalMysqlStore($conn);
        $storeB = new DoctrineDbalMysqlStore($conn);
        $key = new Key('foo');

        $storeA->save($key);
        $this->assertTrue($storeA->exists($key));
        $this->assertTrue($storeB->exists($key));

        $storeB->delete($key);
        $this->assertFalse($storeB->exists($key));
        $this->assertFalse($storeA->exists($key));
    }

    public function testDsnConstructor()
    {
        $this->expectNotToPerformAssertions();

        $store = new DoctrineDbalMysqlStore(self::getMysqlDsn('mysql+advisory'));
        $store->save(new Key('foo'));
    }

    public function testStringifiedFetches()
    {
        $conn = $this->createMysqlConnection([\PDO::ATTR_STRINGIFY_FETCHES => true]);
        $store = new DoctrineDbalMysqlStore($conn);

        $key = new Key('foo');
        $store->save($key);
        $this->assertTrue($store->exists($key));
        $store->delete($key);
        $this->assertFalse($store->exists($key));
    }

    public function testNativePreparedStatements()
    {
        $conn = $this->createMysqlConnection([\PDO::ATTR_EMULATE_PREPARES => false]);
        $store = new DoctrineDbalMysqlStore($conn);

        $key = new Key('foo');
        $store->save($key);
        $this->assertTrue($store->exists($key));
        $store->delete($key);
        $this->assertFalse($store->exists($key));
    }

    public function testPutOffExpirationWhenLockIsLost()
    {
        $conn = $this->createMysqlConnection();
        $store = new DoctrineDbalMysqlStore($conn);

        $key = new Key('foo');
        $store->save($key);

        $conn->executeStatement(\sprintf("DO RELEASE_LOCK('%s')", hash('sha256', (string) $key)));

        $this->expectException(LockConflictedException::class);
        $store->putOffExpiration($key, 300.0);
    }

    public function testDeleteReleasesTheResourceWhenLockIsLost()
    {
        $conn = $this->createMysqlConnection();
        $store = new DoctrineDbalMysqlStore($conn);

        $key = new Key('foo');
        $store->save($key);

        $conn->executeStatement(\sprintf("DO RELEASE_LOCK('%s')", hash('sha256', (string) $key)));
        $store->delete($key);

        $newKey = new Key('foo');
        $store->save($newKey);

        $this->assertTrue($store->exists($newKey));
    }

    public function testResourcesDifferingOnlyByCase()
    {
        $store = $this->getStore();

        $keyLower = new Key('foo');
        $keyUpper = new Key('FOO');

        $store->save($keyLower);

        $store->save($keyUpper);
        $this->assertTrue($store->exists($keyUpper));
        $store->delete($keyUpper);

        $this->assertFalse($store->exists($keyUpper));
        $this->assertTrue($store->exists($keyLower));

        $storeB = $this->getStore();
        try {
            $storeB->save(new Key('foo'));
            $this->fail('The lock held by $keyLower was released by another key');
        } catch (LockConflictedException) {
            $this->addToAssertionCount(1);
        }
    }

    private static function getMysqlDsn(string $scheme): string
    {
        if (!$host = getenv('MYSQL_HOST')) {
            self::markTestSkipped('Missing MYSQL_HOST env variable');
        }

        if (!$user = getenv('MYSQL_USERNAME')) {
            self::markTestSkipped('Missing MYSQL_USERNAME env variable');
        }

        if (!$pass = getenv('MYSQL_PASSWORD')) {
            self::markTestSkipped('Missing MYSQL_PASSWORD env variable');
        }

        return \sprintf('%s://%s:%s@%s', $scheme, $user, $pass, $host);
    }

    /**
     * MySQL expresses the statement timeout in milliseconds, MariaDB in seconds.
     */
    private static function setStatementTimeout(Connection $conn, float $seconds): void
    {
        if ($conn->getDatabasePlatform() instanceof MariaDBPlatform) {
            $conn->executeStatement(\sprintf('SET SESSION max_statement_time=%F', $seconds));
        } else {
            $conn->executeStatement(\sprintf('SET SESSION max_execution_time=%d', 1000 * $seconds));
        }
    }

    private static function getDbalConnection(string $dsn, array $driverOptions = []): Connection
    {
        $params = (new DsnParser(['sqlite' => 'pdo_sqlite']))->parse($dsn);
        if ($driverOptions) {
            $params['driverOptions'] = $driverOptions;
        }
        $config = new Configuration();
        $config->setSchemaManagerFactory(new DefaultSchemaManagerFactory());

        return DriverManager::getConnection($params, $config);
    }
}
