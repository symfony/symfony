<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Doctrine\Tests\Transport;

use Doctrine\DBAL\Schema\Synchronizer\SchemaSynchronizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\PostgreSqlConnection;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PostgreSqlConnectionTest extends TestCase
{
    public function testSerialize()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Cannot serialize '.PostgreSqlConnection::class);

        $schemaSynchronizer = $this->createMock(SchemaSynchronizer::class);
        $driverConnection = $this->createMock(\Doctrine\DBAL\Connection::class);

        $connection = new PostgreSqlConnection([], $driverConnection, $schemaSynchronizer);
        serialize($connection);
    }

    public function testUnserialize()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Cannot unserialize '.PostgreSqlConnection::class);

        $schemaSynchronizer = $this->createMock(SchemaSynchronizer::class);
        $driverConnection = $this->createMock(\Doctrine\DBAL\Connection::class);

        $connection = new PostgreSqlConnection([], $driverConnection, $schemaSynchronizer);
        $connection->__wakeup();
    }
}
