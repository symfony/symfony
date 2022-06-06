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

use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\PostgreSqlStore;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 *
 * @requires extension pdo_pgsql
 * @group integration
 * @group legacy
 */
class PostgreSqlDbalStoreTest extends AbstractStoreTest
{
    use BlockingStoreTestTrait;
    use ExpectDeprecationTrait;
    use SharedLockStoreTestTrait;

    /**
     * {@inheritdoc}
     */
    public function getStore(): PersistingStoreInterface
    {
        if (!getenv('POSTGRES_HOST')) {
            $this->markTestSkipped('Missing POSTGRES_HOST env variable');
        }

        $this->expectDeprecation('Since symfony/lock 5.4: Usage of a DBAL Connection with "Symfony\Component\Lock\Store\PostgreSqlStore" is deprecated and will be removed in symfony 6.0. Use "Symfony\Component\Lock\Store\DoctrineDbalPostgreSqlStore" instead.');

        return new PostgreSqlStore('pgsql://postgres:password@'.getenv('POSTGRES_HOST'));
    }
}
