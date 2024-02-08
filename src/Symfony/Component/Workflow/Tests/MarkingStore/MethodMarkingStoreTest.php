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
