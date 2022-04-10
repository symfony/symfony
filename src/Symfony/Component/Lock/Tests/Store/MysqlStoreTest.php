<?php

namespace Symfony\Component\Lock\Tests\Store;

use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\MysqlStore;

class MysqlStoreTest extends AbstractStoreTest
{
    protected function getPdo(): \PDO
    {
        if (!$host = getenv('MYSQL_HOST')) {
            $this->markTestSkipped('Missing MYSQL_HOST env variable');
        }

        if (!$user = getenv('MYSQL_USERNAME')) {
            $this->markTestSkipped('Missing MYSQL_USERNAME env variable');
        }

        if (!$pass = getenv('MYSQL_PASSWORD')) {
            $this->markTestSkipped('Missing MYSQL_PASSWORD env variable');
        }

        return new \PDO('mysql:host='.$host, $user, $pass);
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

    public function testSelfConflictException()
    {
        $store = $this->getStore();
        $store->save(new Key('foo'));

        try {
            $store->save(new Key('foo'));
            $this->fail('Expected exception: '.LockConflictedException::class);
        } catch (LockConflictedException $e) {
            $this->assertStringContainsString('acquired by this', $e->getMessage());
        }
    }

    public function testOtherConflictException()
    {
        $storeA = $this->getStore();
        $storeA->save(new Key('foo'));

        $storeB = $this->getStore();

        try {
            $storeB->save(new Key('foo'));
            $this->fail('Expected exception: '.LockConflictedException::class);
        } catch (LockConflictedException $e) {
            $this->assertStringContainsString('acquired by other', $e->getMessage());
        }
    }
}
