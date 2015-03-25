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

use Symfony\Component\Form\ChoiceList\ArrayKeyChoiceList;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ArrayKeyChoiceListTest extends AbstractChoiceListTest
{
    private $object;

    protected function setUp()
    {
        parent::setUp();

        $this->object = new \stdClass();
    }

    protected function createChoiceList()
    {
        return new ArrayKeyChoiceList($this->getChoices());
    }

    protected function getChoices()
    {
        return array(0, 1, 'a', 'b', '');
    }

    protected function getValues()
    {
        return array('0', '1', 'a', 'b', '');
    }

    public function testUseChoicesAsValuesByDefault()
    {
        $list = new ArrayKeyChoiceList(array(1 => '', 3 => 0, 7 => '1', 10 => 1.23));

        $this->assertSame(array(1 => '', 3 => '0', 7 => '1', 10 => '1.23'), $list->getValues());
    }

    public function testNoChoices()
    {
        $list = new ArrayKeyChoiceList(array());

        $this->assertSame(array(), $list->getValues());
    }

    public function testGetChoicesForValuesConvertsValuesToStrings()
    {
        $this->assertSame(array(0), $this->list->getChoicesForValues(array(0)));
        $this->assertSame(array(0), $this->list->getChoicesForValues(array('0')));
        $this->assertSame(array(1), $this->list->getChoicesForValues(array(1)));
        $this->assertSame(array(1), $this->list->getChoicesForValues(array('1')));
        $this->assertSame(array('a'), $this->list->getChoicesForValues(array('a')));
        $this->assertSame(array('b'), $this->list->getChoicesForValues(array('b')));
        $this->assertSame(array(''), $this->list->getChoicesForValues(array('')));
        // "1" === (string) true
        $this->assertSame(array(1), $this->list->getChoicesForValues(array(true)));
        // "" === (string) false
        $this->assertSame(array(''), $this->list->getChoicesForValues(array(false)));
        // "" === (string) null
        $this->assertSame(array(''), $this->list->getChoicesForValues(array(null)));
        $this->assertSame(array(), $this->list->getChoicesForValues(array(1.23)));
    }

    public function testGetValuesForChoicesConvertsChoicesToArrayKeys()
    {
        $this->assertSame(array('0'), $this->list->getValuesForChoices(array(0)));
        $this->assertSame(array('0'), $this->list->getValuesForChoices(array('0')));
        $this->assertSame(array('1'), $this->list->getValuesForChoices(array(1)));
        $this->assertSame(array('1'), $this->list->getValuesForChoices(array('1')));
        $this->assertSame(array('a'), $this->list->getValuesForChoices(array('a')));
        $this->assertSame(array('b'), $this->list->getValuesForChoices(array('b')));
        // Always cast booleans to 0 and 1, because:
        // array(true => 'Yes', false => 'No') === array(1 => 'Yes', 0 => 'No')
        // see ChoiceTypeTest::testSetDataSingleNonExpandedAcceptsBoolean
        $this->assertSame(array('0'), $this->list->getValuesForChoices(array(false)));
        $this->assertSame(array('1'), $this->list->getValuesForChoices(array(true)));
    }

    /**
     * @dataProvider provideConvertibleChoices
     */
    public function testConvertChoicesIfNecessary(array $choices, array $converted)
    {
        $list = new ArrayKeyChoiceList($choices);

        $this->assertSame($converted, $list->getChoices());
    }

    public function provideConvertibleChoices()
    {
        return array(
            array(array(0), array(0)),
            array(array(1), array(1)),
            array(array('0'), array(0)),
            array(array('1'), array(1)),
            array(array('1.23'), array('1.23')),
            array(array('foobar'), array('foobar')),
            // The default value of choice fields is NULL. It should be treated
            // like the empty value for this choice list type
            array(array(null), array('')),
            array(array(1.23), array('1.23')),
            // Always cast booleans to 0 and 1, because:
            // array(true => 'Yes', false => 'No') === array(1 => 'Yes', 0 => 'No')
            // see ChoiceTypeTest::testSetDataSingleNonExpandedAcceptsBoolean
            array(array(true), array(1)),
            array(array(false), array(0)),
        );
    }

    /**
     * @dataProvider provideInvalidChoices
     * @expectedException \Symfony\Component\Form\Exception\InvalidArgumentException
     */
    public function testFailIfInvalidChoices(array $choices)
    {
        new ArrayKeyChoiceList($choices);
    }

    /**
     * @dataProvider provideInvalidChoices
     * @expectedException \Symfony\Component\Form\Exception\InvalidArgumentException
     */
    public function testGetValuesForChoicesFailsIfInvalidChoices(array $choices)
    {
        $this->list->getValuesForChoices($choices);
    }

    public function provideInvalidChoices()
    {
        return array(
            array(array(new \stdClass())),
            array(array(array(1, 2))),
        );
    }

    /**
     * @dataProvider provideConvertibleValues
     */
    public function testConvertValuesToStrings($value, $converted)
    {
        $callback = function () use ($value) {
            return $value;
        };

        $list = new ArrayKeyChoiceList(array('choice'), $callback);

        $this->assertSame(array($converted), $list->getValues());
    }

    public function provideConvertibleValues()
    {
        return array(
            array(0, '0'),
            array(1, '1'),
            array('0', '0'),
            array('1', '1'),
            array('1.23', '1.23'),
            array('foobar', 'foobar'),
            // The default value of choice fields is NULL. It should be treated
            // like the empty value for this choice list type
            array(null, ''),
            array(1.23, '1.23'),
            // Always cast booleans to 0 and 1, because:
            // array(true => 'Yes', false => 'No') === array(1 => 'Yes', 0 => 'No')
            // see ChoiceTypeTest::testSetDataSingleNonExpandedAcceptsBoolean
            array(true, '1'),
            array(false, ''),
        );
    }

    public function testCreateChoiceListWithValueCallback()
    {
        $callback = function ($choice, $key) {
            return $key.':'.$choice;
        };

        $choiceList = new ArrayKeyChoiceList(array(2 => 'foo', 7 => 'bar', 10 => 'baz'), $callback);

        $this->assertSame(array(2 => '2:foo', 7 => '7:bar', 10 => '10:baz'), $choiceList->getValues());
        $this->assertSame(array(1 => 'foo', 2 => 'baz'), $choiceList->getChoicesForValues(array(1 => '2:foo', 2 => '10:baz')));
        $this->assertSame(array(1 => '2:foo', 2 => '10:baz'), $choiceList->getValuesForChoices(array(1 => 'foo', 2 => 'baz')));
    }
}
