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
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\ToolEvents;
use Symfony\Component\Cache\Adapter\PdoAdapter;

/**
 * Automatically adds the cache table needed for the PdoAdapter.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class PdoCacheAdapterDoctrineSchemaSubscriber implements EventSubscriber
{
    private $pdoAdapters;

    /**
     * @param iterable|PdoAdapter[] $pdoAdapters
     */
    public function __construct(iterable $pdoAdapters)
    {
        $this->pdoAdapters = $pdoAdapters;
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $dbalConnection = $event->getEntityManager()->getConnection();
        foreach ($this->pdoAdapters as $pdoAdapter) {
            $pdoAdapter->configureSchema($event->getSchema(), $dbalConnection);
        }
    }

    public function getSubscribedEvents(): array
    {
        if (!class_exists(ToolEvents::class)) {
            return [];
        }

        return [
            ToolEvents::postGenerateSchema,
        ];
    }
}
