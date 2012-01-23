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

use Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceList;

class ChoiceListTest extends \PHPUnit_Framework_TestCase
{
    private $obj1;

    private $obj2;

    private $obj3;

    private $obj4;

    private $list;

    protected function setUp()
    {
        parent::setUp();

        $this->obj1 = new \stdClass();
        $this->obj2 = new \stdClass();
        $this->obj3 = new \stdClass();
        $this->obj4 = new \stdClass();

        $this->list = new ChoiceList(
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
        $this->list = new ChoiceList(
            array($this->obj1, $this->obj2, $this->obj3, $this->obj4),
            array('A', 'B', 'C', 'D'),
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

    public function testGetIndicesForChoices()
    {
        $choices = array($this->obj2, $this->obj3);
        $this->assertSame(array(1, 2), $this->list->getIndicesForChoices($choices));
    }

    public function testGetIndicesForChoicesIgnoresNonExistingChoices()
    {
        $choices = array($this->obj2, $this->obj3, 'foobar');
        $this->assertSame(array(1, 2), $this->list->getIndicesForChoices($choices));
    }

    public function testGetIndicesForValues()
    {
        // values and indices are always the same
        $values = array('1', '2');
        $this->assertSame(array(1, 2), $this->list->getIndicesForValues($values));
    }

    public function testGetIndicesForValuesIgnoresNonExistingValues()
    {
        $values = array('1', '2', '5');
        $this->assertSame(array(1, 2), $this->list->getIndicesForValues($values));
    }

    public function testGetChoicesForValues()
    {
        $values = array('1', '2');
        $this->assertSame(array($this->obj2, $this->obj3), $this->list->getChoicesForValues($values));
    }

    public function testGetChoicesForValuesIgnoresNonExistingValues()
    {
        $values = array('1', '2', '5');
        $this->assertSame(array($this->obj2, $this->obj3), $this->list->getChoicesForValues($values));
    }

    public function testGetValuesForChoices()
    {
        $choices = array($this->obj2, $this->obj3);
        $this->assertSame(array('1', '2'), $this->list->getValuesForChoices($choices));
    }

    public function testGetValuesForChoicesIgnoresNonExistingChoices()
    {
        $choices = array($this->obj2, $this->obj3, 'foobar');
        $this->assertSame(array('1', '2'), $this->list->getValuesForChoices($choices));
    }
}
