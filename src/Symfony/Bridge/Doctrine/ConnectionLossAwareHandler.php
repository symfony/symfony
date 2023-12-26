<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;

/** @internal */
class ConnectionLossAwareHandler
{
    public static function reconnectOnFailure(Connection $connection): void
    {
        try {
            $connection->executeQuery($connection->getDatabasePlatform($connection)->getDummySelectSQL());
        } catch (DBALException) {
            $connection->close();
            // Attempt to reestablish the lazy connection by sending another query.
            $connection->executeQuery($connection->getDatabasePlatform($connection)->getDummySelectSQL());
        }
    }
}
