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

final class PdoSessionHandlerSchemaListener extends AbstractSchemaListener
{
    private PdoSessionHandler $sessionHandler;

    public function __construct(\SessionHandlerInterface $sessionHandler)
    {
        if ($sessionHandler instanceof PdoSessionHandler) {
            $this->sessionHandler = $sessionHandler;
        }
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $event): void
    {
        if (!isset($this->sessionHandler)) {
            return;
        }

        $connection = $event->getEntityManager()->getConnection();

        $this->sessionHandler->configureSchema($event->getSchema(), $this->getIsSameDatabaseChecker($connection));
    }
}
