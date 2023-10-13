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

use Doctrine\DBAL\Event\SchemaCreateTableEventArgs;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Automatically adds any required database tables to the Doctrine Schema.
 */
class MessengerTransportDoctrineSchemaListener extends AbstractSchemaListener
{
    private const PROCESSING_TABLE_FLAG = self::class.':processing';

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

        foreach ($this->transports as $transport) {
            if (!$transport instanceof DoctrineTransport) {
                continue;
            }

            $transport->configureSchema($event->getSchema(), $connection, $this->getIsSameDatabaseChecker($connection));
        }
    }

    public function onSchemaCreateTable(SchemaCreateTableEventArgs $event): void
    {
        $table = $event->getTable();

        // if this method triggers a nested create table below, allow Doctrine to work like normal
        if ($table->hasOption(self::PROCESSING_TABLE_FLAG)) {
            return;
        }

        foreach ($this->transports as $transport) {
            if (!$transport instanceof DoctrineTransport) {
                continue;
            }

            if (!$extraSql = $transport->getExtraSetupSqlForTable($table)) {
                continue;
            }

            // avoid this same listener from creating a loop on this table
            $table->addOption(self::PROCESSING_TABLE_FLAG, true);
            $createTableSql = $event->getPlatform()->getCreateTableSQL($table);

            /*
             * Add all the SQL needed to create the table and tell Doctrine
             * to "preventDefault" so that only our SQL is used. This is
             * the only way to inject some extra SQL.
             */
            $event->addSql($createTableSql);
            foreach ($extraSql as $sql) {
                $event->addSql($sql);
            }
            $event->preventDefault();

            return;
        }
    }
}
