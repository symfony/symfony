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
use Symfony\Component\Workflow\Tests\Subject;

class MethodMarkingStoreTest extends TestCase
{
    public function testGetSetMarkingWithMultipleState()
    {
        $subject = new Subject();

        $markingStore = new MethodMarkingStore(false);

        $marking = $markingStore->getMarking($subject);

        $this->assertCount(0, $marking->getPlaces());

        $marking->mark('first_place');

        $markingStore->setMarking($subject, $marking, ['foo' => 'bar']);

        $this->assertSame(['first_place' => 1], $subject->getMarking());
        $this->assertSame(['foo' => 'bar'], $subject->getContext());

        $marking2 = $markingStore->getMarking($subject);

        $this->assertEquals($marking, $marking2);
    }

    public function testGetSetMarkingWithSingleState()
    {
        $subject = new Subject();

        $markingStore = new MethodMarkingStore(true);

        $marking = $markingStore->getMarking($subject);

        $this->assertCount(0, $marking->getPlaces());

        $marking->mark('first_place');

        $markingStore->setMarking($subject, $marking, ['foo' => 'bar']);

        $this->assertSame('first_place', $subject->getMarking());

        $marking2 = $markingStore->getMarking($subject);
        $this->assertSame(['foo' => 'bar'], $subject->getContext());

        $this->assertEquals($marking, $marking2);
    }

    public function testGetSetMarkingWithSingleStateAndAlmostEmptyPlaceName()
    {
        $subject = new Subject(0);

        $markingStore = new MethodMarkingStore(true);

        $marking = $markingStore->getMarking($subject);

        $this->assertCount(1, $marking->getPlaces());
    }

    public function testGetMarkingWithValueObject()
    {
        $subject = new Subject($this->createValueObject('first_place'));

        $markingStore = new MethodMarkingStore(true);

        $marking = $markingStore->getMarking($subject);

        $this->assertCount(1, $marking->getPlaces());
        $this->assertSame('first_place', (string) $subject->getMarking());
    }

    public function testGetMarkingWithBackedEnum()
    {
        $subject = new SubjectWithEnum(TestEnum::Foo);

        $markingStore = new MethodMarkingStore(true);

        $marking = $markingStore->getMarking($subject);

        $this->assertCount(1, $marking->getPlaces());
        $this->assertSame(['foo' => 1], $marking->getPlaces());

        $marking->mark('bar');
        $marking->unmark('foo');
        $markingStore->setMarking($subject, $marking);

        $this->assertSame(TestEnum::Bar, $subject->getMarking());
    }

    public function testGetMarkingWithUninitializedProperty()
    {
        $subject = new SubjectWithType();

        $markingStore = new MethodMarkingStore(true);

        $marking = $markingStore->getMarking($subject);

        $this->assertCount(0, $marking->getPlaces());
    }

    public function testGetMarkingWithUninitializedProperty2()
    {
        $subject = new SubjectWithType();

        $markingStore = new MethodMarkingStore(true, 'marking2');

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Typed property Symfony\Component\Workflow\Tests\MarkingStore\SubjectWithType::$marking must not be accessed before initialization');

        $markingStore->getMarking($subject);
    }

    public function testGetMarkingWithUninitializedPropertyInheritance()
    {
        $subject = new ChildInheritingProperty();

        $markingStore = new MethodMarkingStore(true, 'place');
        $marking = $markingStore->getMarking($subject);

        $this->assertCount(0, $marking->getPlaces());
    }

    public function testGetMarkingWithSameSubjectMultipleTimes()
    {
        $subject1 = new Subject('first_place');
        $subject2 = new Subject('second_place');
        $subject3 = new Subject('third_place');

        $markingStore = new MethodMarkingStore(true);

        $marking1 = $markingStore->getMarking($subject1);
        $marking2 = $markingStore->getMarking($subject2);
        $marking3 = $markingStore->getMarking($subject3);

        $this->assertSame(['first_place' => 1], $marking1->getPlaces());
        $this->assertSame(['second_place' => 1], $marking2->getPlaces());
        $this->assertSame(['third_place' => 1], $marking3->getPlaces());
    }

    public function testSetMarkingWithMultipleSubjectsSharingCachedSetter()
    {
        $subject1 = new Subject();
        $subject2 = new Subject();
        $subject3 = new Subject();

        $markingStore = new MethodMarkingStore(true);

        // First call caches the setter for Subject class
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

        // Each subject should have its own marking and context
        $this->assertSame('place1', $subject1->getMarking());
        $this->assertSame(['context1' => 'value1'], $subject1->getContext());

        $this->assertSame('place2', $subject2->getMarking());
        $this->assertSame(['context2' => 'value2'], $subject2->getContext());

        $this->assertSame('place3', $subject3->getMarking());
        $this->assertSame(['context3' => 'value3'], $subject3->getContext());
    }

    public function testSetMarkingWithMultipleSubjectsSharingCachedSetterMultipleState()
    {
        $subject1 = new Subject();
        $subject2 = new Subject();

        $markingStore = new MethodMarkingStore(false);

        // First call caches the setter for Subject class
        $marking1 = $markingStore->getMarking($subject1);
        $marking1->mark('place1');
        $marking1->mark('place2');
        $markingStore->setMarking($subject1, $marking1, ['context1' => 'value1']);

        // Second call should use the cached setter but operate on a different subject
        $marking2 = $markingStore->getMarking($subject2);
        $marking2->mark('place3');
        $markingStore->setMarking($subject2, $marking2, ['context2' => 'value2']);

        // Each subject should have its own marking and context
        $this->assertSame(['place1' => 1, 'place2' => 1], $subject1->getMarking());
        $this->assertSame(['context1' => 'value1'], $subject1->getContext());

        $this->assertSame(['place3' => 1], $subject2->getMarking());
        $this->assertSame(['context2' => 'value2'], $subject2->getContext());
    }

    private function createValueObject(string $markingValue): object
    {
        return new class($markingValue) {
            private string $markingValue;

            public function __construct(string $markingValue)
            {
                $this->markingValue = $markingValue;
            }

            public function __toString(): string
            {
                return $this->markingValue;
            }
        };
    }
}

class ParentWithProperty
{
    public string $place;
}

class ChildInheritingProperty extends ParentWithProperty
{
}
