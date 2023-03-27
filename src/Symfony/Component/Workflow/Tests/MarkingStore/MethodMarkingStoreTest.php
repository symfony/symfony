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
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Tests\Subject;

class MethodMarkingStoreTest extends TestCase
{
    public function testGetSetMarkingWithMultipleState()
    {
        $subject = new Subject();

        $markingStore = new MethodMarkingStore(false);

        $marking = $markingStore->getMarking($subject);

        $this->assertInstanceOf(Marking::class, $marking);
        $this->assertCount(0, $marking->getPlaces());

        $marking->mark('first_place');

        $markingStore->setMarking($subject, $marking);

        $this->assertSame(['first_place' => 1], $subject->getMarking());

        $marking2 = $markingStore->getMarking($subject);

        $this->assertEquals($marking, $marking2);
    }

    public function testGetSetMarkingWithSingleState()
    {
        $subject = new Subject();

        $markingStore = new MethodMarkingStore(true);

        $marking = $markingStore->getMarking($subject);

        $this->assertInstanceOf(Marking::class, $marking);
        $this->assertCount(0, $marking->getPlaces());

        $marking->mark('first_place');

        $markingStore->setMarking($subject, $marking);

        $this->assertSame('first_place', $subject->getMarking());

        $marking2 = $markingStore->getMarking($subject);

        $this->assertEquals($marking, $marking2);
    }

    public function testGetSetMarkingWithSingleStateAndAlmostEmptyPlaceName()
    {
        $subject = new Subject(0);

        $markingStore = new MethodMarkingStore(true);

        $marking = $markingStore->getMarking($subject);

        $this->assertInstanceOf(Marking::class, $marking);
        $this->assertCount(1, $marking->getPlaces());
    }

    public function testGetMarkingWithValueObject()
    {
        $subject = new Subject($this->createValueObject('first_place'));

        $markingStore = new MethodMarkingStore(true);

        $marking = $markingStore->getMarking($subject);

        $this->assertInstanceOf(Marking::class, $marking);
        $this->assertCount(1, $marking->getPlaces());
        $this->assertSame('first_place', (string) $subject->getMarking());
    }

    public function testGetMarkingWithUninitializedProperty()
    {
        $subject = new SubjectWithType();

        $markingStore = new MethodMarkingStore(true);

        $marking = $markingStore->getMarking($subject);

        $this->assertInstanceOf(Marking::class, $marking);
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
            /** @var string */
            private $markingValue;

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
