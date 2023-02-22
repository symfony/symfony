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

use Symfony\Component\Lock\Exception\InvalidTtlException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\PdoStore;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @requires extension pdo_sqlite
 *
 * @group integration
 */
class PdoStoreTest extends AbstractStoreTestCase
{
    use ExpiringStoreTestTrait;

    protected static $dbFile;

    public static function setUpBeforeClass(): void
    {
        self::$dbFile = tempnam(sys_get_temp_dir(), 'sf_sqlite_lock');

        $store = new PdoStore('sqlite:'.self::$dbFile);
        $store->createTable();
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$dbFile);
    }

    protected function getClockDelay()
    {
        return 1000000;
    }

    public function getStore(): PersistingStoreInterface
    {
        return new PdoStore('sqlite:'.self::$dbFile);
    }

    public function testAbortAfterExpiration()
    {
        $this->markTestSkipped('Pdo expects a TTL greater than 1 sec. Simulating a slow network is too hard');
    }

    public function testInvalidTtl()
    {
        $this->expectException(InvalidTtlException::class);
        $store = $this->getStore();
        $store->putOffExpiration(new Key('toto'), 0.1);
    }

    public function testInvalidTtlConstruct()
    {
        $this->expectException(InvalidTtlException::class);

        return new PdoStore('sqlite:'.self::$dbFile, [], 0.1, 0);
    }

    /**
     * @dataProvider provideDsn
     */
    public function testDsn(string $dsn, string $file = null)
    {
        $key = new Key(uniqid(__METHOD__, true));

        try {
            $store = new PdoStore($dsn);

            $store->save($key);
            $this->assertTrue($store->exists($key));
        } finally {
            if (null !== $file) {
                @unlink($file);
            }
        }
    }

    public static function provideDsn()
    {
        $dbFile = tempnam(sys_get_temp_dir(), 'sf_sqlite_cache');
        yield ['sqlite:'.$dbFile.'2', $dbFile.'2'];
        yield ['sqlite::memory:'];
    }
}
