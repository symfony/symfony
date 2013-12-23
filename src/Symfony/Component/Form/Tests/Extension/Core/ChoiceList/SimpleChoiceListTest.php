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

use Symfony\Component\Form\Extension\Core\ChoiceList\SimpleChoiceList;
use Symfony\Component\Form\Extension\Core\View\ChoiceView;

class SimpleChoiceListTest extends AbstractChoiceListTest
{
    public function testInitArray()
    {
        $choices = array('a' => 'A', 'b' => 'B', 'c' => 'C');
        $this->list = new SimpleChoiceList($choices, array('b'));

        $this->assertSame(array(0 => 'a', 1 => 'b', 2 => 'c'), $this->list->getChoices());
        $this->assertSame(array(0 => 'a', 1 => 'b', 2 => 'c'), $this->list->getValues());
        $this->assertEquals(array(1 => new ChoiceView('b', 'b', 'B')), $this->list->getPreferredViews());
        $this->assertEquals(array(0 => new ChoiceView('a', 'a', 'A'), 2 => new ChoiceView('c', 'c', 'C')), $this->list->getRemainingViews());
    }

    public function testInitNestedArray()
    {
        $this->assertSame(array(0 => 'a', 1 => 'b', 2 => 'c', 3 => 'd'), $this->list->getChoices());
        $this->assertSame(array(0 => 'a', 1 => 'b', 2 => 'c', 3 => 'd'), $this->list->getValues());
        $this->assertEquals(array(
            'Group 1' => array(1 => new ChoiceView('b', 'b', 'B')),
            'Group 2' => array(2 => new ChoiceView('c', 'c', 'C'))
        ), $this->list->getPreferredViews());
        $this->assertEquals(array(
            'Group 1' => array(0 => new ChoiceView('a', 'a', 'A')),
            'Group 2' => array(3 => new ChoiceView('d', 'd', 'D'))
        ), $this->list->getRemainingViews());
    }

    /**
     * @dataProvider dirtyValuesProvider
     */
    public function testGetValuesForChoicesDealsWithDirtyValues($choice, $value)
    {
        $choices = array(
            '0' => 'Zero',
            '1' => 'One',
            '' => 'Empty',
            '1.23' => 'Float',
            'foo' => 'Foo',
            'foo10' => 'Foo 10',
        );

        $this->list = new SimpleChoiceList($choices, array());

        $this->assertSame(array($value), $this->list->getValuesForChoices(array($choice)));
    }

    public function dirtyValuesProvider()
    {
        return array(
            array(0, '0'),
            array('0', '0'),
            array('1', '1'),
            array(false, '0'),
            array(true, '1'),
            array('', ''),
            array(null, ''),
            array('1.23', '1.23'),
            array('foo', 'foo'),
            array('foo10', 'foo10'),
        );
    }

    /**
     * @return \Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface
     */
    protected function createChoiceList()
    {
        return new SimpleChoiceList(array(
            'Group 1' => array('a' => 'A', 'b' => 'B'),
            'Group 2' => array('c' => 'C', 'd' => 'D'),
        ), array('b', 'c'));
    }

    protected function getChoices()
    {
        return array(0 => 'a', 1 => 'b', 2 => 'c', 3 => 'd');
    }

    protected function getLabels()
    {
        return array(0 => 'A', 1 => 'B', 2 => 'C', 3 => 'D');
    }

    protected function getValues()
    {
        return array(0 => 'a', 1 => 'b', 2 => 'c', 3 => 'd');
    }

    protected function getIndices()
    {
        return array(0, 1, 2, 3);
    }
}
