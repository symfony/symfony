<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Middleware\IdleConnection;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Middleware\IdleConnection\Driver;

class DriverTest extends TestCase
{
    /**
     * @group time-sensitive
     */
    public function testConnect()
    {
        $driverMock = $this->createMock(DriverInterface::class);
        $connectionMock = $this->createMock(ConnectionInterface::class);

        $driverMock->expects($this->once())
            ->method('connect')
            ->willReturn($connectionMock);

        $connectionExpiries = new \ArrayObject();

        $driver = new Driver($driverMock, $connectionExpiries, 60, 'default');
        $connection = $driver->connect([]);

        $this->assertSame($connectionMock, $connection);
        $this->assertArrayHasKey('default', $connectionExpiries);
        $this->assertSame(time() + 60, $connectionExpiries['default']);
    }
}
