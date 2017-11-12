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
use Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\StrictSessionHandler;

class StrictSessionHandlerTest extends TestCase
{
    public function testOpen(): void
    {
        $handler = $this->getMockBuilder('SessionHandlerInterface')->getMock();
        $handler->expects($this->once())->method('open')
            ->with('path', 'name')->willReturn(true);
        $proxy = new StrictSessionHandler($handler);

        $this->assertInstanceof('SessionUpdateTimestampHandlerInterface', $proxy);
        $this->assertInstanceof(AbstractSessionHandler::class, $proxy);
        $this->assertTrue($proxy->open('path', 'name'));
    }

    public function testCloseSession(): void
    {
        $handler = $this->getMockBuilder('SessionHandlerInterface')->getMock();
        $handler->expects($this->once())->method('close')
            ->willReturn(true);
        $proxy = new StrictSessionHandler($handler);

        $this->assertTrue($proxy->close());
    }

    public function testValidateIdOK(): void
    {
        $handler = $this->getMockBuilder('SessionHandlerInterface')->getMock();
        $handler->expects($this->once())->method('read')
            ->with('id')->willReturn('data');
        $proxy = new StrictSessionHandler($handler);

        $this->assertTrue($proxy->validateId('id'));
    }

    public function testValidateIdKO(): void
    {
        $handler = $this->getMockBuilder('SessionHandlerInterface')->getMock();
        $handler->expects($this->once())->method('read')
            ->with('id')->willReturn('');
        $proxy = new StrictSessionHandler($handler);

        $this->assertFalse($proxy->validateId('id'));
    }

    public function testRead(): void
    {
        $handler = $this->getMockBuilder('SessionHandlerInterface')->getMock();
        $handler->expects($this->once())->method('read')
            ->with('id')->willReturn('data');
        $proxy = new StrictSessionHandler($handler);

        $this->assertSame('data', $proxy->read('id'));
    }

    public function testReadWithValidateIdOK(): void
    {
        $handler = $this->getMockBuilder('SessionHandlerInterface')->getMock();
        $handler->expects($this->once())->method('read')
            ->with('id')->willReturn('data');
        $proxy = new StrictSessionHandler($handler);

        $this->assertTrue($proxy->validateId('id'));
        $this->assertSame('data', $proxy->read('id'));
    }

    public function testReadWithValidateIdMismatch(): void
    {
        $handler = $this->getMockBuilder('SessionHandlerInterface')->getMock();
        $handler->expects($this->exactly(2))->method('read')
            ->withConsecutive(array('id1'), array('id2'))
            ->will($this->onConsecutiveCalls('data1', 'data2'));
        $proxy = new StrictSessionHandler($handler);

        $this->assertTrue($proxy->validateId('id1'));
        $this->assertSame('data2', $proxy->read('id2'));
    }

    public function testUpdateTimestamp(): void
    {
        $handler = $this->getMockBuilder('SessionHandlerInterface')->getMock();
        $handler->expects($this->once())->method('write')
            ->with('id', 'data')->willReturn(true);
        $proxy = new StrictSessionHandler($handler);

        $this->assertTrue($proxy->updateTimestamp('id', 'data'));
    }

    public function testWrite(): void
    {
        $handler = $this->getMockBuilder('SessionHandlerInterface')->getMock();
        $handler->expects($this->once())->method('write')
            ->with('id', 'data')->willReturn(true);
        $proxy = new StrictSessionHandler($handler);

        $this->assertTrue($proxy->write('id', 'data'));
    }

    public function testWriteEmptyNewSession(): void
    {
        $handler = $this->getMockBuilder('SessionHandlerInterface')->getMock();
        $handler->expects($this->once())->method('read')
            ->with('id')->willReturn('');
        $handler->expects($this->never())->method('write');
        $handler->expects($this->never())->method('destroy');
        $proxy = new StrictSessionHandler($handler);

        $this->assertFalse($proxy->validateId('id'));
        $this->assertSame('', $proxy->read('id'));
        $this->assertTrue($proxy->write('id', ''));
    }

    public function testWriteEmptyExistingSession(): void
    {
        $handler = $this->getMockBuilder('SessionHandlerInterface')->getMock();
        $handler->expects($this->once())->method('read')
            ->with('id')->willReturn('data');
        $handler->expects($this->never())->method('write');
        $handler->expects($this->once())->method('destroy')->willReturn(true);
        $proxy = new StrictSessionHandler($handler);

        $this->assertSame('data', $proxy->read('id'));
        $this->assertTrue($proxy->write('id', ''));
    }

    public function testDestroy(): void
    {
        $handler = $this->getMockBuilder('SessionHandlerInterface')->getMock();
        $handler->expects($this->once())->method('destroy')
            ->with('id')->willReturn(true);
        $proxy = new StrictSessionHandler($handler);

        $this->assertTrue($proxy->destroy('id'));
    }

    public function testDestroyNewSession(): void
    {
        $handler = $this->getMockBuilder('SessionHandlerInterface')->getMock();
        $handler->expects($this->once())->method('read')
            ->with('id')->willReturn('');
        $handler->expects($this->never())->method('destroy');
        $proxy = new StrictSessionHandler($handler);

        $this->assertSame('', $proxy->read('id'));
        $this->assertTrue($proxy->destroy('id'));
    }

    public function testDestroyNonEmptyNewSession(): void
    {
        $handler = $this->getMockBuilder('SessionHandlerInterface')->getMock();
        $handler->expects($this->once())->method('read')
            ->with('id')->willReturn('');
        $handler->expects($this->once())->method('write')
            ->with('id', 'data')->willReturn(true);
        $handler->expects($this->once())->method('destroy')
            ->with('id')->willReturn(true);
        $proxy = new StrictSessionHandler($handler);

        $this->assertSame('', $proxy->read('id'));
        $this->assertTrue($proxy->write('id', 'data'));
        $this->assertTrue($proxy->destroy('id'));
    }

    public function testGc(): void
    {
        $handler = $this->getMockBuilder('SessionHandlerInterface')->getMock();
        $handler->expects($this->once())->method('gc')
            ->with(123)->willReturn(true);
        $proxy = new StrictSessionHandler($handler);

        $this->assertTrue($proxy->gc(123));
    }
}
