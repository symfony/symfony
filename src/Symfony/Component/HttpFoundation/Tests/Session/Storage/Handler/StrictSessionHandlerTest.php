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
        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->expects(self::once())->method('open')
            ->with('path', 'name')->willReturn(true);
        $proxy = new StrictSessionHandler($handler);

        self::assertInstanceOf(\SessionUpdateTimestampHandlerInterface::class, $proxy);
        self::assertInstanceOf(AbstractSessionHandler::class, $proxy);
        self::assertTrue($proxy->open('path', 'name'));
    }

    public function testCloseSession()
    {
        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->expects(self::once())->method('close')
            ->willReturn(true);
        $proxy = new StrictSessionHandler($handler);

        self::assertTrue($proxy->close());
    }

    public function testValidateIdOK()
    {
        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->expects(self::once())->method('read')
            ->with('id')->willReturn('data');
        $proxy = new StrictSessionHandler($handler);

        self::assertTrue($proxy->validateId('id'));
    }

    public function testValidateIdKO()
    {
        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->expects(self::once())->method('read')
            ->with('id')->willReturn('');
        $proxy = new StrictSessionHandler($handler);

        self::assertFalse($proxy->validateId('id'));
    }

    public function testRead()
    {
        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->expects(self::once())->method('read')
            ->with('id')->willReturn('data');
        $proxy = new StrictSessionHandler($handler);

        self::assertSame('data', $proxy->read('id'));
    }

    public function testReadWithValidateIdOK()
    {
        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->expects(self::once())->method('read')
            ->with('id')->willReturn('data');
        $proxy = new StrictSessionHandler($handler);

        self::assertTrue($proxy->validateId('id'));
        self::assertSame('data', $proxy->read('id'));
    }

    public function testReadWithValidateIdMismatch()
    {
        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->expects(self::exactly(2))->method('read')
            ->withConsecutive(['id1'], ['id2'])
            ->will(self::onConsecutiveCalls('data1', 'data2'));
        $proxy = new StrictSessionHandler($handler);

        self::assertTrue($proxy->validateId('id1'));
        self::assertSame('data2', $proxy->read('id2'));
    }

    public function testUpdateTimestamp()
    {
        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->expects(self::once())->method('write')
            ->with('id', 'data')->willReturn(true);
        $proxy = new StrictSessionHandler($handler);

        self::assertTrue($proxy->updateTimestamp('id', 'data'));
    }

    public function testWrite()
    {
        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->expects(self::once())->method('write')
            ->with('id', 'data')->willReturn(true);
        $proxy = new StrictSessionHandler($handler);

        self::assertTrue($proxy->write('id', 'data'));
    }

    public function testWriteEmptyNewSession()
    {
        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->expects(self::once())->method('read')
            ->with('id')->willReturn('');
        $handler->expects(self::never())->method('write');
        $handler->expects(self::once())->method('destroy')->willReturn(true);
        $proxy = new StrictSessionHandler($handler);

        self::assertFalse($proxy->validateId('id'));
        self::assertSame('', $proxy->read('id'));
        self::assertTrue($proxy->write('id', ''));
    }

    public function testWriteEmptyExistingSession()
    {
        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->expects(self::once())->method('read')
            ->with('id')->willReturn('data');
        $handler->expects(self::never())->method('write');
        $handler->expects(self::once())->method('destroy')->willReturn(true);
        $proxy = new StrictSessionHandler($handler);

        self::assertSame('data', $proxy->read('id'));
        self::assertTrue($proxy->write('id', ''));
    }

    public function testDestroy()
    {
        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->expects(self::once())->method('destroy')
            ->with('id')->willReturn(true);
        $proxy = new StrictSessionHandler($handler);

        self::assertTrue($proxy->destroy('id'));
    }

    public function testDestroyNewSession()
    {
        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->expects(self::once())->method('read')
            ->with('id')->willReturn('');
        $handler->expects(self::once())->method('destroy')->willReturn(true);
        $proxy = new StrictSessionHandler($handler);

        self::assertSame('', $proxy->read('id'));
        self::assertTrue($proxy->destroy('id'));
    }

    public function testDestroyNonEmptyNewSession()
    {
        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->expects(self::once())->method('read')
            ->with('id')->willReturn('');
        $handler->expects(self::once())->method('write')
            ->with('id', 'data')->willReturn(true);
        $handler->expects(self::once())->method('destroy')
            ->with('id')->willReturn(true);
        $proxy = new StrictSessionHandler($handler);

        self::assertSame('', $proxy->read('id'));
        self::assertTrue($proxy->write('id', 'data'));
        self::assertTrue($proxy->destroy('id'));
    }

    public function testGc()
    {
        $handler = self::createMock(\SessionHandlerInterface::class);
        $handler->expects(self::once())->method('gc')
            ->with(123)->willReturn(1);
        $proxy = new StrictSessionHandler($handler);

        self::assertSame(1, $proxy->gc(123));
    }
}
