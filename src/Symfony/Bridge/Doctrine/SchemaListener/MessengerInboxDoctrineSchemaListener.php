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
use Symfony\Component\Messenger\Bridge\Doctrine\Inbox\DoctrineInbox;

/**
 * Adds the tables of the Messenger inboxes to the Doctrine schema.
 */
class MessengerInboxDoctrineSchemaListener extends AbstractSchemaListener
{
    /**
     * @param iterable<mixed, object> $inboxes
     */
    public function __construct(
        private readonly iterable $inboxes,
    ) {
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $connection = $event->getEntityManager()->getConnection();
        $schema = $event->getSchema();

        foreach ($this->inboxes as $inbox) {
            if (!$inbox instanceof DoctrineInbox) {
                continue;
            }

            $isSameDatabaseChecker = $this->getIsSameDatabaseChecker($connection);
            $schema = $this->filterSchemaChanges($schema, $connection, static fn () => $inbox->configureSchema($schema, $connection, $isSameDatabaseChecker));
        }

        if (method_exists($schema, 'edit') && method_exists($event, 'setSchema')) {
            $event->setSchema($schema);
        }
    }
}
