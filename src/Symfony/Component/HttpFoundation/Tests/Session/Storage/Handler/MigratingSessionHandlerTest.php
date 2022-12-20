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
    private $dualHandler;
    private $currentHandler;
    private $writeOnlyHandler;

    protected function setUp(): void
    {
        $this->currentHandler = self::createMock(\SessionHandlerInterface::class);
        $this->writeOnlyHandler = self::createMock(\SessionHandlerInterface::class);

        $this->dualHandler = new MigratingSessionHandler($this->currentHandler, $this->writeOnlyHandler);
    }

    public function testInstanceOf()
    {
        self::assertInstanceOf(\SessionHandlerInterface::class, $this->dualHandler);
        self::assertInstanceOf(\SessionUpdateTimestampHandlerInterface::class, $this->dualHandler);
    }

    public function testClose()
    {
        $this->currentHandler->expects(self::once())
            ->method('close')
            ->willReturn(true);

        $this->writeOnlyHandler->expects(self::once())
            ->method('close')
            ->willReturn(false);

        $result = $this->dualHandler->close();

        self::assertTrue($result);
    }

    public function testDestroy()
    {
        $sessionId = 'xyz';

        $this->currentHandler->expects(self::once())
            ->method('destroy')
            ->with($sessionId)
            ->willReturn(true);

        $this->writeOnlyHandler->expects(self::once())
            ->method('destroy')
            ->with($sessionId)
            ->willReturn(false);

        $result = $this->dualHandler->destroy($sessionId);

        self::assertTrue($result);
    }

    public function testGc()
    {
        $maxlifetime = 357;

        $this->currentHandler->expects(self::once())
            ->method('gc')
            ->with($maxlifetime)
            ->willReturn(1);

        $this->writeOnlyHandler->expects(self::once())
            ->method('gc')
            ->with($maxlifetime)
            ->willReturn(false);

        self::assertSame(1, $this->dualHandler->gc($maxlifetime));
    }

    public function testOpen()
    {
        $savePath = '/path/to/save/location';
        $sessionName = 'xyz';

        $this->currentHandler->expects(self::once())
            ->method('open')
            ->with($savePath, $sessionName)
            ->willReturn(true);

        $this->writeOnlyHandler->expects(self::once())
            ->method('open')
            ->with($savePath, $sessionName)
            ->willReturn(false);

        $result = $this->dualHandler->open($savePath, $sessionName);

        self::assertTrue($result);
    }

    public function testRead()
    {
        $sessionId = 'xyz';
        $readValue = 'something';

        $this->currentHandler->expects(self::once())
            ->method('read')
            ->with($sessionId)
            ->willReturn($readValue);

        $this->writeOnlyHandler->expects(self::never())
            ->method('read')
            ->with(self::any());

        $result = $this->dualHandler->read($sessionId);

        self::assertSame($readValue, $result);
    }

    public function testWrite()
    {
        $sessionId = 'xyz';
        $data = 'my-serialized-data';

        $this->currentHandler->expects(self::once())
            ->method('write')
            ->with($sessionId, $data)
            ->willReturn(true);

        $this->writeOnlyHandler->expects(self::once())
            ->method('write')
            ->with($sessionId, $data)
            ->willReturn(false);

        $result = $this->dualHandler->write($sessionId, $data);

        self::assertTrue($result);
    }

    public function testValidateId()
    {
        $sessionId = 'xyz';
        $readValue = 'something';

        $this->currentHandler->expects(self::once())
            ->method('read')
            ->with($sessionId)
            ->willReturn($readValue);

        $this->writeOnlyHandler->expects(self::never())
            ->method('read')
            ->with(self::any());

        $result = $this->dualHandler->validateId($sessionId);

        self::assertTrue($result);
    }

    public function testUpdateTimestamp()
    {
        $sessionId = 'xyz';
        $data = 'my-serialized-data';

        $this->currentHandler->expects(self::once())
            ->method('write')
            ->with($sessionId, $data)
            ->willReturn(true);

        $this->writeOnlyHandler->expects(self::once())
            ->method('write')
            ->with($sessionId, $data)
            ->willReturn(false);

        $result = $this->dualHandler->updateTimestamp($sessionId, $data);

        self::assertTrue($result);
    }
}
