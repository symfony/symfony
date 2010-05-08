<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\OutputEscaper;

use Symfony\Components\OutputEscaper\Escaper;

class ArrayDecoratorTest extends \PHPUnit_Framework_TestCase
{
    static protected $escaped;

    static public function setUpBeforeClass()
    {
        $a = array('<strong>escaped!</strong>', 1, null, array(2, '<strong>escaped!</strong>'));

        self::$escaped = Escaper::escape('entities', $a);
    }

    public function testGetRaw()
    {
        $this->assertEquals('<strong>escaped!</strong>', self::$escaped->getRaw(0), '->getRaw() returns the raw value');
    }

    public function testArrayAccessInterface()
    {
        $this->assertEquals('&lt;strong&gt;escaped!&lt;/strong&gt;', self::$escaped[0], 'The escaped object behaves like an array');
        $this->assertNull(self::$escaped[2], 'The escaped object behaves like an array');
        $this->assertEquals('&lt;strong&gt;escaped!&lt;/strong&gt;', self::$escaped[3][1], 'The escaped object behaves like an array');

        $this->assertTrue(isset(self::$escaped[1]), 'The escaped object behaves like an array (isset)');

        try {
            unset(self::$escaped[0]);

            $this->fail('The escaped object is read only (unset)');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\LogicException', $e, 'The escaped object is read only (unset)');
            $this->assertEquals('Cannot unset values.', $e->getMessage(), 'The escaped object is read only (unset)');
        }

        try {
            self::$escaped[0] = 12;

            $this->fail('The escaped object is read only (set)');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\LogicException', $e, 'The escaped object is read only (set)');
            $this->assertEquals('Cannot set values.', $e->getMessage(), 'The escaped object is read only (set)');
        }
    }

    public function testIteratorInterface()
    {
        foreach (self::$escaped as $key => $value) {
            switch ($key) {
                case 0:
                    $this->assertEquals('&lt;strong&gt;escaped!&lt;/strong&gt;', $value, 'The escaped object behaves like an array');
                    break;
                case 1:
                    $this->assertEquals(1, $value, 'The escaped object behaves like an array');
                    break;
                case 2:
                    $this->assertNull($value, 'The escaped object behaves like an array');
                    break;
                case 3:
                    break;
                default:
                    $this->fail('The escaped object behaves like an array');
            }
        }
    }

    public function testCountableInterface()
    {
        $this->assertEquals(4, count(self::$escaped), 'The escaped object implements the Countable interface');
    }
}
