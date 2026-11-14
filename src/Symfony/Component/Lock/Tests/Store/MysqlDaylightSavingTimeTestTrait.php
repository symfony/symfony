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

use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;

trait MysqlDaylightSavingTimeTestTrait
{
    /**
     * @param array{host: string, user: string, password: string, dbname: string} $params
     *
     * @return array{PersistingStoreInterface, \Closure(string): mixed} The store and a closure that runs SQL on its connection
     */
    abstract protected function createMysqlStore(array $params): array;

    /**
     * @requires extension pdo_mysql
     *
     * @group integration
     *
     * @dataProvider provideDaylightSavingTimeChanges
     */
    public function testMysqlLockSurvivesDaylightSavingTimeChange(int $holderTime, int $contenderTime, bool $holderKeepsLock)
    {
        if (!$host = getenv('MYSQL_HOST')) {
            $this->markTestSkipped('Missing MYSQL_HOST env variable');
        }

        $params = [
            'host' => $host,
            'user' => getenv('MYSQL_USERNAME') ?: 'root',
            'password' => getenv('MYSQL_PASSWORD') ?: '',
            'dbname' => 'symfony_lock_test',
        ];
        $pdo = new \PDO('mysql:host='.$host, $params['user'], $params['password']);

        if (null === $pdo->query("SELECT CONVERT_TZ('2025-11-02 07:00:00', '+00:00', 'America/Chicago')")->fetchColumn()) {
            $this->markTestSkipped('MySQL time zone tables are not loaded');
        }

        $pdo->exec('CREATE DATABASE IF NOT EXISTS symfony_lock_test');

        try {
            [$store, $exec] = $this->createMysqlStore($params);
            $exec("SET time_zone = 'America/Chicago'");
            $holder = new Key(__METHOD__);
            $contender = new Key(__METHOD__);

            $exec('SET TIMESTAMP = '.$holderTime);
            $store->save($holder);

            $exec('SET TIMESTAMP = '.$contenderTime);
            try {
                $store->save($contender);
            } catch (LockConflictedException) {
                // expected when the holder keeps the lock
            }

            $this->assertSame($holderKeepsLock, $store->exists($holder));
            $this->assertSame(!$holderKeepsLock, $store->exists($contender));
        } finally {
            $pdo->exec('DROP DATABASE IF EXISTS symfony_lock_test');
        }
    }

    public static function provideDaylightSavingTimeChanges(): iterable
    {
        // On 2025-11-02, America/Chicago went from 02:00 CDT back to 01:00 CST at 07:00 UTC, so 01:00-02:00 local happened twice.
        yield 'lock taken before 02:00 CST' => [gmmktime(7, 58, 0, 11, 2, 2025), gmmktime(8, 0, 30, 11, 2, 2025), true];
        yield 'lock expired after the second 01:00' => [gmmktime(6, 58, 0, 11, 2, 2025), gmmktime(7, 4, 0, 11, 2, 2025), false];
    }
}
