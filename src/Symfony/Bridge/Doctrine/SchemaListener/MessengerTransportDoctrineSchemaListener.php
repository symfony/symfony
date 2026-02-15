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
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Automatically adds any required database tables to the Doctrine Schema.
 */
class MessengerTransportDoctrineSchemaListener extends AbstractSchemaListener
{
    /**
     * @param iterable<mixed, TransportInterface> $transports
     */
    public function __construct(
        private readonly iterable $transports,
    ) {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $connection = $event->getEntityManager()->getConnection();
        $schema = $event->getSchema();

        foreach ($this->transports as $transport) {
            if (!$transport instanceof DoctrineTransport) {
                continue;
            }

            $isSameDatabaseChecker = $this->getIsSameDatabaseChecker($connection);
            $this->filterSchemaChanges($schema, $connection, static function () use ($transport, $schema, $connection, $isSameDatabaseChecker) {
                $transport->configureSchema($schema, $connection, $isSameDatabaseChecker);
            });
        }
    }
}
