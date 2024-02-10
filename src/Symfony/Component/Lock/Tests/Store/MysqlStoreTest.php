<?php

namespace Symfony\Component\Lock\Tests\Store;

use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\MysqlStore;

class MysqlStoreTest extends AbstractStoreTest
{
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

        [$host, $user, $pass] = $this->getEnv();
        $store = new MysqlStore("mysql:$host", ['db_username' => $user, 'db_password' => $pass]);
        $store->save(new Key('foo'));
    }
}
