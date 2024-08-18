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
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\DoctrineDbalStore;

final class LockStoreSchemaListener extends AbstractSchemaListener
{
    /**
     * @param iterable<mixed, PersistingStoreInterface> $stores
     */
    public function __construct(
        private readonly iterable $stores,
    ) {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $connection = $event->getEntityManager()->getConnection();

        foreach ($this->stores as $store) {
            if (!$store instanceof DoctrineDbalStore) {
                continue;
            }

            $store->configureSchema($event->getSchema(), $this->getIsSameDatabaseChecker($connection));
        }
    }
}
