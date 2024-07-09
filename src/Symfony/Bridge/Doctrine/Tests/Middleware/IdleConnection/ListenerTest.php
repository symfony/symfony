<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Middleware\IdleConnection;

use Doctrine\DBAL\Connection as ConnectionInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Middleware\IdleConnection\Listener;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class ListenerTest extends TestCase
{
    public function testOnKernelRequest()
    {
        $containerMock = $this->createMock(ContainerInterface::class);
        $connectionExpiries = new \ArrayObject(['connectionone' => time() - 30, 'connectiontwo' => time() + 40]);

        $connectionOneMock = $this->getMockBuilder(ConnectionInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $containerMock->expects($this->exactly(1))
            ->method('get')
            ->with('doctrine.dbal.connectionone_connection')
            ->willReturn($connectionOneMock);

        $listener = new Listener($connectionExpiries, $containerMock);

        $listener->onKernelRequest($this->createMock(RequestEvent::class));

        $this->assertArrayNotHasKey('connectionone', (array) $connectionExpiries);
        $this->assertArrayHasKey('connectiontwo', (array) $connectionExpiries);
    }
}
