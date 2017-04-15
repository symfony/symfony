<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Flag\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Flag\Flag;
use Symfony\Component\Flag\Tests\Fixtures\Bar;
use Symfony\Component\Flag\Tests\Fixtures\Foo;

/**
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
class FlagTest extends TestCase
{
    /**
     * @dataProvider provideBitfields
     */
    public function testSetAndGet($bitfield)
    {
        $flag = (new Flag())->set($bitfield);

        $this->assertEquals($bitfield, $flag->get());
    }

    public function provideBitfields()
    {
        return array(
            array(0),
            array(1),
            array(2),
            array(4),
            array(1023),
            array(1024),
            array(E_ALL),
            array(PHP_INT_MAX),
        );
    }

    /**
     * @expectedException \Symfony\Component\Flag\Exception\InvalidArgumentException
     * @expectedExceptionMessage Bitfield must be an integer.
     */
    public function testSetNotIntBitfield()
    {
        (new Flag())->set('a');
    }

    /**
     * @expectedException \Symfony\Component\Flag\Exception\InvalidArgumentException
     * @expectedExceptionMessage Bitfield must not exceed integer max limit.
     */
    public function testSetToBigBitfield()
    {
        (new Flag())->set(PHP_INT_MAX * 2);
    }

    public function testGetIterator()
    {
        $flag = new Flag(Foo::class, 'FLAG_', Bar::FLAG_A);

        $flags = $flag->getIterator(false);
        foreach (Foo::getPrefixedFlags() as $expected) {
            $this->assertArrayHasKey($expected[0], $flags);
        }

        $flags = $flag->getIterator();
        $this->assertArrayHasKey(Foo::FLAG_A, $flags);
        $this->assertArrayNotHasKey(Foo::FLAG_B, $flags);
        $this->assertArrayNotHasKey(Foo::FLAG_C, $flags);
    }

    /**
     * @dataProvider provideToString
     */
    public function testToString($from, $prefix, $bitfield, $expected)
    {
        $flag = new Flag($from, $prefix, $bitfield);

        $this->assertEquals($expected, (string) $flag);
    }

    public function provideToString()
    {
        return array(
            array(null, 'E_', E_ERROR, 'E_* [bin: 1] [dec: 1] [E_*: ERROR]'),
            array(Foo::class, '', 0, 'Foo [bin: 0] [dec: 0] [flags: ]'),
            array(Foo::class, '', Foo::FLAG_A, 'Foo [bin: 1] [dec: 1] [flags: FLAG_A]'),
            array(Foo::class, '', Foo::FLAG_A | Foo::FLAG_B, 'Foo [bin: 11] [dec: 3] [flags: FLAG_A | FLAG_B]'),
            array(Foo::class, 'FLAG_', 0, 'Foo::FLAG_* [bin: 0] [dec: 0] [FLAG_*: ]'),
            array(Foo::class, 'FLAG_', Foo::FLAG_A, 'Foo::FLAG_* [bin: 1] [dec: 1] [FLAG_*: A]'),
            array(Foo::class, 'FLAG_', Foo::FLAG_A | Foo::FLAG_B, 'Foo::FLAG_* [bin: 11] [dec: 3] [FLAG_*: A | B]'),
        );
    }

    public function testAdd()
    {
        $flag = new Flag(Foo::class, 'FLAG_');

        $flag->add(Foo::FLAG_A);
        $this->assertEquals(Foo::FLAG_A, $flag->get());

        $flag->add(Foo::FLAG_B);
        $this->assertEquals(Foo::FLAG_A | Foo::FLAG_B, $flag->get());

        $this->assertNotEquals(Foo::FLAG_C, $flag->get());
    }

    public function testAddStandalone()
    {
        $flag = new Flag();

        $flag->add(1);
        $this->assertEquals(1, $flag->get());

        $flag->add(2);
        $this->assertEquals(1 | 2, $flag->get());

        $this->assertNotEquals(4, $flag->get());
    }

    public function testRemove()
    {
        $flag = new Flag(Foo::class, 'FLAG_', Foo::FLAG_A | Foo::FLAG_B);

        $this->assertEquals(Foo::FLAG_A | Foo::FLAG_B, $flag->get());
        $flag->remove(Foo::FLAG_B);
        $this->assertEquals(Foo::FLAG_A, $flag->get());
    }

    public function testHas()
    {
        $flag = new Flag(Foo::class, 'FLAG_', Foo::FLAG_A | Foo::FLAG_B);

        $this->assertTrue($flag->has(Foo::FLAG_A));
        $this->assertTrue($flag->has(Foo::FLAG_B));
        $this->assertFalse($flag->has(Foo::FLAG_C));
    }
}
