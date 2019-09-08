<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Traits;

trait PdoPruneableTrait
{
    protected function isPruned($cache, string $name): bool
    {
        $o = new \ReflectionObject($cache);

        if (!$o->hasMethod('getConnection')) {
            self::fail('Cache does not have "getConnection()" method.');
        }

        $getPdoConn = $o->getMethod('getConnection');
        $getPdoConn->setAccessible(true);

        /** @var \Doctrine\DBAL\Statement $select */
        $select = $getPdoConn->invoke($cache)->prepare('SELECT 1 FROM cache_items WHERE item_id LIKE :id');
        $select->bindValue(':id', sprintf('%%%s', $name));
        $select->execute();

        return 0 === \count($select->fetchAll(\PDO::FETCH_COLUMN));
    }
}
