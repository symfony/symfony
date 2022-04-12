<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\MarkingStore;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\SingleStateMarkingStore;

/** @group legacy*/
class SingleStateMarkingStoreTest extends TestCase
{
    public function testGetSetMarking()
    {
        $subject = new \stdClass();
        $subject->myMarks = null;

        $markingStore = new SingleStateMarkingStore('myMarks');

        $marking = $markingStore->getMarking($subject);

        $this->assertInstanceOf(Marking::class, $marking);
        $this->assertCount(0, $marking->getPlaces());

        $marking->mark('first_place');

        $markingStore->setMarking($subject, $marking);

        $this->assertSame('first_place', $subject->myMarks);

        $marking2 = $markingStore->getMarking($subject);

        $this->assertEquals($marking, $marking2);
    }

    public function testAlmostEmptyPlaceName()
    {
        $subject = new \stdClass();
        $subject->myMarks = 0;

        $markingStore = new SingleStateMarkingStore('myMarks');

        $marking = $markingStore->getMarking($subject);

        $this->assertInstanceOf(Marking::class, $marking);
        $this->assertCount(1, $marking->getPlaces());
    }
}
