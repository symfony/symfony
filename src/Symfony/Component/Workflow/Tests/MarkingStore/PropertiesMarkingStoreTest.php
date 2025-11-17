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
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;

class PropertiesMarkingStoreTest extends TestCase
{
    public function testGetSetMarkingWithMultipleState()
    {
        $subject = new SubjectWithProperties();
        $markingStore = new MethodMarkingStore(false);

        $marking = $markingStore->getMarking($subject);

        $this->assertCount(0, $marking->getPlaces());

        $marking->mark('first_place');

        $markingStore->setMarking($subject, $marking, ['foo' => 'bar']);

        $this->assertSame(['first_place' => 1], $subject->marking);

        $marking2 = $markingStore->getMarking($subject);

        $this->assertEquals($marking, $marking2);
    }

    public function testGetSetMarkingWithSingleState()
    {
        $subject = new SubjectWithProperties();
        $markingStore = new MethodMarkingStore(true, 'place', 'placeContext');

        $marking = $markingStore->getMarking($subject);

        $this->assertCount(0, $marking->getPlaces());

        $marking->mark('first_place');

        $markingStore->setMarking($subject, $marking, ['foo' => 'bar']);

        $this->assertSame('first_place', $subject->place);

        $marking2 = $markingStore->getMarking($subject);

        $this->assertEquals($marking, $marking2);
    }

    public function testGetSetMarkingWithSingleStateAndAlmostEmptyPlaceName()
    {
        $subject = new SubjectWithProperties();
        $subject->place = 0;

        $markingStore = new MethodMarkingStore(true, 'place');

        $marking = $markingStore->getMarking($subject);

        $this->assertCount(1, $marking->getPlaces());
    }

    public function testGetMarkingWithValueObject()
    {
        $subject = new SubjectWithProperties();
        $subject->place = $this->createValueObject('first_place');

        $markingStore = new MethodMarkingStore(true, 'place');

        $marking = $markingStore->getMarking($subject);

        $this->assertCount(1, $marking->getPlaces());
        $this->assertSame('first_place', (string) $subject->place);
    }

    public function testGetMarkingWithUninitializedProperty()
    {
        $subject = new SubjectWithProperties();

        $markingStore = new MethodMarkingStore(true, 'place');

        $marking = $markingStore->getMarking($subject);

        $this->assertCount(0, $marking->getPlaces());
    }

    public function testSetMarkingWithMultipleSubjectsSharingCachedSetter()
    {
        $subject1 = new SubjectWithProperties();
        $subject2 = new SubjectWithProperties();
        $subject3 = new SubjectWithProperties();

        $markingStore = new MethodMarkingStore(false);

        // First call caches the setter for SubjectWithProperties class
        $marking1 = $markingStore->getMarking($subject1);
        $marking1->mark('place1');
        $markingStore->setMarking($subject1, $marking1, ['context1' => 'value1']);

        // Subsequent calls should use the cached setter but operate on different subjects
        $marking2 = $markingStore->getMarking($subject2);
        $marking2->mark('place2');
        $markingStore->setMarking($subject2, $marking2, ['context2' => 'value2']);

        $marking3 = $markingStore->getMarking($subject3);
        $marking3->mark('place3');
        $markingStore->setMarking($subject3, $marking3, ['context3' => 'value3']);

        // Each subject should have its own marking set correctly
        $this->assertSame(['place1' => 1], $subject1->marking);
        $this->assertSame(['place2' => 1], $subject2->marking);
        $this->assertSame(['place3' => 1], $subject3->marking);
    }

    public function testSetMarkingWithMultipleSubjectsSharingCachedSetterSingleState()
    {
        $subject1 = new SubjectWithProperties();
        $subject2 = new SubjectWithProperties();

        $markingStore = new MethodMarkingStore(true, 'place');

        // First call caches the setter for SubjectWithProperties class
        $marking1 = $markingStore->getMarking($subject1);
        $marking1->mark('place1');
        $markingStore->setMarking($subject1, $marking1, ['context1' => 'value1']);

        // Second call should use the cached setter but operate on a different subject
        $marking2 = $markingStore->getMarking($subject2);
        $marking2->mark('place2');
        $markingStore->setMarking($subject2, $marking2, ['context2' => 'value2']);

        // Each subject should have its own marking set correctly
        $this->assertSame('place1', $subject1->place);
        $this->assertSame('place2', $subject2->place);
    }

    private function createValueObject(string $markingValue): object
    {
        return new class($markingValue) {
            public function __construct(
                private string $markingValue,
            ) {
            }

            public function __toString(): string
            {
                return $this->markingValue;
            }
        };
    }
}
