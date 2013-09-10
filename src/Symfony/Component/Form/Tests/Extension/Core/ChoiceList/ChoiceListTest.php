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

use Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceList;
use Symfony\Component\Form\Extension\Core\View\ChoiceView;

class ChoiceListTest extends AbstractChoiceListTest
{
    private $obj1;

    private $obj2;

    private $obj3;

    private $obj4;

    protected function setUp()
    {
        $this->obj1 = new \stdClass();
        $this->obj2 = new \stdClass();
        $this->obj3 = new \stdClass();
        $this->obj4 = new \stdClass();

        parent::setUp();
    }

    public function testInitArray()
    {
        $this->list = new ChoiceList(
            array($this->obj1, $this->obj2, $this->obj3, $this->obj4),
            array('A', 'B', 'C', 'D'),
            array($this->obj2)
        );

        $this->assertSame(array($this->obj1, $this->obj2, $this->obj3, $this->obj4), $this->list->getChoices());
        $this->assertSame(array('0', '1', '2', '3'), $this->list->getValues());
        $this->assertEquals(array(1 => new ChoiceView($this->obj2, '1', 'B')), $this->list->getPreferredViews());
        $this->assertEquals(array(0 => new ChoiceView($this->obj1, '0', 'A'), 2 => new ChoiceView($this->obj3, '2', 'C'), 3 => new ChoiceView($this->obj4, '3', 'D')), $this->list->getRemainingViews());
    }

    /**
     * Necessary for interoperability with MongoDB cursors or ORM relations as
     * choices parameter. A choice itself that is an object implementing \Traversable
     * is not treated as hierarchical structure, but as-is.
     */
    public function testInitNestedTraversable()
    {
        $traversableChoice = new \ArrayIterator(array($this->obj3, $this->obj4));

        $this->list = new ChoiceList(
            new \ArrayIterator(array(
                'Group' => array($this->obj1, $this->obj2),
                'Not a Group' => $traversableChoice
            )),
            array(
                'Group' => array('A', 'B'),
                'Not a Group' => 'C',
            ),
            array($this->obj2)
        );

        $this->assertSame(array($this->obj1, $this->obj2, $traversableChoice), $this->list->getChoices());
        $this->assertSame(array('0', '1', '2'), $this->list->getValues());
        $this->assertEquals(array(
            'Group' => array(1 => new ChoiceView($this->obj2, '1', 'B'))
        ), $this->list->getPreferredViews());
        $this->assertEquals(array(
            'Group' => array(0 => new ChoiceView($this->obj1, '0', 'A')),
            2 => new ChoiceView($traversableChoice, '2', 'C')
        ), $this->list->getRemainingViews());
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

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInitWithInsufficientLabels()
    {
        $this->list = new ChoiceList(
            array($this->obj1, $this->obj2),
            array('A')
        );
    }

    public function testInitWithLabelsContainingNull()
    {
        $this->list = new ChoiceList(
            array($this->obj1, $this->obj2),
            array('A', null)
        );

        $this->assertEquals(
            array(0 => new ChoiceView($this->obj1, '0', 'A'), 1 => new ChoiceView($this->obj2, '1', null)),
            $this->list->getRemainingViews()
        );
    }

    /**
     * @return \Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface
     */
    protected function createChoiceList()
    {
        return new ChoiceList(
            array(
                'Group 1' => array($this->obj1, $this->obj2),
                'Group 2' => array($this->obj3, $this->obj4),
            ),
            array(
                'Group 1' => array('A', 'B'),
                'Group 2' => array('C', 'D'),
            ),
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
