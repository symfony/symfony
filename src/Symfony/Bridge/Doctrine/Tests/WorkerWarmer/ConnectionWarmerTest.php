<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\WorkerWarmer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\WorkerWarmer\ConnectionWarmer;

class ConnectionWarmerTest extends TestCase
{
    #[RequiresPhpExtension('pdo_sqlite')]
    public function testConnectionsAreOpened()
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->assertFalse($connection->isConnected());

        (new ConnectionWarmer(['default' => $connection]))();

        $this->assertTrue($connection->isConnected());
    }

    public function testFailureIsLoggedAndRemainingConnectionsAreOpened()
    {
        $exception = new \RuntimeException('Connection refused');
        $failing = $this->createMock(Connection::class);
        $failing->expects($this->once())->method('getNativeConnection')->willThrowException($exception);
        $working = $this->createMock(Connection::class);
        $working->expects($this->once())->method('getNativeConnection');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with(
            'Failed to preconnect the "{name}" DBAL connection: {message}',
            ['name' => 'failing', 'message' => 'Connection refused', 'exception' => $exception],
        );

        (new ConnectionWarmer(['failing' => $failing, 'working' => $working], $logger))();
    }
}
