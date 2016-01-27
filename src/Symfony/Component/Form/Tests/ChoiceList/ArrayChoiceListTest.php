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

    public function testCreateChoiceListWithoutValueCallbackAndDuplicateFreeToStringChoices()
    {
        $choiceList = new ArrayChoiceList(array(2 => 'foo', 7 => 'bar', 10 => 123));

        $this->assertSame(array('foo', 'bar', '123'), $choiceList->getValues());
        $this->assertSame(array('foo' => 'foo', 'bar' => 'bar', '123' => 123), $choiceList->getChoices());
        $this->assertSame(array('foo' => 2, 'bar' => 7, '123' => 10), $choiceList->getOriginalKeys());
        $this->assertSame(array(1 => 'foo', 2 => 123), $choiceList->getChoicesForValues(array(1 => 'foo', 2 => '123')));
        $this->assertSame(array(1 => 'foo', 2 => '123'), $choiceList->getValuesForChoices(array(1 => 'foo', 2 => 123)));
    }

    public function testCreateChoiceListWithoutValueCallbackAndToStringDuplicates()
    {
        $choiceList = new ArrayChoiceList(array(2 => 'foo', 7 => '123', 10 => 123));

        $this->assertSame(array('0', '1', '2'), $choiceList->getValues());
        $this->assertSame(array('0' => 'foo', '1' => '123', '2' => 123), $choiceList->getChoices());
        $this->assertSame(array('0' => 2, '1' => 7, '2' => 10), $choiceList->getOriginalKeys());
        $this->assertSame(array(1 => 'foo', 2 => 123), $choiceList->getChoicesForValues(array(1 => '0', 2 => '2')));
        $this->assertSame(array(1 => '0', 2 => '2'), $choiceList->getValuesForChoices(array(1 => 'foo', 2 => 123)));
    }

    public function testCreateChoiceListWithoutValueCallbackAndMixedChoices()
    {
        $object = new \stdClass();
        $choiceList = new ArrayChoiceList(array(2 => 'foo', 5 => array(7 => '123'), 10 => $object));

        $this->assertSame(array('0', '1', '2'), $choiceList->getValues());
        $this->assertSame(array('0' => 'foo', '1' => '123', '2' => $object), $choiceList->getChoices());
        $this->assertSame(array('0' => 2, '1' => 7, '2' => 10), $choiceList->getOriginalKeys());
        $this->assertSame(array(1 => 'foo', 2 => $object), $choiceList->getChoicesForValues(array(1 => '0', 2 => '2')));
        $this->assertSame(array(1 => '0', 2 => '2'), $choiceList->getValuesForChoices(array(1 => 'foo', 2 => $object)));
    }

    public function testCreateChoiceListWithGroupedChoices()
    {
        $choiceList = new ArrayChoiceList(array(
            'Group 1' => array('A' => 'a', 'B' => 'b'),
            'Group 2' => array('C' => 'c', 'D' => 'd'),
        ));

        $this->assertSame(array('a', 'b', 'c', 'd'), $choiceList->getValues());
        $this->assertSame(array(
            'Group 1' => array('A' => 'a', 'B' => 'b'),
            'Group 2' => array('C' => 'c', 'D' => 'd'),
        ), $choiceList->getStructuredValues());
        $this->assertSame(array('a' => 'a', 'b' => 'b', 'c' => 'c', 'd' => 'd'), $choiceList->getChoices());
        $this->assertSame(array('a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'), $choiceList->getOriginalKeys());
        $this->assertSame(array(1 => 'a', 2 => 'b'), $choiceList->getChoicesForValues(array(1 => 'a', 2 => 'b')));
        $this->assertSame(array(1 => 'a', 2 => 'b'), $choiceList->getValuesForChoices(array(1 => 'a', 2 => 'b')));
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

    public function testGetChoicesForValuesWithContainingNull()
    {
        $choiceList = new ArrayChoiceList(array('Null' => null));

        $this->assertSame(array(0 => null), $choiceList->getChoicesForValues(array('0')));
    }
}
