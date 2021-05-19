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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\PdoStore;

/**
 * @requires extension pdo_sqlite
 */
class PdoDbalStoreTest extends AbstractStoreTest
{
    use ExpiringStoreTestTrait;

    protected static $dbFile;

    public static function setUpBeforeClass(): void
    {
        self::$dbFile = tempnam(sys_get_temp_dir(), 'sf_sqlite_lock');

        $store = new PdoStore(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile]));
        $store->createTable();
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$dbFile);
    }

    /**
     * {@inheritdoc}
     */
    protected function getClockDelay()
    {
        return 1000000;
    }

    /**
     * {@inheritdoc}
     */
    public function getStore(): PersistingStoreInterface
    {
        return new PdoStore(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => self::$dbFile]));
    }

    public function testAbortAfterExpiration()
    {
        $this->markTestSkipped('Pdo expects a TTL greater than 1 sec. Simulating a slow network is too hard');
    }

    public function testConfigureSchema()
    {
        $store = new PdoStore($this->createMock(Connection::class), ['db_table' => 'lock_table']);
        $schema = new Schema();
        $store->configureSchema($schema);
        $this->assertTrue($schema->hasTable('lock_table'));
    }
}
