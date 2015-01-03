<?php

namespace Symfony\Bridge\Propel1\Tests\Form\ChoiceList;

use Symfony\Bridge\Propel1\Form\ChoiceList\ModelChoiceList;
use Symfony\Bridge\Propel1\Tests\Fixtures\Item;
use Symfony\Bridge\Propel1\Tests\Fixtures\ItemQuery;
use Symfony\Component\Form\Tests\Extension\Core\ChoiceList\AbstractChoiceListTest;

class CompatModelChoiceListTest extends AbstractChoiceListTest
{
    const ITEM_CLASS = '\Symfony\Bridge\Propel1\Tests\Fixtures\Item';

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|\Symfony\Bridge\Propel1\Tests\Fixtures\ItemQuery
     */
    protected $query;

    protected $item1;
    protected $item2;
    protected $item3;
    protected $item4;

    public function testGetChoicesForValues()
    {
        $this->query
            ->expects($this->once())
            ->method('filterById')
            ->with(array(1, 2))
            ->will($this->returnSelf())
        ;

        ItemQuery::$result = array(
            $this->item2,
            $this->item1,
        );

        parent::testGetChoicesForValues();
    }

    protected function setUp()
    {
        $this->query = $this->getMock('Symfony\Bridge\Propel1\Tests\Fixtures\ItemQuery', array(
            'filterById',
        ), array(), '', true, true, true, false, true);

        $this->query
            ->expects($this->any())
            ->method('filterById')
            ->with($this->anything())
            ->will($this->returnSelf())
        ;

        $this->createItems();

        ItemQuery::$result = array(
            $this->item1,
            $this->item2,
            $this->item3,
            $this->item4,
        );

        parent::setUp();
    }

    protected function createItems()
    {
        $this->item1 = new Item(1, 'Foo');
        $this->item2 = new Item(2, 'Bar');
        $this->item3 = new Item(3, 'Baz');
        $this->item4 = new Item(4, 'Cuz');
    }

    protected function createChoiceList()
    {
        return new ModelChoiceList(self::ITEM_CLASS, 'value', null, $this->query);
    }

    protected function getChoices()
    {
        return array(
            1 => $this->item1,
            2 => $this->item2,
            3 => $this->item3,
            4 => $this->item4,
        );
    }

    protected function getLabels()
    {
        return array(
            1 => 'Foo',
            2 => 'Bar',
            3 => 'Baz',
            4 => 'Cuz',
        );
    }

    protected function getValues()
    {
        return array(
            1 => '1',
            2 => '2',
            3 => '3',
            4 => '4',
        );
    }

    protected function getIndices()
    {
        return array(
            1,
            2,
            3,
            4,
        );
    }
}
