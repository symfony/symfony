<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineOrm\SchemaListener;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Symfony\Bridge\Doctrine\SchemaListener\AbstractSchemaListener;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\DataKeyStore;

/**
 * Declares the table the data key store needs in the schema the ORM assembles.
 *
 * It extends the abstract listener of `symfony/doctrine-bridge` rather than repeating it, the way
 * the Lock, Messenger, Cache, Session and RememberMe stores do: honouring the schema assets filter
 * and telling a table on another database apart is a hundred lines nobody should own twice.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class DataKeyStoreSchemaListener extends AbstractSchemaListener
{
    /**
     * @param iterable<mixed, object> $stores
     */
    public function __construct(
        private readonly iterable $stores,
    ) {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $connection = $event->getEntityManager()->getConnection();
        $schema = $event->getSchema();

        foreach ($this->stores as $store) {
            if (!$store instanceof DataKeyStore) {
                continue;
            }

            $isSameDatabaseChecker = $this->getIsSameDatabaseChecker($connection);
            $schema = $this->filterSchemaChanges($schema, $connection, static fn () => $store->configureSchema($schema, $isSameDatabaseChecker));
        }

        if (method_exists($schema, 'edit') && method_exists($event, 'setSchema')) {
            $event->setSchema($schema);
        }
    }
}
