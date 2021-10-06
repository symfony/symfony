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

use Doctrine\DBAL\DriverManager;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\DoctrineDbalPostgreSqlStore;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @requires extension pdo_pgsql
 * @group integration
 */
class DoctrineDbalPostgreSqlStoreTest extends AbstractStoreTest
{
    use BlockingStoreTestTrait;
    use SharedLockStoreTestTrait;

    /**
     * {@inheritdoc}
     */
    public function getStore(): PersistingStoreInterface
    {
        if (!getenv('POSTGRES_HOST')) {
            $this->markTestSkipped('Missing POSTGRES_HOST env variable');
        }
        $conn = DriverManager::getConnection(['url' => 'pgsql://postgres:password@'.getenv('POSTGRES_HOST')]);

        return new DoctrineDbalPostgreSqlStore($conn);
    }

    /**
     * @requires extension pdo_sqlite
     * @dataProvider getInvalidDrivers
     */
    public function testInvalidDriver($connOrDsn)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The adapter "Symfony\Component\Lock\Store\DoctrineDbalPostgreSqlStore" does not support');

        $store = new DoctrineDbalPostgreSqlStore($connOrDsn);
        $store->exists(new Key('foo'));
    }

    public function getInvalidDrivers()
    {
        yield ['sqlite:///tmp/foo.db'];
        yield [DriverManager::getConnection(['url' => 'sqlite:///tmp/foo.db'])];
    }
}
