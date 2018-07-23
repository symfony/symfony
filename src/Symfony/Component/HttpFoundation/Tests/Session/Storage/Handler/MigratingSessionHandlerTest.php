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

    protected function setUp()
    {
        $this->currentHandler = $this->createMock(\SessionHandlerInterface::class);
        $this->writeOnlyHandler = $this->createMock(\SessionHandlerInterface::class);

        $this->dualHandler = new MigratingSessionHandler($this->currentHandler, $this->writeOnlyHandler);
    }

    public function testInstanceOf()
    {
        $this->assertInstanceOf(\SessionHandlerInterface::class, $this->dualHandler);
        $this->assertInstanceOf(\SessionUpdateTimestampHandlerInterface::class, $this->dualHandler);
    }

    public function testClose()
    {
        $this->currentHandler->expects($this->once())
            ->method('close')
            ->will($this->returnValue(true));

        $this->writeOnlyHandler->expects($this->once())
            ->method('close')
            ->will($this->returnValue(false));

        $result = $this->dualHandler->close();

        $this->assertTrue($result);
    }

    public function testDestroy()
    {
        $sessionId = 'xyz';

        $this->currentHandler->expects($this->once())
            ->method('destroy')
            ->with($sessionId)
            ->will($this->returnValue(true));

        $this->writeOnlyHandler->expects($this->once())
            ->method('destroy')
            ->with($sessionId)
            ->will($this->returnValue(false));

        $result = $this->dualHandler->destroy($sessionId);

        $this->assertTrue($result);
    }

    public function testGc()
    {
        $maxlifetime = 357;

        $this->currentHandler->expects($this->once())
            ->method('gc')
            ->with($maxlifetime)
            ->will($this->returnValue(true));

        $this->writeOnlyHandler->expects($this->once())
            ->method('gc')
            ->with($maxlifetime)
            ->will($this->returnValue(false));

        $result = $this->dualHandler->gc($maxlifetime);
        $this->assertTrue($result);
    }

    public function testOpen()
    {
        $savePath = '/path/to/save/location';
        $sessionName = 'xyz';

        $this->currentHandler->expects($this->once())
            ->method('open')
            ->with($savePath, $sessionName)
            ->will($this->returnValue(true));

        $this->writeOnlyHandler->expects($this->once())
            ->method('open')
            ->with($savePath, $sessionName)
            ->will($this->returnValue(false));

        $result = $this->dualHandler->open($savePath, $sessionName);

        $this->assertTrue($result);
    }

    public function testRead()
    {
        $sessionId = 'xyz';
        $readValue = 'something';

        $this->currentHandler->expects($this->once())
            ->method('read')
            ->with($sessionId)
            ->will($this->returnValue($readValue));

        $this->writeOnlyHandler->expects($this->never())
            ->method('read')
            ->with($this->any());

        $result = $this->dualHandler->read($sessionId);

        $this->assertSame($readValue, $result);
    }

    public function testWrite()
    {
        $sessionId = 'xyz';
        $data = 'my-serialized-data';

        $this->currentHandler->expects($this->once())
            ->method('write')
            ->with($sessionId, $data)
            ->will($this->returnValue(true));

        $this->writeOnlyHandler->expects($this->once())
            ->method('write')
            ->with($sessionId, $data)
            ->will($this->returnValue(false));

        $result = $this->dualHandler->write($sessionId, $data);

        $this->assertTrue($result);
    }

    public function testValidateId()
    {
        $sessionId = 'xyz';
        $readValue = 'something';

        $this->currentHandler->expects($this->once())
            ->method('read')
            ->with($sessionId)
            ->will($this->returnValue($readValue));

        $this->writeOnlyHandler->expects($this->never())
            ->method('read')
            ->with($this->any());

        $result = $this->dualHandler->validateId($sessionId);

        $this->assertTrue($result);
    }

    public function testUpdateTimestamp()
    {
        $sessionId = 'xyz';
        $data = 'my-serialized-data';

        $this->currentHandler->expects($this->once())
            ->method('write')
            ->with($sessionId, $data)
            ->will($this->returnValue(true));

        $this->writeOnlyHandler->expects($this->once())
            ->method('write')
            ->with($sessionId, $data)
            ->will($this->returnValue(false));

        $result = $this->dualHandler->updateTimestamp($sessionId, $data);

        $this->assertTrue($result);
    }
}
