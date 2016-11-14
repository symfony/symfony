<?php

namespace Symfony\Component\Workflow\Tests\MarkingStore;

use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\PropertyAccessMarkingStore;
use Symfony\Component\Workflow\MultipleStateMarking;
use Symfony\Component\Workflow\SingleStateMarking;

class PropertyAccessMarkingStoreTest extends MarkingStoreTest
{
    public function getClass()
    {
        return PropertyAccessMarkingStore::class;
    }

    public function testGetSetSingleStateMarking()
    {
        $subject = new \stdClass();
        $subject->myMarks = null;

        $markingStore = new PropertyAccessMarkingStore('myMarks', null, Marking::STRATEGY_SINGLE_STATE);

        $marking = $markingStore->getMarking($subject);

        $this->assertInstanceOf(SingleStateMarking::class, $marking);
        $this->assertSame(array(), $marking->getState());

        $marking->mark('first_place');

        $markingStore->setMarking($subject, $marking);

        $this->assertSame('first_place', $subject->myMarks);

        $marking2 = $markingStore->getMarking($subject);

        $this->assertEquals($marking, $marking2);

        $marking2->mark('second_place');

        $markingStore->setMarking($subject, $marking2);

        $this->assertSame('second_place', $subject->myMarks);

        $marking3 = $markingStore->getMarking($subject);

        $this->assertEquals($marking2, $marking3);

        $marking3->unmark('second_place');

        $markingStore->setMarking($subject, $marking3);

        $this->assertNull($subject->myMarks);

        $marking4 = $markingStore->getMarking($subject);

        $this->assertEquals($marking3, $marking4);
    }

    public function testGetSetMultipleStateMarking()
    {
        $subject = new \stdClass();
        $subject->myMarks = null;

        $markingStore = new PropertyAccessMarkingStore('myMarks');

        $marking = $markingStore->getMarking($subject);

        $this->assertInstanceOf(MultipleStateMarking::class, $marking);
        $this->assertCount(0, $marking->getState());

        $marking->mark('first_place');

        $markingStore->setMarking($subject, $marking);

        $this->assertSame(array('first_place' => 1), $subject->myMarks);

        $marking2 = $markingStore->getMarking($subject);

        $this->assertEquals($marking, $marking2);

        $marking2->mark('second_place');

        $markingStore->setMarking($subject, $marking2);

        $this->assertSame(array('first_place' => 1, 'second_place' => 1), $subject->myMarks);

        $marking3 = $markingStore->getMarking($subject);

        $this->assertEquals($marking2, $marking3);
    }
}
