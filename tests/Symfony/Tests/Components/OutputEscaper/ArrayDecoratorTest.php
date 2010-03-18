<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\OutputEscaper;

require_once __DIR__.'/../../bootstrap.php';

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
    $this->assertEquals(self::$escaped->getRaw(0), '<strong>escaped!</strong>', '->getRaw() returns the raw value');
  }

  public function testArrayAccessInterface()
  {
    $this->assertEquals(self::$escaped[0], '&lt;strong&gt;escaped!&lt;/strong&gt;', 'The escaped object behaves like an array');
    $this->assertEquals(self::$escaped[2], null, 'The escaped object behaves like an array');
    $this->assertEquals(self::$escaped[3][1], '&lt;strong&gt;escaped!&lt;/strong&gt;', 'The escaped object behaves like an array');

    $this->assertTrue(isset(self::$escaped[1]), 'The escaped object behaves like an array (isset)');

    try
    {
      unset(self::$escaped[0]);

      $this->fail('The escaped object is read only (unset)');
    }
    catch (\LogicException $e)
    {
    }

    try
    {
      self::$escaped[0] = 12;

      $this->fail('The escaped object is read only (set)');
    }
    catch (\LogicException $e)
    {
    }
  }

  public function testIteratorInterface()
  {
    foreach (self::$escaped as $key => $value)
    {
      switch ($key)
      {
        case 0:
          $this->assertEquals($value, '&lt;strong&gt;escaped!&lt;/strong&gt;', 'The escaped object behaves like an array');
          break;
        case 1:
          $this->assertEquals($value, 1, 'The escaped object behaves like an array');
          break;
        case 2:
          $this->assertEquals($value, null, 'The escaped object behaves like an array');
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
    $this->assertEquals(count(self::$escaped), 4, 'The escaped object implements the Countable interface');
  }
}
