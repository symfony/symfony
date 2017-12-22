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
use Symfony\Component\Lock\Store\MysqlStore;

/**
 * @author Jérôme TAMARELLE <jerome@tamarelle.net>
 */
class MysqlStoreTest extends AbstractStoreTest
{
    use BlockingStoreTestTrait;

    /**
     * {@inheritdoc}
     */
    public function getStore()
    {
        return new MysqlStore(getenv('MYSQL_DSN'), array(
            'db_username' => getenv('MYSQL_USER'),
            'db_password' => getenv('MYSQL_PASSWORD'),
        ));
    }

    /**
     * @medium
     *
     * @todo use phpunit/php-invoker to avoid infinite wait ?
     */
    public function testConfigurableWaitTimeout()
    {
        $store = new MysqlStore(getenv('MYSQL_DSN'), array(
            'db_username' => getenv('MYSQL_USER'),
            'db_password' => getenv('MYSQL_PASSWORD'),
            'wait_timeout' => 1,
        ));

        $resource = uniqid(__METHOD__, true);
        $key1 = new Key($resource);
        $key2 = new Key($resource);

        $store->waitAndSave($key1);

        $startTime = microtime(true);

        try {
            $store->waitAndSave($key2);

            $this->fail('The store shouldn\'t save the second key');
        } catch (LockConflictedException $e) {
            // Expected
        }

        $waitTime = microtime(true) - $startTime;

        $this->assertGreaterThanOrEqual(1, $waitTime);
        $this->assertLessThan(2, $waitTime);
    }
}
