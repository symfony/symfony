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
