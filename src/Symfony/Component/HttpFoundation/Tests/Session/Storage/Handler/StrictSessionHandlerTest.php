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
    public function testOpen()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $handler->expects($this->once())->method('open')
            ->with('path', 'name')->willReturn(true);
        $proxy = new StrictSessionHandler($handler);

        $this->assertInstanceOf(\SessionUpdateTimestampHandlerInterface::class, $proxy);
        $this->assertInstanceOf(AbstractSessionHandler::class, $proxy);
        $this->assertTrue($proxy->open('path', 'name'));
    }

    public function testCloseSession()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $handler->expects($this->once())->method('close')
            ->willReturn(true);
        $proxy = new StrictSessionHandler($handler);

        $this->assertTrue($proxy->close());
    }

    public function testValidateIdOK()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $handler->expects($this->once())->method('read')
            ->with('id')->willReturn('data');
        $proxy = new StrictSessionHandler($handler);

        $this->assertTrue($proxy->validateId('id'));
    }

    public function testValidateIdKO()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $handler->expects($this->once())->method('read')
            ->with('id')->willReturn('');
        $proxy = new StrictSessionHandler($handler);

        $this->assertFalse($proxy->validateId('id'));
    }

    public function testRead()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $handler->expects($this->once())->method('read')
            ->with('id')->willReturn('data');
        $proxy = new StrictSessionHandler($handler);

        $this->assertSame('data', $proxy->read('id'));
    }

    public function testReadWithValidateIdOK()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $handler->expects($this->once())->method('read')
            ->with('id')->willReturn('data');
        $proxy = new StrictSessionHandler($handler);

        $this->assertTrue($proxy->validateId('id'));
        $this->assertSame('data', $proxy->read('id'));
    }

    public function testReadWithValidateIdMismatch()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $handler->expects($this->exactly(2))->method('read')
            ->willReturnCallback(function (...$args) {
                static $series = [
                    [['id1'], 'data1'],
                    [['id2'], 'data2'],
                ];

                [$expectedArgs, $return] = array_shift($series);
                $this->assertSame($expectedArgs, $args);

                return $return;
            })
        ;
        $proxy = new StrictSessionHandler($handler);

        $this->assertTrue($proxy->validateId('id1'));
        $this->assertSame('data2', $proxy->read('id2'));
    }

    public function testUpdateTimestamp()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $handler->expects($this->once())->method('write')
            ->with('id', 'data')->willReturn(true);
        $proxy = new StrictSessionHandler($handler);

        $this->assertTrue($proxy->updateTimestamp('id', 'data'));
    }

    public function testWrite()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $handler->expects($this->once())->method('write')
            ->with('id', 'data')->willReturn(true);
        $proxy = new StrictSessionHandler($handler);

        $this->assertTrue($proxy->write('id', 'data'));
    }

    public function testWriteEmptyNewSession()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $handler->expects($this->once())->method('read')
            ->with('id')->willReturn('');
        $handler->expects($this->never())->method('write');
        $handler->expects($this->once())->method('destroy')->willReturn(true);
        $proxy = new StrictSessionHandler($handler);
        $proxy->open('path', 'name');

        $this->assertFalse($proxy->validateId('id'));
        $this->assertSame('', $proxy->read('id'));
        $this->assertTrue($proxy->write('id', ''));
    }

    public function testWriteEmptyExistingSession()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $handler->expects($this->once())->method('read')
            ->with('id')->willReturn('data');
        $handler->expects($this->never())->method('write');
        $handler->expects($this->once())->method('destroy')->willReturn(true);
        $proxy = new StrictSessionHandler($handler);
        $proxy->open('path', 'name');

        $this->assertSame('data', $proxy->read('id'));
        $this->assertTrue($proxy->write('id', ''));
    }

    public function testDestroy()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $handler->expects($this->once())->method('destroy')
            ->with('id')->willReturn(true);
        $proxy = new StrictSessionHandler($handler);
        $proxy->open('path', 'name');

        $this->assertTrue($proxy->destroy('id'));
    }

    public function testDestroyNewSession()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $handler->expects($this->once())->method('read')
            ->with('id')->willReturn('');
        $handler->expects($this->once())->method('destroy')->willReturn(true);
        $proxy = new StrictSessionHandler($handler);
        $proxy->open('path', 'name');

        $this->assertSame('', $proxy->read('id'));
        $this->assertTrue($proxy->destroy('id'));
    }

    public function testDestroyNonEmptyNewSession()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $handler->expects($this->once())->method('read')
            ->with('id')->willReturn('');
        $handler->expects($this->once())->method('write')
            ->with('id', 'data')->willReturn(true);
        $handler->expects($this->once())->method('destroy')
            ->with('id')->willReturn(true);
        $proxy = new StrictSessionHandler($handler);
        $proxy->open('path', 'name');

        $this->assertSame('', $proxy->read('id'));
        $this->assertTrue($proxy->write('id', 'data'));
        $this->assertTrue($proxy->destroy('id'));
    }

    public function testGc()
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $handler->expects($this->once())->method('gc')
            ->with(123)->willReturn(1);
        $proxy = new StrictSessionHandler($handler);

        $this->assertSame(1, $proxy->gc(123));
    }
}
