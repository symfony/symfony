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

use Doctrine\DBAL\Connection as ConnectionInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Middleware\IdleConnection\Listener;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

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
        $event = $this->createMock(RequestEvent::class);
        $event->method('getRequestType')->willReturn(HttpKernelInterface::MAIN_REQUEST);

        $listener->onKernelRequest($event);

        $this->assertArrayNotHasKey('connectionone', (array) $connectionExpiries);
        $this->assertArrayHasKey('connectiontwo', (array) $connectionExpiries);
    }

    public function testOnKernelRequestShouldSkipSubrequests()
    {
        self::expectNotToPerformAssertions();
        $arrayObj = $this->createMock(\ArrayObject::class);
        $arrayObj->method('getIterator')->willThrowException(new \Exception('Invalid behavior'));
        $listener = new Listener($arrayObj, $this->createMock(ContainerInterface::class));

        $event = $this->createMock(RequestEvent::class);
        $event->method('getRequestType')->willReturn(HttpKernelInterface::SUB_REQUEST);
        $listener->onKernelRequest($event);
    }
}
