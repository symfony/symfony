<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Tests\Parser;

use PHPUnit\Framework\TestCase;
use Symfony\Component\CssSelector\Parser\Reader;

class ReaderTest extends TestCase
{
    public function testIsEOF()
    {
        $reader = new Reader('');
        self::assertTrue($reader->isEOF());

        $reader = new Reader('hello');
        self::assertFalse($reader->isEOF());

        $this->assignPosition($reader, 2);
        self::assertFalse($reader->isEOF());

        $this->assignPosition($reader, 5);
        self::assertTrue($reader->isEOF());
    }

    public function testGetRemainingLength()
    {
        $reader = new Reader('hello');
        self::assertEquals(5, $reader->getRemainingLength());

        $this->assignPosition($reader, 2);
        self::assertEquals(3, $reader->getRemainingLength());

        $this->assignPosition($reader, 5);
        self::assertEquals(0, $reader->getRemainingLength());
    }

    public function testGetSubstring()
    {
        $reader = new Reader('hello');
        self::assertEquals('he', $reader->getSubstring(2));
        self::assertEquals('el', $reader->getSubstring(2, 1));

        $this->assignPosition($reader, 2);
        self::assertEquals('ll', $reader->getSubstring(2));
        self::assertEquals('lo', $reader->getSubstring(2, 1));
    }

    public function testGetOffset()
    {
        $reader = new Reader('hello');
        self::assertEquals(2, $reader->getOffset('ll'));
        self::assertFalse($reader->getOffset('w'));

        $this->assignPosition($reader, 2);
        self::assertEquals(0, $reader->getOffset('ll'));
        self::assertFalse($reader->getOffset('he'));
    }

    public function testFindPattern()
    {
        $reader = new Reader('hello');

        self::assertFalse($reader->findPattern('/world/'));
        self::assertEquals(['hello', 'h'], $reader->findPattern('/^([a-z]).*/'));

        $this->assignPosition($reader, 2);
        self::assertFalse($reader->findPattern('/^h.*/'));
        self::assertEquals(['llo'], $reader->findPattern('/^llo$/'));
    }

    public function testMoveForward()
    {
        $reader = new Reader('hello');
        self::assertEquals(0, $reader->getPosition());

        $reader->moveForward(2);
        self::assertEquals(2, $reader->getPosition());
    }

    public function testToEnd()
    {
        $reader = new Reader('hello');
        $reader->moveToEnd();
        self::assertTrue($reader->isEOF());
    }

    private function assignPosition(Reader $reader, int $value)
    {
        $position = new \ReflectionProperty($reader, 'position');
        $position->setAccessible(true);
        $position->setValue($reader, $value);
    }
}
