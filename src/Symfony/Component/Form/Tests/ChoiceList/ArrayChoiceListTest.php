<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\ChoiceList;

use Symfony\Component\Form\ChoiceList\ArrayChoiceList;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ArrayChoiceListTest extends AbstractChoiceListTest
{
    private $object;

    protected function setUp()
    {
        parent::setUp();

        $this->object = new \stdClass();
    }

    protected function createChoiceList()
    {
        return new ArrayChoiceList($this->getChoices());
    }

    protected function getChoices()
    {
        return array(0, 1, '1', 'a', false, true, $this->object);
    }

    protected function getValues()
    {
        return array('0', '1', '2', '3', '4', '5', '6');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\InvalidArgumentException
     */
    public function testFailIfKeyMismatch()
    {
        new ArrayChoiceList(array(0 => 'a', 1 => 'b'), array(1 => 'a', 2 => 'b'));
    }

    public function testCreateChoiceListWithValueCallback()
    {
        $callback = function ($choice) {
            return ':'.$choice;
        };

        $choiceList = new ArrayChoiceList(array(2 => 'foo', 7 => 'bar', 10 => 'baz'), $callback);

        $this->assertSame(array(':foo', ':bar', ':baz'), $choiceList->getValues());
        $this->assertSame(array(':foo' => 'foo', ':bar' => 'bar', ':baz' => 'baz'), $choiceList->getChoices());
        $this->assertSame(array(':foo' => 2, ':bar' => 7, ':baz' => 10), $choiceList->getOriginalKeys());
        $this->assertSame(array(1 => 'foo', 2 => 'baz'), $choiceList->getChoicesForValues(array(1 => ':foo', 2 => ':baz')));
        $this->assertSame(array(1 => ':foo', 2 => ':baz'), $choiceList->getValuesForChoices(array(1 => 'foo', 2 => 'baz')));
    }

    public function testCreateChoiceListWithGroupedChoices()
    {
        $choiceList = new ArrayChoiceList(array(
            'Group 1' => array('A' => 'a', 'B' => 'b'),
            'Group 2' => array('C' => 'c', 'D' => 'd'),
        ));

        $this->assertSame(array('0', '1', '2', '3'), $choiceList->getValues());
        $this->assertSame(array(
            'Group 1' => array('A' => '0', 'B' => '1'),
            'Group 2' => array('C' => '2', 'D' => '3'),
        ), $choiceList->getStructuredValues());
        $this->assertSame(array(0 => 'a', 1 => 'b', 2 => 'c', 3 => 'd'), $choiceList->getChoices());
        $this->assertSame(array(0 => 'A', 1 => 'B', 2 => 'C', 3 => 'D'), $choiceList->getOriginalKeys());
        $this->assertSame(array(1 => 'a', 2 => 'b'), $choiceList->getChoicesForValues(array(1 => '0', 2 => '1')));
        $this->assertSame(array(1 => '0', 2 => '1'), $choiceList->getValuesForChoices(array(1 => 'a', 2 => 'b')));
    }

    public function testCompareChoicesByIdentityByDefault()
    {
        $callback = function ($choice) {
            return $choice->value;
        };

        $obj1 = (object) array('value' => 'value1');
        $obj2 = (object) array('value' => 'value2');

        $choiceList = new ArrayChoiceList(array($obj1, $obj2), $callback);
        $this->assertSame(array(2 => 'value2'), $choiceList->getValuesForChoices(array(2 => $obj2)));
        $this->assertSame(array(2 => 'value2'), $choiceList->getValuesForChoices(array(2 => (object) array('value' => 'value2'))));
    }
}
