<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\ChoiceList;

use Symfony\Component\Form\Extension\Core\ChoiceList\ObjectChoiceList;
use Symfony\Component\Form\Extension\Core\View\ChoiceView;

class ObjectChoiceListTest_EntityWithToString
{
    private $property;

    public function __construct($property)
    {
        $this->property = $property;
    }

    public function __toString()
    {
        return $this->property;
    }
}

class ObjectChoiceListTest extends AbstractChoiceListTest
{
    private $obj1;

    private $obj2;

    private $obj3;

    private $obj4;

    protected function setUp()
    {
        $this->obj1 = (object) array('name' => 'A');
        $this->obj2 = (object) array('name' => 'B');
        $this->obj3 = (object) array('name' => 'C');
        $this->obj4 = (object) array('name' => 'D');

        parent::setUp();
    }

    public function testInitArray()
    {
        $this->list = new ObjectChoiceList(
            array($this->obj1, $this->obj2, $this->obj3, $this->obj4),
            'name',
            array($this->obj2)
        );

        $this->assertSame(array($this->obj1, $this->obj2, $this->obj3, $this->obj4), $this->list->getChoices());
        $this->assertSame(array('0', '1', '2', '3'), $this->list->getValues());
        $this->assertEquals(array(1 => new ChoiceView($this->obj2, '1', 'B')), $this->list->getPreferredViews());
        $this->assertEquals(array(0 => new ChoiceView($this->obj1, '0', 'A'), 2 => new ChoiceView($this->obj3, '2', 'C'), 3 => new ChoiceView($this->obj4, '3', 'D')), $this->list->getRemainingViews());
    }

    public function testInitNestedArray()
    {
        $this->assertSame(array($this->obj1, $this->obj2, $this->obj3, $this->obj4), $this->list->getChoices());
        $this->assertSame(array('0', '1', '2', '3'), $this->list->getValues());
        $this->assertEquals(array(
            'Group 1' => array(1 => new ChoiceView($this->obj2, '1', 'B')),
            'Group 2' => array(2 => new ChoiceView($this->obj3, '2', 'C'))
        ), $this->list->getPreferredViews());
        $this->assertEquals(array(
            'Group 1' => array(0 => new ChoiceView($this->obj1, '0', 'A')),
            'Group 2' => array(3 => new ChoiceView($this->obj4, '3', 'D'))
        ), $this->list->getRemainingViews());
    }

    public function testInitArrayWithGroupPath()
    {
        $this->obj1 = (object) array('name' => 'A', 'category' => 'Group 1');
        $this->obj2 = (object) array('name' => 'B', 'category' => 'Group 1');
        $this->obj3 = (object) array('name' => 'C', 'category' => 'Group 2');
        $this->obj4 = (object) array('name' => 'D', 'category' => 'Group 2');

        // Objects with NULL groups are not grouped
        $obj5 = (object) array('name' => 'E', 'category' => null);

        // Objects without the group property are not grouped either
        // see https://github.com/symfony/symfony/commit/d9b7abb7c7a0f28e0ce970afc5e305dce5dccddf
        $obj6 = (object) array('name' => 'F');

        $this->list = new ObjectChoiceList(
            array($this->obj1, $this->obj2, $this->obj3, $this->obj4, $obj5, $obj6),
            'name',
            array($this->obj2, $this->obj3),
            'category'
        );

        $this->assertSame(array($this->obj1, $this->obj2, $this->obj3, $this->obj4, $obj5, $obj6), $this->list->getChoices());
        $this->assertSame(array('0', '1', '2', '3', '4', '5'), $this->list->getValues());
        $this->assertEquals(array(
            'Group 1' => array(1 => new ChoiceView($this->obj2, '1', 'B')),
            'Group 2' => array(2 => new ChoiceView($this->obj3, '2', 'C'))
        ), $this->list->getPreferredViews());
        $this->assertEquals(array(
            'Group 1' => array(0 => new ChoiceView($this->obj1, '0', 'A')),
            'Group 2' => array(3 => new ChoiceView($this->obj4, '3', 'D')),
            4 => new ChoiceView($obj5, '4', 'E'),
            5 => new ChoiceView($obj6, '5', 'F'),
        ), $this->list->getRemainingViews());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInitArrayWithGroupPathThrowsExceptionIfNestedArray()
    {
        $this->obj1 = (object) array('name' => 'A', 'category' => 'Group 1');
        $this->obj2 = (object) array('name' => 'B', 'category' => 'Group 1');
        $this->obj3 = (object) array('name' => 'C', 'category' => 'Group 2');
        $this->obj4 = (object) array('name' => 'D', 'category' => 'Group 2');

        new ObjectChoiceList(
            array(
                'Group 1' => array($this->obj1, $this->obj2),
                'Group 2' => array($this->obj3, $this->obj4),
            ),
            'name',
            array($this->obj2, $this->obj3),
            'category'
        );
    }

    public function testInitArrayWithValuePath()
    {
        $this->obj1 = (object) array('name' => 'A', 'id' => 10);
        $this->obj2 = (object) array('name' => 'B', 'id' => 20);
        $this->obj3 = (object) array('name' => 'C', 'id' => 30);
        $this->obj4 = (object) array('name' => 'D', 'id' => 40);

        $this->list = new ObjectChoiceList(
            array($this->obj1, $this->obj2, $this->obj3, $this->obj4),
            'name',
            array($this->obj2, $this->obj3),
            null,
            'id'
        );

        $this->assertSame(array($this->obj1, $this->obj2, $this->obj3, $this->obj4), $this->list->getChoices());
        $this->assertSame(array('10', '20', '30', '40'), $this->list->getValues());
        $this->assertEquals(array(1 => new ChoiceView($this->obj2, '20', 'B'), 2 => new ChoiceView($this->obj3, '30', 'C')), $this->list->getPreferredViews());
        $this->assertEquals(array(0 => new ChoiceView($this->obj1, '10', 'A'), 3 => new ChoiceView($this->obj4, '40', 'D')), $this->list->getRemainingViews());
    }

    public function testInitArrayUsesToString()
    {
        $this->obj1 = new ObjectChoiceListTest_EntityWithToString('A');
        $this->obj2 = new ObjectChoiceListTest_EntityWithToString('B');
        $this->obj3 = new ObjectChoiceListTest_EntityWithToString('C');
        $this->obj4 = new ObjectChoiceListTest_EntityWithToString('D');

        $this->list = new ObjectChoiceList(
            array($this->obj1, $this->obj2, $this->obj3, $this->obj4)
        );

        $this->assertSame(array($this->obj1, $this->obj2, $this->obj3, $this->obj4), $this->list->getChoices());
        $this->assertSame(array('0', '1', '2', '3'), $this->list->getValues());
        $this->assertEquals(array(0 => new ChoiceView($this->obj1, '0', 'A'), 1 => new ChoiceView($this->obj2, '1', 'B'), 2 => new ChoiceView($this->obj3, '2', 'C'), 3 => new ChoiceView($this->obj4, '3', 'D')), $this->list->getRemainingViews());
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\StringCastException
     */
    public function testInitArrayThrowsExceptionIfToStringNotFound()
    {
        $this->obj1 = new ObjectChoiceListTest_EntityWithToString('A');
        $this->obj2 = new ObjectChoiceListTest_EntityWithToString('B');
        $this->obj3 = (object) array('name' => 'C');
        $this->obj4 = new ObjectChoiceListTest_EntityWithToString('D');

        new ObjectChoiceList(
            array($this->obj1, $this->obj2, $this->obj3, $this->obj4)
        );
    }

    /**
     * @return \Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface
     */
    protected function createChoiceList()
    {
        return new ObjectChoiceList(
            array(
                'Group 1' => array($this->obj1, $this->obj2),
                'Group 2' => array($this->obj3, $this->obj4),
            ),
            'name',
            array($this->obj2, $this->obj3)
        );
    }

    protected function getChoices()
    {
        return array(0 => $this->obj1, 1 => $this->obj2, 2 => $this->obj3, 3 => $this->obj4);
    }

    protected function getLabels()
    {
        return array(0 => 'A', 1 => 'B', 2 => 'C', 3 => 'D');
    }

    protected function getValues()
    {
        return array(0 => '0', 1 => '1', 2 => '2', 3 => '3');
    }

    protected function getIndices()
    {
        return array(0, 1, 2, 3);
    }
}
