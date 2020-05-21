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

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Event\SchemaCreateTableEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;
use Symfony\Component\Scheduler\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Scheduler\Transport\TransportInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SchedulerTransportDoctrineSchemaSubscriber implements EventSubscriber
{
    private const PROCESSING_TABLE_FLAG = self::class.':processing';

    private $transports;

    /**
     * @param iterable|TransportInterface[] $transports
     */
    public function __construct(iterable $transports)
    {
        $this->transports = $transports;
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $connection = $event->getEntityManager()->getConnection();
        foreach ($this->transports as $transport) {
            if (!$transport instanceof DoctrineTransport) {
                continue;
            }

            $transport->configureSchema($event->getSchema(), $connection);
        }
    }

    public function onSchemaCreateTable(SchemaCreateTableEventArgs $event): void
    {
        $table = $event->getTable();

        if ($table->hasOption(self::PROCESSING_TABLE_FLAG)) {
            return;
        }

        foreach ($this->transports as $transport) {
            if (!$transport instanceof DoctrineTransport) {
                continue;
            }

            $table->addOption(self::PROCESSING_TABLE_FLAG, true);
            $createTableSql = $event->getPlatform()->getCreateTableSQL($table);

            $event->addSql($createTableSql);
            $event->preventDefault();

            return;
        }
    }

    public function getSubscribedEvents(): array
    {
        return [
            ToolEvents::postGenerateSchema,
            Events::onSchemaCreateTable,
        ];
    }
}
