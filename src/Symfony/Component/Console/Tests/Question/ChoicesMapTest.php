<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Question;

use Symfony\Component\Console\Question\ChoicesMap;

class ChoicesMapTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ChoicesMap
     */
    private $choicesMap;

    protected function setUp()
    {
        $choices = array('a' => 'foo', 'b' => 'bar', 'baz');
        $this->choicesMap = new ChoicesMap($choices);
    }

    public function testGetChoicePositionFromValue()
    {
        $this->assertEquals(0, $this->choicesMap->getChoicePositionFromValue('a'));
        $this->assertEquals(1, $this->choicesMap->getChoicePositionFromValue('b'));
        $this->assertEquals(2, $this->choicesMap->getChoicePositionFromValue(0));
    }

    public function testGetChoiceTextFromValue()
    {
        $this->assertEquals('foo', $this->choicesMap->getChoiceTextFromValue('a'));
        $this->assertEquals('bar', $this->choicesMap->getChoiceTextFromValue('b'));
        $this->assertEquals('baz', $this->choicesMap->getChoiceTextFromValue(0));
    }

    public function testGetChoiceTextAt()
    {
        $this->assertEquals('baz', $this->choicesMap->getChoiceTextAt(2));
        $this->assertEquals('bar', $this->choicesMap->getChoiceTextAt(1));
        $this->assertEquals('foo', $this->choicesMap->getChoiceTextAt(0));
    }

    public function testGetChoiceValueAt()
    {
        $this->assertEquals(0, $this->choicesMap->getChoiceValueAt(2));
        $this->assertEquals('a', $this->choicesMap->getChoiceValueAt(0));
        $this->assertEquals('b', $this->choicesMap->getChoiceValueAt(1));
    }
}
