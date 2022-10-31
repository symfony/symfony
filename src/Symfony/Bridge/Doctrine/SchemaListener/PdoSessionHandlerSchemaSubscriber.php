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
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

final class PdoSessionHandlerSchemaSubscriber extends AbstractSchemaSubscriber
{
    private iterable $pdoSessionHandlers;

    /**
     * @param iterable<mixed, PdoSessionHandler> $pdoSessionHandlers
     */
    public function __construct(iterable $pdoSessionHandlers)
    {
        $this->pdoSessionHandlers = $pdoSessionHandlers;
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $connection = $event->getEntityManager()->getConnection();

        foreach ($this->pdoSessionHandlers as $pdoSessionHandler) {
            $pdoSessionHandler->configureSchema($event->getSchema(), $this->getIsSameDatabaseChecker($connection));
        }
    }
}
