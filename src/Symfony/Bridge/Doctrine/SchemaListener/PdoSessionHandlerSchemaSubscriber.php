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
    private PdoSessionHandler $sessionHandler;

    public function __construct(\SessionHandlerInterface $sessionHandler)
    {
        if ($sessionHandler instanceof PdoSessionHandler) {
            $this->sessionHandler = $sessionHandler;
        }
    }

    public function getSubscribedEvents(): array
    {
        return isset($this->sessionHandler) ? parent::getSubscribedEvents() : [];
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        $connection = $event->getEntityManager()->getConnection();

        $this->sessionHandler->configureSchema($event->getSchema(), $this->getIsSameDatabaseChecker($connection));
    }
}
