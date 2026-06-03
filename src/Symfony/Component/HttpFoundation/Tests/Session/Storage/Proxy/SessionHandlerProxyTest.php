<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Proxy;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\StrictSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\SessionHandlerProxy;

/**
 * Tests for SessionHandlerProxy class.
 *
 * @author Drak <drak@zikula.org>
 */
#[PreserveGlobalState(false)]
#[RunTestsInSeparateProcesses]
class SessionHandlerProxyTest extends TestCase
{
    public function testOpenTrue()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $proxy = new SessionHandlerProxy($handler);
        $handler->expects($this->once())
            ->method('open')
            ->willReturn(true);

        $this->assertFalse($proxy->isActive());
        $proxy->open('name', 'id');
        $this->assertFalse($proxy->isActive());
    }

    public function testOpenFalse()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $proxy = new SessionHandlerProxy($handler);
        $handler->expects($this->once())
            ->method('open')
            ->willReturn(false);

        $this->assertFalse($proxy->isActive());
        $proxy->open('name', 'id');
        $this->assertFalse($proxy->isActive());
    }

    public function testClose()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $proxy = new SessionHandlerProxy($handler);
        $handler->expects($this->once())
            ->method('close')
            ->willReturn(true);

        $this->assertFalse($proxy->isActive());
        $proxy->close();
        $this->assertFalse($proxy->isActive());
    }

    public function testCloseFalse()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $proxy = new SessionHandlerProxy($handler);
        $handler->expects($this->once())
            ->method('close')
            ->willReturn(false);

        $this->assertFalse($proxy->isActive());
        $proxy->close();
        $this->assertFalse($proxy->isActive());
    }

    public function testRead()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $proxy = new SessionHandlerProxy($handler);
        $handler->expects($this->once())
            ->method('read')
            ->willReturn('foo')
        ;

        $proxy->read('id');
    }

    public function testWrite()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $proxy = new SessionHandlerProxy($handler);
        $handler->expects($this->once())
            ->method('write')
            ->willReturn(true)
        ;

        $this->assertTrue($proxy->write('id', 'data'));
    }

    public function testDestroy()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $proxy = new SessionHandlerProxy($handler);
        $handler->expects($this->once())
            ->method('destroy')
            ->willReturn(true)
        ;

        $this->assertTrue($proxy->destroy('id'));
    }

    public function testGc()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $proxy = new SessionHandlerProxy($handler);
        $handler->expects($this->once())
            ->method('gc')
            ->willReturn(1)
        ;

        $proxy->gc(86400);
    }

    public function testValidateIdWithoutUpdateTimestampHandler()
    {
        $proxy = new SessionHandlerProxy($this->createStub(\SessionHandlerInterface::class));

        $this->assertTrue($proxy->validateId('id'));
    }

    public function testValidateIdWithUpdateTimestampHandlerAndValidId()
    {
        $handler = $this->createMock(TestSessionHandler::class);
        $handler->expects($this->once())
            ->method('validateId')
            ->willReturn(true);

        $proxy = new SessionHandlerProxy($handler);

        $this->assertTrue($proxy->validateId('id'));
    }

    public function testValidateIdWithUpdateTimestampHandlerAndInvalidId()
    {
        $handler = $this->createMock(TestSessionHandler::class);
        $handler->expects($this->once())
            ->method('validateId')
            ->willReturn(false);

        $proxy = new SessionHandlerProxy($handler);

        $this->assertFalse($proxy->validateId('id'));
    }

    public function testUpdateTimestampWithUpdateTimestampHandler()
    {
        $handler = $this->createMock(TestSessionHandler::class);
        $handler->expects($this->once())
            ->method('updateTimestamp')
            ->willReturn(false);

        $proxy = new SessionHandlerProxy($handler);

        $proxy->updateTimestamp('id', 'data');
    }

    public function testUpdateTimestampWithoutUpdateTimestampHandler()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $handler->expects($this->once())
            ->method('write')
            ->willReturn(true);

        $proxy = new SessionHandlerProxy($handler);

        $this->assertTrue($proxy->updateTimestamp('id', 'data'));
    }

    #[DataProvider('provideNativeSessionStorageHandler')]
    public function testNativeSessionStorageSaveHandlerName($handler)
    {
        $this->assertSame('files', (new NativeSessionStorage([], $handler))->getSaveHandler()->getSaveHandlerName());
    }

    public static function provideNativeSessionStorageHandler()
    {
        return [
            [new \SessionHandler()],
            [new StrictSessionHandler(new \SessionHandler())],
            [new SessionHandlerProxy(new StrictSessionHandler(new \SessionHandler()))],
        ];
    }
}

abstract class TestSessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
}
