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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\MarshallingSessionHandler;

/**
 * @author Ahmed TAILOULOUTE <ahmed.tailouloute@gmail.com>
 */
class MarshallingSessionHandlerTest extends TestCase
{
    protected MockObject&\SessionHandlerInterface $handler;
    protected MockObject&MarshallerInterface $marshaller;

    protected function setUp(): void
    {
        $this->marshaller = $this->createMock(MarshallerInterface::class);
        $this->handler = $this->createMock(AbstractSessionHandler::class);
    }

    public function testOpen()
    {
        $marshallingSessionHandler = new MarshallingSessionHandler($this->handler, $this->marshaller);

        $this->handler->expects($this->once())->method('open')
            ->with('path', 'name')->willReturn(true);

        $marshallingSessionHandler->open('path', 'name');
    }

    public function testClose()
    {
        $marshallingSessionHandler = new MarshallingSessionHandler($this->handler, $this->marshaller);

        $this->handler->expects($this->once())->method('close')->willReturn(true);

        $this->assertTrue($marshallingSessionHandler->close());
    }

    public function testDestroy()
    {
        $marshallingSessionHandler = new MarshallingSessionHandler($this->handler, $this->marshaller);

        $this->handler->expects($this->once())->method('destroy')
            ->with('session_id')->willReturn(true);

        $marshallingSessionHandler->destroy('session_id');
    }

    public function testGc()
    {
        $marshallingSessionHandler = new MarshallingSessionHandler($this->handler, $this->marshaller);

        $this->handler->expects($this->once())->method('gc')
            ->with(4711)->willReturn(1);

        $marshallingSessionHandler->gc(4711);
    }

    public function testRead()
    {
        $marshallingSessionHandler = new MarshallingSessionHandler($this->handler, $this->marshaller);

        $this->handler->expects($this->once())->method('read')->with('session_id')
            ->willReturn('data');
        $this->marshaller->expects($this->once())->method('unmarshall')->with('data')
            ->willReturn('unmarshalled_data')
        ;

        $result = $marshallingSessionHandler->read('session_id');
        $this->assertEquals('unmarshalled_data', $result);
    }

    public function testWrite()
    {
        $marshallingSessionHandler = new MarshallingSessionHandler($this->handler, $this->marshaller);

        $this->marshaller->expects($this->once())->method('marshall')
            ->with(['data' => 'data'], [])
            ->willReturn(['data' => 'marshalled_data']);

        $this->handler->expects($this->once())->method('write')
            ->with('session_id', 'marshalled_data')
        ;

        $marshallingSessionHandler->write('session_id', 'data');
    }

    public function testValidateId()
    {
        $marshallingSessionHandler = new MarshallingSessionHandler($this->handler, $this->marshaller);

        $this->handler->expects($this->once())->method('validateId')
            ->with('session_id')->willReturn(true);

        $marshallingSessionHandler->validateId('session_id');
    }

    public function testUpdateTimestamp()
    {
        $marshallingSessionHandler = new MarshallingSessionHandler($this->handler, $this->marshaller);

        $this->handler->expects($this->once())->method('updateTimestamp')
            ->with('session_id', 'data')->willReturn(true);

        $marshallingSessionHandler->updateTimestamp('session_id', 'data');
    }
}
