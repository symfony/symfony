<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\SchemaListener;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\DoctrineDbalStore;

final class LockStoreSchemaListener extends AbstractSchemaListener
{
    /**
     * @param iterable<mixed, PersistingStoreInterface> $stores
     */
    public function __construct(private iterable $stores)
    {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $connection = $event->getEntityManager()->getConnection();

        $storesIterator = new \ArrayIterator($this->stores);
        while ($storesIterator->valid()) {
            try {
                $store = $storesIterator->current();
                if (!$store instanceof DoctrineDbalStore) {
                    continue;
                }

                $store->configureSchema($event->getSchema(), $this->getIsSameDatabaseChecker($connection));
            } catch (InvalidArgumentException) {
                // no-op
            }

            $storesIterator->next();
        }
    }
}
