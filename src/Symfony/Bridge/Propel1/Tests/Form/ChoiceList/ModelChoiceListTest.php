<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Propel1\Tests\Form\ChoiceList;

use Symfony\Bridge\Propel1\Form\ChoiceList\ModelChoiceList;
use Symfony\Bridge\Propel1\Tests\Propel1TestCase;
use Symfony\Bridge\Propel1\Tests\Fixtures\Item;
use Symfony\Bridge\Propel1\Tests\Fixtures\ItemQuery;
use Symfony\Bridge\Propel1\Tests\Fixtures\ReadOnlyItem;
use Symfony\Component\Form\Extension\Core\View\ChoiceView;

class ModelChoiceListTest extends Propel1TestCase
{
    const ITEM_CLASS = '\Symfony\Bridge\Propel1\Tests\Fixtures\Item';

    protected function setUp()
    {
        ItemQuery::$result = array();
    }

    public function testEmptyChoicesReturnsEmpty()
    {
        $choiceList = new ModelChoiceList(
            self::ITEM_CLASS,
            'value',
            array()
        );

        $this->assertSame(array(), $choiceList->getChoices());
    }

    public function testReadOnlyIsValidChoice()
    {
        $item = new ReadOnlyItem();
        $choiceList = new ModelChoiceList(
            '\Symfony\Bridge\Propel1\Tests\Fixtures\ReadOnlyItem',
            'name',
            array(
                $item,
            )
        );

        $this->assertSame(array(42 => $item), $choiceList->getChoices());
    }

    public function testFlattenedChoices()
    {
        $item1 = new Item(1, 'Foo');
        $item2 = new Item(2, 'Bar');

        $choiceList = new ModelChoiceList(
            self::ITEM_CLASS,
            'value',
            array(
                $item1,
                $item2,
            )
        );

        $this->assertSame(array(1 => $item1, 2 => $item2), $choiceList->getChoices());
    }

    public function testFlattenedPreferredChoices()
    {
        $item1 = new Item(1, 'Foo');
        $item2 = new Item(2, 'Bar');

        $choiceList = new ModelChoiceList(
            self::ITEM_CLASS,
            'value',
            array(
                $item1,
                $item2,
            ),
            null,
            null,
            array(
                $item1,
            )
        );

        $this->assertSame(array(1 => $item1, 2 => $item2), $choiceList->getChoices());
        $this->assertEquals(array(1 => new ChoiceView($item1, '1', 'Foo')), $choiceList->getPreferredViews());
    }

    public function testNestedChoices()
    {
        $item1 = new Item(1, 'Foo');
        $item2 = new Item(2, 'Bar');

        $choiceList = new ModelChoiceList(
            self::ITEM_CLASS,
            'value',
            array(
                'group1' => array($item1),
                'group2' => array($item2),
            )
        );

        $this->assertSame(array(1 => $item1, 2 => $item2), $choiceList->getChoices());
        $this->assertEquals(array(
            'group1' => array(1 => new ChoiceView($item1, '1', 'Foo')),
            'group2' => array(2 => new ChoiceView($item2, '2', 'Bar')),
        ), $choiceList->getRemainingViews());
    }

    public function testGroupBySupportsString()
    {
        $item1 = new Item(1, 'Foo', 'Group1');
        $item2 = new Item(2, 'Bar', 'Group1');
        $item3 = new Item(3, 'Baz', 'Group2');
        $item4 = new Item(4, 'Boo!', null);

        $choiceList = new ModelChoiceList(
            self::ITEM_CLASS,
            'value',
            array(
                $item1,
                $item2,
                $item3,
                $item4,
            ),
            null,
            'groupName'
        );

        $this->assertEquals(array(1 => $item1, 2 => $item2, 3 => $item3, 4 => $item4), $choiceList->getChoices());
        $this->assertEquals(array(
            'Group1' => array(1 => new ChoiceView($item1, '1', 'Foo'), 2 => new ChoiceView($item2, '2', 'Bar')),
            'Group2' => array(3 => new ChoiceView($item3, '3', 'Baz')),
            4 => new ChoiceView($item4, '4', 'Boo!'),
        ), $choiceList->getRemainingViews());
    }

    public function testGroupByInvalidPropertyPathReturnsFlatChoices()
    {
        $item1 = new Item(1, 'Foo', 'Group1');
        $item2 = new Item(2, 'Bar', 'Group1');

        $choiceList = new ModelChoiceList(
            self::ITEM_CLASS,
            'value',
            array(
                $item1,
                $item2,
            ),
            null,
            'child.that.does.not.exist'
        );

        $this->assertEquals(array(
            1 => $item1,
            2 => $item2,
        ), $choiceList->getChoices());
    }

    public function testGetValuesForChoices()
    {
        $item1 = new Item(1, 'Foo');
        $item2 = new Item(2, 'Bar');

        ItemQuery::$result = array(
            $item1,
            $item2,
        );

        $choiceList = new ModelChoiceList(
            self::ITEM_CLASS,
            'value',
            null,
            null,
            null,
            null
        );

        $this->assertEquals(array(1, 2), $choiceList->getValuesForChoices(array($item1, $item2)));
    }

    public function testDifferentEqualObjectsAreChoosen()
    {
        $item = new Item(1, 'Foo');

        ItemQuery::$result = array(
            $item,
        );

        $choiceList = new ModelChoiceList(
            self::ITEM_CLASS,
            'value',
            array($item)
        );

        $choosenItem = new Item(1, 'Foo');

        $this->assertEquals(array('1'), $choiceList->getValuesForChoices(array($choosenItem)));
    }

    /**
     * @group legacy
     */
    public function testLegacygetIndicesForChoices()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $item1 = new Item(1, 'Foo');
        $item2 = new Item(2, 'Bar');

        ItemQuery::$result = array(
            $item1,
            $item2,
        );

        $choiceList = new ModelChoiceList(
            self::ITEM_CLASS,
            'value',
            null,
            null,
            null,
            null
        );

        $this->assertEquals(array(1, 2), $choiceList->getIndicesForChoices(array($item1, $item2)));
    }

    /**
     * @group legacy
     */
    public function testLegacyDifferentEqualObjectsAreChoosen()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $item = new Item(1, 'Foo');

        ItemQuery::$result = array(
            $item,
        );

        $choiceList = new ModelChoiceList(
            self::ITEM_CLASS,
            'value',
            array($item)
        );

        $choosenItem = new Item(1, 'Foo');

        $this->assertEquals(array(1), $choiceList->getIndicesForChoices(array($choosenItem)));
    }

    /**
     * @group legacy
     */
    public function testLegacyGetIndicesForNullChoices()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $item = new Item(1, 'Foo');
        $choiceList = new ModelChoiceList(
            self::ITEM_CLASS,
            'value',
            array($item)
        );

        $this->assertEquals(array(), $choiceList->getIndicesForChoices(array(null)));
    }

    public function testDontAllowInvalidChoiceValues()
    {
        $item = new Item(1, 'Foo');
        $choiceList = new ModelChoiceList(
            self::ITEM_CLASS,
            'value',
            array($item)
        );

        $this->assertEquals(array(), $choiceList->getValuesForChoices(array(new Item(2, 'Bar'))));
        $this->assertEquals(array(), $choiceList->getChoicesForValues(array(2)));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\MissingOptionsException
     */
    public function testEmptyClass()
    {
        new ModelChoiceList('');
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testInvalidClass()
    {
        new ModelChoiceList('Foo\Bar\DoesNotExistClass');
    }

    public function testCustomIdentifier()
    {
        $item1 = new Item(1, 'Foo', null, null, 'slug');
        $item2 = new Item(2, 'Bar', null, null, 'slug2');

        $choiceList = new ModelChoiceList(
            self::ITEM_CLASS,
            'value',
            array(
                $item1,
                $item2,
            ),
            null,
            null,
            array(),
            null,
            'slug'
        );

        $this->assertSame(array('slug' => $item1, 'slug2' => $item2), $choiceList->getChoices());
    }
}
