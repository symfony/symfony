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

class ObjectDecoratorTest extends \PHPUnit_Framework_TestCase
{
  static protected $escaped;

  static public function setUpBeforeClass()
  {
    $object = new OutputEscaperTest();

    self::$escaped = Escaper::escape('entities', $object);
  }

  public function testGenericBehavior()
  {
    $this->assertEquals(self::$escaped->getTitle(), '&lt;strong&gt;escaped!&lt;/strong&gt;', 'The escaped object behaves like the real object');

    $array = self::$escaped->getTitles();
    $this->assertEquals($array[2], '&lt;strong&gt;escaped!&lt;/strong&gt;', 'The escaped object behaves like the real object');
  }

  public function testMagicToString()
  {
    $this->assertEquals(self::$escaped->__toString(), '&lt;strong&gt;escaped!&lt;/strong&gt;', 'The escaped object behaves like the real object');
  }
}

class OutputEscaperTest
{
  public function __toString()
  {
    return $this->getTitle();
  }

  public function getTitle()
  {
    return '<strong>escaped!</strong>';
  }

  public function getTitles()
  {
    return array(1, 2, '<strong>escaped!</strong>');
  }
}
