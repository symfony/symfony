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
        $this->assertEquals('&lt;strong&gt;escaped!&lt;/strong&gt;', self::$escaped->getTitle(), 'The escaped object behaves like the real object');

        $array = self::$escaped->getTitles();
        $this->assertEquals('&lt;strong&gt;escaped!&lt;/strong&gt;', $array[2], 'The escaped object behaves like the real object');
    }

    public function testMagicToString()
    {
        $this->assertEquals('&lt;strong&gt;escaped!&lt;/strong&gt;', self::$escaped->__toString(), 'The escaped object behaves like the real object');
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
