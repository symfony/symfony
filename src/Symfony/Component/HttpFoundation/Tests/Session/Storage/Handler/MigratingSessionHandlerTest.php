<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Handler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\MigratingSessionHandler;

class MigratingSessionHandlerTest extends TestCase
{
    public function testInstanceOf()
    {
        $dualHandler = new MigratingSessionHandler($this->createStub(\SessionHandlerInterface::class), $this->createStub(\SessionHandlerInterface::class));

        $this->assertInstanceOf(\SessionHandlerInterface::class, $dualHandler);
        $this->assertInstanceOf(\SessionUpdateTimestampHandlerInterface::class, $dualHandler);
    }

    public function testClose()
    {
        $currentHandler = $this->createMock(\SessionHandlerInterface::class);
        $currentHandler->expects($this->once())
            ->method('close')
            ->willReturn(true);

        $writeOnlyHandler = $this->createMock(\SessionHandlerInterface::class);
        $writeOnlyHandler->expects($this->once())
            ->method('close')
            ->willReturn(false);

        $dualHandler = new MigratingSessionHandler($currentHandler, $writeOnlyHandler);
        $result = $dualHandler->close();

        $this->assertTrue($result);
    }

    public function testDestroy()
    {
        $currentHandler = $this->createMock(\SessionHandlerInterface::class);
        $writeOnlyHandler = $this->createMock(\SessionHandlerInterface::class);
        $dualHandler = new MigratingSessionHandler($currentHandler, $writeOnlyHandler);
        $dualHandler->open('/path/to/save/location', 'xyz');

        $sessionId = 'xyz';

        $currentHandler->expects($this->once())
            ->method('destroy')
            ->with($sessionId)
            ->willReturn(true);

        $writeOnlyHandler->expects($this->once())
            ->method('destroy')
            ->with($sessionId)
            ->willReturn(false);

        $result = $dualHandler->destroy($sessionId);

        $this->assertTrue($result);
    }

    public function testGc()
    {
        $maxlifetime = 357;

        $currentHandler = $this->createMock(\SessionHandlerInterface::class);
        $currentHandler->expects($this->once())
            ->method('gc')
            ->with($maxlifetime)
            ->willReturn(1);

        $writeOnlyHandler = $this->createMock(\SessionHandlerInterface::class);
        $writeOnlyHandler->expects($this->once())
            ->method('gc')
            ->with($maxlifetime)
            ->willReturn(false);

        $dualHandler = new MigratingSessionHandler($currentHandler, $writeOnlyHandler);
        $this->assertSame(1, $dualHandler->gc($maxlifetime));
    }

    public function testOpen()
    {
        $savePath = '/path/to/save/location';
        $sessionName = 'xyz';

        $currentHandler = $this->createMock(\SessionHandlerInterface::class);
        $currentHandler->expects($this->once())
            ->method('open')
            ->with($savePath, $sessionName)
            ->willReturn(true);

        $writeOnlyHandler = $this->createMock(\SessionHandlerInterface::class);
        $writeOnlyHandler->expects($this->once())
            ->method('open')
            ->with($savePath, $sessionName)
            ->willReturn(false);

        $dualHandler = new MigratingSessionHandler($currentHandler, $writeOnlyHandler);
        $result = $dualHandler->open($savePath, $sessionName);

        $this->assertTrue($result);
    }

    public function testRead()
    {
        $sessionId = 'xyz';
        $readValue = 'something';

        $currentHandler = $this->createMock(\SessionHandlerInterface::class);
        $currentHandler->expects($this->once())
            ->method('read')
            ->with($sessionId)
            ->willReturn($readValue);

        $writeOnlyHandler = $this->createMock(\SessionHandlerInterface::class);
        $writeOnlyHandler->expects($this->never())
            ->method('read')
            ->with($this->any());

        $dualHandler = new MigratingSessionHandler($currentHandler, $writeOnlyHandler);
        $result = $dualHandler->read($sessionId);

        $this->assertSame($readValue, $result);
    }

    public function testWrite()
    {
        $sessionId = 'xyz';
        $data = 'my-serialized-data';

        $currentHandler = $this->createMock(\SessionHandlerInterface::class);
        $currentHandler->expects($this->once())
            ->method('write')
            ->with($sessionId, $data)
            ->willReturn(true);

        $writeOnlyHandler = $this->createMock(\SessionHandlerInterface::class);
        $writeOnlyHandler->expects($this->once())
            ->method('write')
            ->with($sessionId, $data)
            ->willReturn(false);

        $dualHandler = new MigratingSessionHandler($currentHandler, $writeOnlyHandler);
        $result = $dualHandler->write($sessionId, $data);

        $this->assertTrue($result);
    }

    public function testValidateId()
    {
        $sessionId = 'xyz';
        $readValue = 'something';

        $currentHandler = $this->createMock(\SessionHandlerInterface::class);
        $currentHandler->expects($this->once())
            ->method('read')
            ->with($sessionId)
            ->willReturn($readValue);

        $writeOnlyHandler = $this->createMock(\SessionHandlerInterface::class);
        $writeOnlyHandler->expects($this->never())
            ->method('read')
            ->with($this->any());

        $dualHandler = new MigratingSessionHandler($currentHandler, $writeOnlyHandler);
        $result = $dualHandler->validateId($sessionId);

        $this->assertTrue($result);
    }

    public function testUpdateTimestamp()
    {
        $sessionId = 'xyz';
        $data = 'my-serialized-data';

        $currentHandler = $this->createMock(\SessionHandlerInterface::class);
        $currentHandler->expects($this->once())
            ->method('write')
            ->with($sessionId, $data)
            ->willReturn(true);

        $writeOnlyHandler = $this->createMock(\SessionHandlerInterface::class);
        $writeOnlyHandler->expects($this->once())
            ->method('write')
            ->with($sessionId, $data)
            ->willReturn(false);

        $dualHandler = new MigratingSessionHandler($currentHandler, $writeOnlyHandler);
        $result = $dualHandler->updateTimestamp($sessionId, $data);

        $this->assertTrue($result);
    }
}
