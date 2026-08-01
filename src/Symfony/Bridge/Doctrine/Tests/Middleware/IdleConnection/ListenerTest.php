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
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ListenerTest extends TestCase
{
    public function testOnKernelRequest()
    {
        $connectionExpiries = new \ArrayObject(['connectionone' => time() - 30, 'connectiontwo' => time() + 40]);

        $connectionOneMock = $this->createStub(ConnectionInterface::class);

        $container = new Container();
        $container->set('doctrine.dbal.connectionone_connection', $connectionOneMock);

        $listener = new Listener($connectionExpiries, $container);

        $listener->onKernelRequest(new RequestEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST));

        $this->assertArrayNotHasKey('connectionone', (array) $connectionExpiries);
        $this->assertArrayHasKey('connectiontwo', (array) $connectionExpiries);
    }

    public function testOnKernelRequestClosesEveryExpiredConnection()
    {
        $connectionExpiries = new \ArrayObject([
            'connectionone' => time() - 30,
            'connectiontwo' => time() - 20,
            'connectionthree' => time() + 40,
        ]);

        $connectionOneMock = $this->createMock(ConnectionInterface::class);
        $connectionOneMock->expects($this->once())->method('close');

        $connectionTwoMock = $this->createMock(ConnectionInterface::class);
        $connectionTwoMock->expects($this->once())->method('close');

        $connectionThreeMock = $this->createMock(ConnectionInterface::class);
        $connectionThreeMock->expects($this->never())->method('close');

        $container = new Container();
        $container->set('doctrine.dbal.connectionone_connection', $connectionOneMock);
        $container->set('doctrine.dbal.connectiontwo_connection', $connectionTwoMock);
        $container->set('doctrine.dbal.connectionthree_connection', $connectionThreeMock);

        $listener = new Listener($connectionExpiries, $container);

        $listener->onKernelRequest(new RequestEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST));

        $this->assertArrayNotHasKey('connectionone', (array) $connectionExpiries);
        $this->assertArrayNotHasKey('connectiontwo', (array) $connectionExpiries);
        $this->assertArrayHasKey('connectionthree', (array) $connectionExpiries);
    }

    public function testOnKernelRequestShouldSkipSubrequests()
    {
        self::expectNotToPerformAssertions();
        $arrayObj = $this->createStub(\ArrayObject::class);
        $arrayObj->method('getArrayCopy')->willThrowException(new \Exception('Invalid behavior'));
        $listener = new Listener($arrayObj, new Container());

        $listener->onKernelRequest(new RequestEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::SUB_REQUEST));
    }
}
