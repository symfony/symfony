<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Formatter;

use Symfony\Component\Translation\Exception\InvalidArgumentException;
use Symfony\Component\Translation\Exception\LogicException;
use Symfony\Component\Translation\Formatter\ChoiceMessageFormatterInterface;
use Symfony\Component\Translation\Formatter\FallbackFormatter;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;


class FallbackFormatterTest extends \PHPUnit\Framework\TestCase
{
    public function testFormatSame()
    {
        $first = $this->getMockBuilder(MessageFormatterInterface::class)->setMethods(array('format'))->getMock();
        $first
            ->expects($this->once())
            ->method('format')
            ->with('foo', 'en', array(2))
            ->willReturn('foo');

        $second = $this->getMockBuilder(MessageFormatterInterface::class)->setMethods(array('format'))->getMock();
        $second
            ->expects($this->once())
            ->method('format')
            ->with('foo', 'en', array(2))
            ->willReturn('bar');

        $this->assertEquals('bar', (new FallbackFormatter($first, $second))->format('foo', 'en', array(2)));
    }

    public function testFormatDifferent()
    {
        $first = $this->getMockBuilder(MessageFormatterInterface::class)->setMethods(array('format'))->getMock();
        $first
            ->expects($this->once())
            ->method('format')
            ->with('foo', 'en', array(2))
            ->willReturn('new value');

        $second = $this->getMockBuilder(MessageFormatterInterface::class)->setMethods(array('format'))->getMock();
        $second
            ->expects($this->exactly(0))
            ->method('format')
            ->withAnyParameters();

        $this->assertEquals('new value', (new FallbackFormatter($first, $second))->format('foo', 'en', array(2)));
    }

    public function testFormatException()
    {
        $first = $this->getMockBuilder(MessageFormatterInterface::class)->setMethods(array('format'))->getMock();
        $first
            ->expects($this->once())
            ->method('format')
            ->willThrowException(new InvalidArgumentException());

        $second = $this->getMockBuilder(MessageFormatterInterface::class)->setMethods(array('format'))->getMock();
        $second
            ->expects($this->once())
            ->method('format')
            ->with('foo', 'en', array(2))
            ->willReturn('bar');

        $this->assertEquals('bar', (new FallbackFormatter($first, $second))->format('foo', 'en', array(2)));
    }

    public function testFormatExceptionUnknown()
    {
        $first = $this->getMockBuilder(MessageFormatterInterface::class)->setMethods(array('format'))->getMock();
        $first
            ->expects($this->once())
            ->method('format')
            ->willThrowException(new \RuntimeException());

        $second = $this->getMockBuilder(MessageFormatterInterface::class)->setMethods(array('format'))->getMock();
        $second
            ->expects($this->exactly(0))
            ->method('format');

        $this->expectException(\RuntimeException::class);
        $this->assertEquals('bar', (new FallbackFormatter($first, $second))->format('foo', 'en', array(2)));
    }

    public function testChoiceFormatSame()
    {
        $first = $this->getMockBuilder(SuperFormatterInterface::class)->setMethods(array('format', 'choiceFormat'))->getMock();
        $first
            ->expects($this->once())
            ->method('choiceFormat')
            ->with('foo', 1, 'en', array(2))
            ->willReturn('foo');

        $second = $this->getMockBuilder(SuperFormatterInterface::class)->setMethods(array('format', 'choiceFormat'))->getMock();
        $second
            ->expects($this->once())
            ->method('choiceFormat')
            ->with('foo', 1, 'en', array(2))
            ->willReturn('bar');

        $this->assertEquals('bar', (new FallbackFormatter($first, $second))->choiceFormat('foo', 1, 'en', array(2)));
    }

    public function testChoiceFormatDifferent()
    {
        $first = $this->getMockBuilder(SuperFormatterInterface::class)->setMethods(array('format', 'choiceFormat'))->getMock();
        $first
            ->expects($this->once())
            ->method('choiceFormat')
            ->with('foo', 1, 'en', array(2))
            ->willReturn('new value');

        $second = $this->getMockBuilder(SuperFormatterInterface::class)->setMethods(array('format', 'choiceFormat'))->getMock();
        $second
            ->expects($this->exactly(0))
            ->method('choiceFormat')
            ->withAnyParameters()
            ->willReturn('bar');

        $this->assertEquals('new value', (new FallbackFormatter($first, $second))->choiceFormat('foo', 1, 'en', array(2)));
    }

    public function testChoiceFormatException()
    {
        $first = $this->getMockBuilder(SuperFormatterInterface::class)->setMethods(array('format', 'choiceFormat'))->getMock();
        $first
            ->expects($this->once())
            ->method('choiceFormat')
            ->willThrowException(new InvalidArgumentException());

        $second = $this->getMockBuilder(SuperFormatterInterface::class)->setMethods(array('format', 'choiceFormat'))->getMock();
        $second
            ->expects($this->once())
            ->method('choiceFormat')
            ->with('foo', 1, 'en', array(2))
            ->willReturn('bar');

        $this->assertEquals('bar', (new FallbackFormatter($first, $second))->choiceFormat('foo', 1, 'en', array(2)));
    }

    public function testChoiceFormatOnlyFirst()
    {
        // Implements both interfaces
        $first = $this->getMockBuilder(SuperFormatterInterface::class)->setMethods(array('format', 'choiceFormat'))->getMock();
        $first
            ->expects($this->once())
            ->method('choiceFormat')
            ->with('foo', 1, 'en', array(2))
            ->willReturn('bar');

        // Implements only one interface
        $second = $this->getMockBuilder(MessageFormatterInterface::class)->setMethods(array('format'))->getMock();
        $second
            ->expects($this->exactly(0))
            ->method('format')
            ->withAnyParameters()
            ->willReturn('error');

        $this->assertEquals('bar', (new FallbackFormatter($first, $second))->choiceFormat('foo', 1, 'en', array(2)));
    }

    public function testChoiceFormatOnlySecond()
    {
        // Implements only one interface
        $first = $this->getMockBuilder(MessageFormatterInterface::class)->setMethods(array('format'))->getMock();
        $first
            ->expects($this->exactly(0))
            ->method('format')
            ->withAnyParameters()
            ->willReturn('error');

        // Implements both interfaces
        $second = $this->getMockBuilder(SuperFormatterInterface::class)->setMethods(array('format', 'choiceFormat'))->getMock();
        $second
            ->expects($this->once())
            ->method('choiceFormat')
            ->with('foo', 1, 'en', array(2))
            ->willReturn('bar');

        $this->assertEquals('bar', (new FallbackFormatter($first, $second))->choiceFormat('foo', 1, 'en', array(2)));
    }

    public function testChoiceFormatNoChoiceFormat()
    {
        // Implements only one interface
        $first = $this->getMockBuilder(MessageFormatterInterface::class)->setMethods(array('format'))->getMock();
        $first
            ->expects($this->exactly(0))
            ->method('format');

        // Implements both interfaces
        $second = $this->getMockBuilder(MessageFormatterInterface::class)->setMethods(array('format'))->getMock();
        $second
            ->expects($this->exactly(0))
            ->method('format');

        $this->expectException(LogicException::class);
        $this->assertEquals('bar', (new FallbackFormatter($first, $second))->choiceFormat('foo', 1, 'en', array(2)));
    }
}

interface SuperFormatterInterface extends MessageFormatterInterface, ChoiceMessageFormatterInterface
{
}
