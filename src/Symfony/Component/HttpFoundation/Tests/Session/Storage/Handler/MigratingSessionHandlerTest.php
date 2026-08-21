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
use Symfony\Component\HttpFoundation\Session\Storage\Handler\ClearableSessionHandlerInterface;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\MigratingSessionHandler;
use Symfony\Component\HttpFoundation\Tests\Fixtures\BareSessionHandler;

abstract class ClearableSessionHandlerAndTimestamp extends BareSessionHandler implements \SessionUpdateTimestampHandlerInterface, ClearableSessionHandlerInterface
{
}

abstract class NonClearableSessionHandlerWithTimestamp extends BareSessionHandler implements \SessionUpdateTimestampHandlerInterface
{
}

class MigratingSessionHandlerTest extends TestCase
{
    public function testInstanceOf()
    {
        $dualHandler = new MigratingSessionHandler($this->createStub(BareSessionHandler::class), $this->createStub(BareSessionHandler::class));

        $this->assertInstanceOf(\SessionHandlerInterface::class, $dualHandler);
        $this->assertInstanceOf(\SessionUpdateTimestampHandlerInterface::class, $dualHandler);
    }

    public function testClose()
    {
        $currentHandler = $this->createMock(BareSessionHandler::class);
        $currentHandler->expects($this->once())
            ->method('close')
            ->willReturn(true);

        $writeOnlyHandler = $this->createMock(BareSessionHandler::class);
        $writeOnlyHandler->expects($this->once())
            ->method('close')
            ->willReturn(false);

        $dualHandler = new MigratingSessionHandler($currentHandler, $writeOnlyHandler);
        $result = $dualHandler->close();

        $this->assertTrue($result);
    }

    public function testDestroy()
    {
        $currentHandler = $this->createMock(BareSessionHandler::class);
        $writeOnlyHandler = $this->createMock(BareSessionHandler::class);
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

        $currentHandler = $this->createMock(BareSessionHandler::class);
        $currentHandler->expects($this->once())
            ->method('gc')
            ->with($maxlifetime)
            ->willReturn(1);

        $writeOnlyHandler = $this->createMock(BareSessionHandler::class);
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

        $currentHandler = $this->createMock(BareSessionHandler::class);
        $currentHandler->expects($this->once())
            ->method('open')
            ->with($savePath, $sessionName)
            ->willReturn(true);

        $writeOnlyHandler = $this->createMock(BareSessionHandler::class);
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

        $currentHandler = $this->createMock(BareSessionHandler::class);
        $currentHandler->expects($this->once())
            ->method('read')
            ->with($sessionId)
            ->willReturn($readValue);

        $writeOnlyHandler = $this->createMock(BareSessionHandler::class);
        $writeOnlyHandler->expects($this->never())
            ->method('read');

        $dualHandler = new MigratingSessionHandler($currentHandler, $writeOnlyHandler);
        $result = $dualHandler->read($sessionId);

        $this->assertSame($readValue, $result);
    }

    public function testWrite()
    {
        $sessionId = 'xyz';
        $data = 'my-serialized-data';

        $currentHandler = $this->createMock(BareSessionHandler::class);
        $currentHandler->expects($this->once())
            ->method('write')
            ->with($sessionId, $data)
            ->willReturn(true);

        $writeOnlyHandler = $this->createMock(BareSessionHandler::class);
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

        $currentHandler = $this->createMock(BareSessionHandler::class);
        $currentHandler->expects($this->once())
            ->method('read')
            ->with($sessionId)
            ->willReturn($readValue);

        $writeOnlyHandler = $this->createMock(BareSessionHandler::class);
        $writeOnlyHandler->expects($this->never())
            ->method('read');

        $dualHandler = new MigratingSessionHandler($currentHandler, $writeOnlyHandler);
        $result = $dualHandler->validateId($sessionId);

        $this->assertTrue($result);
    }

    public function testUpdateTimestamp()
    {
        $sessionId = 'xyz';
        $data = 'my-serialized-data';

        $currentHandler = $this->createMock(BareSessionHandler::class);
        $currentHandler->expects($this->once())
            ->method('write')
            ->with($sessionId, $data)
            ->willReturn(true);

        $writeOnlyHandler = $this->createMock(BareSessionHandler::class);
        $writeOnlyHandler->expects($this->once())
            ->method('write')
            ->with($sessionId, $data)
            ->willReturn(false);

        $dualHandler = new MigratingSessionHandler($currentHandler, $writeOnlyHandler);
        $result = $dualHandler->updateTimestamp($sessionId, $data);

        $this->assertTrue($result);
    }

    public function testClearCallsBothHandlersWhenBothAreClearable()
    {
        $currentHandler = $this->createMock(ClearableSessionHandlerAndTimestamp::class);
        $currentHandler->expects($this->once())->method('clear');

        $writeOnlyHandler = $this->createMock(ClearableSessionHandlerAndTimestamp::class);
        $writeOnlyHandler->expects($this->once())->method('clear');

        $dualHandler = new MigratingSessionHandler($currentHandler, $writeOnlyHandler);
        $dualHandler->clear();
    }

    public function testClearCallsOnlyCurrentHandlerWhenWriteOnlyHandlerNotClearable()
    {
        $currentHandler = $this->createMock(ClearableSessionHandlerAndTimestamp::class);
        $currentHandler->expects($this->once())->method('clear');

        $writeOnlyHandler = $this->createStub(NonClearableSessionHandlerWithTimestamp::class);

        $dualHandler = new MigratingSessionHandler($currentHandler, $writeOnlyHandler);
        $dualHandler->clear();
    }

    public function testClearThrowsWhenCurrentHandlerNotClearable()
    {
        $currentHandler = $this->createStub(NonClearableSessionHandlerWithTimestamp::class);
        $writeOnlyHandler = $this->createStub(NonClearableSessionHandlerWithTimestamp::class);

        $dualHandler = new MigratingSessionHandler($currentHandler, $writeOnlyHandler);

        $this->expectException(\LogicException::class);
        $dualHandler->clear();
    }
}
