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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\MysqlStore;
use Symfony\Component\Lock\Test\AbstractStoreTestCase;

#[RequiresPhpExtension('pdo_mysql')]
#[Group('integration')]
class MysqlStoreTest extends AbstractStoreTestCase
{
    use BlockingStoreTestTrait;

    protected function getEnv(): array
    {
        if (!$dsn = getenv('MYSQL_DSN')) {
            $this->markTestSkipped('Missing MYSQL_DSN env variable');
        }

        if (!$user = getenv('MYSQL_USERNAME')) {
            $this->markTestSkipped('Missing MYSQL_USERNAME env variable');
        }

        if (!$pass = getenv('MYSQL_PASSWORD')) {
            $this->markTestSkipped('Missing MYSQL_PASSWORD env variable');
        }

        return [$dsn, $user, $pass];
    }

    protected function getPdo(): \PDO
    {
        [$dsn, $user, $pass] = $this->getEnv();

        return new \PDO($dsn, $user, $pass);
    }

    protected function getStore(): PersistingStoreInterface
    {
        return new MysqlStore($this->getPdo());
    }

    public function testDriverRequirement()
    {
        $this->expectException(InvalidArgumentException::class);
        new MysqlStore(new \PDO('sqlite::memory:'));
    }

    public function testExceptionModeRequirement()
    {
        $this->expectException(InvalidArgumentException::class);
        $pdo = $this->getPdo();
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
        new MysqlStore($pdo);
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
        $pdo = $this->getPdo();

        $storeA = new MysqlStore($pdo);
        $storeB = new MysqlStore($pdo);
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

        [$dsn, $user, $pass] = $this->getEnv();
        $store = new MysqlStore("$dsn", ['db_username' => $user, 'db_password' => $pass]);
        $store->save(new Key('foo'));
    }

    public function testStringifiedFetches()
    {
        [$dsn, $user, $pass] = $this->getEnv();
        $pdo = new \PDO($dsn, $user, $pass, [\PDO::ATTR_STRINGIFY_FETCHES => true]);
        $store = new MysqlStore($pdo);

        $key = new Key('foo');
        $store->save($key);
        $this->assertTrue($store->exists($key));
        $store->delete($key);
        $this->assertFalse($store->exists($key));
    }

    public function testNativePreparedStatements()
    {
        [$dsn, $user, $pass] = $this->getEnv();
        $pdo = new \PDO($dsn, $user, $pass, [\PDO::ATTR_EMULATE_PREPARES => false]);
        $store = new MysqlStore($pdo);

        $key = new Key('foo');
        $store->save($key);
        $this->assertTrue($store->exists($key));
        $store->delete($key);
        $this->assertFalse($store->exists($key));
    }

    public function testPutOffExpirationWhenLockIsLost()
    {
        $pdo = $this->getPdo();
        $store = new MysqlStore($pdo);

        $key = new Key('foo');
        $store->save($key);

        $hashedKey = hash('sha256', (string) $key);

        $pdo->exec("DO RELEASE_LOCK('$hashedKey')");

        $this->expectException(LockConflictedException::class);
        $store->putOffExpiration($key, 300.0);
    }

    public function testDeleteReleasesTheResourceWhenLockIsLost()
    {
        $pdo = $this->getPdo();
        $store = new MysqlStore($pdo);

        $key = new Key('foo');
        $store->save($key);

        $pdo->exec(\sprintf("DO RELEASE_LOCK('%s')", hash('sha256', (string) $key)));
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
}
