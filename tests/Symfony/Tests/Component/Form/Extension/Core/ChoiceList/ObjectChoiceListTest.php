<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\ChoiceList;

use Symfony\Component\Form\Extension\Core\ChoiceList\ObjectChoiceList;

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

class ObjectChoiceListTest extends \PHPUnit_Framework_TestCase
{
    private $obj1;

    private $obj2;

    private $obj3;

    private $obj4;

    private $list;

    protected function setUp()
    {
        parent::setUp();

        $this->obj1 = (object) array('name' => 'A');
        $this->obj2 = (object) array('name' => 'B');
        $this->obj3 = (object) array('name' => 'C');
        $this->obj4 = (object) array('name' => 'D');

        $this->list = new ObjectChoiceList(
            array(
                'Group 1' => array($this->obj1, $this->obj2),
                'Group 2' => array($this->obj3, $this->obj4),
            ),
            'name',
            array($this->obj2, $this->obj3)
        );
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->obj1 = null;
        $this->obj2 = null;
        $this->obj3 = null;
        $this->obj4 = null;
        $this->list = null;
    }

    public function testInitArray()
    {
        $this->list = new ObjectChoiceList(
            array($this->obj1, $this->obj2, $this->obj3, $this->obj4),
            'name',
            array($this->obj2)
        );

        $this->assertSame(array($this->obj1, $this->obj2, $this->obj3, $this->obj4), $this->list->getChoices());
        $this->assertSame(array('A', 'B', 'C', 'D'), $this->list->getLabels());

        $this->assertSame(array(1 => '1'), $this->list->getPreferredValues());
        $this->assertSame(array(1 => '1'), $this->list->getPreferredValueHierarchy());
        $this->assertSame(array(0 => '0', 2 => '2', 3 => '3'), $this->list->getRemainingValues());
        $this->assertSame(array(0 => '0', 2 => '2', 3 => '3'), $this->list->getRemainingValueHierarchy());
    }

    public function testInitNestedArray()
    {
        $this->assertSame(array($this->obj1, $this->obj2, $this->obj3, $this->obj4), $this->list->getChoices());
        $this->assertSame(array('A', 'B', 'C', 'D'), $this->list->getLabels());

        $this->assertSame(array(1 => '1', 2 => '2'), $this->list->getPreferredValues());
        $this->assertSame(array(
            'Group 1' => array(1 => '1'),
            'Group 2' => array(2 => '2')
        ), $this->list->getPreferredValueHierarchy());
        $this->assertSame(array(0 => '0', 3 => '3'), $this->list->getRemainingValues());
        $this->assertSame(array(
            'Group 1' => array(0 => '0'),
            'Group 2' => array(3 => '3')
        ), $this->list->getRemainingValueHierarchy());
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
        $this->assertSame(array('A', 'B', 'C', 'D', 'E', 'F'), $this->list->getLabels());

        $this->assertSame(array(1 => '1', 2 => '2'), $this->list->getPreferredValues());
        $this->assertSame(array(
            'Group 1' => array(1 => '1'),
            'Group 2' => array(2 => '2')
        ), $this->list->getPreferredValueHierarchy());
        $this->assertSame(array(0 => '0', 3 => '3', 4 => '4', 5 => '5'), $this->list->getRemainingValues());
        $this->assertSame(array(
            'Group 1' => array(0 => '0'),
            'Group 2' => array(3 => '3'),
            4 => '4',
            5 => '5',
        ), $this->list->getRemainingValueHierarchy());
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
        $this->assertSame(array('A', 'B', 'C', 'D'), $this->list->getLabels());

        // Values are always converted to strings to avoid problems with
        // comparisons
        $this->assertSame(array(1 => '20', 2 => '30'), $this->list->getPreferredValues());
        $this->assertSame(array(1 => '20', 2 => '30'), $this->list->getPreferredValueHierarchy());
        $this->assertSame(array(0 => '10', 3 => '40'), $this->list->getRemainingValues());
        $this->assertSame(array(0 => '10', 3 => '40'), $this->list->getRemainingValueHierarchy());
    }

    public function testInitArrayWithIndexPath()
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
            null,
            'id'
        );

        $this->assertSame(array(10 => $this->obj1, 20 => $this->obj2, 30 => $this->obj3, 40 => $this->obj4), $this->list->getChoices());
        $this->assertSame(array(10 => 'A', 20 => 'B', 30 => 'C', 40 => 'D'), $this->list->getLabels());

        $this->assertSame(array(20 => '1', 30 => '2'), $this->list->getPreferredValues());
        $this->assertSame(array(20 => '1', 30 => '2'), $this->list->getPreferredValueHierarchy());
        $this->assertSame(array(10 => '0', 40 => '3'), $this->list->getRemainingValues());
        $this->assertSame(array(10 => '0', 40 => '3'), $this->list->getRemainingValueHierarchy());
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
        $this->assertSame(array('A', 'B', 'C', 'D'), $this->list->getLabels());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testInitArrayThrowsExceptionIfToStringNotFound()
    {
        $this->obj1 = new ObjectChoiceListTest_EntityWithToString('A');
        $this->obj2 = new ObjectChoiceListTest_EntityWithToString('B');
        $this->obj3 = (object) array('name' => 'C');
        $this->obj4 = new ObjectChoiceListTest_EntityWithToString('D');

        $this->list = new ObjectChoiceList(
            array($this->obj1, $this->obj2, $this->obj3, $this->obj4)
        );

        $this->assertSame(array($this->obj1, $this->obj2, $this->obj3, $this->obj4), $this->list->getChoices());
        $this->assertSame(array('A', 'B', 'C', 'D'), $this->list->getLabels());
    }
}
