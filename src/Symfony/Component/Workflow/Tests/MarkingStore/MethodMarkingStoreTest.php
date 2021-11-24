<?php

namespace Symfony\Component\Workflow\Tests\MarkingStore;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Tests\fixtures\FooEnum;
use Symfony\Component\Workflow\Tests\Subject;
use Symfony\Component\Workflow\Utils\PlaceEnumerationUtils;

class MethodMarkingStoreTest extends TestCase
{
    /**
     * @dataProvider providePlaceAndExpectedResultForMultipleState
     */
    public function testGetSetMarkingWithMultipleState(string|\UnitEnum $place, array $expectedResult)
    {
        $subject = new Subject();

        $markingStore = new MethodMarkingStore(false);

        $marking = $markingStore->getMarking($subject);

        $this->assertInstanceOf(Marking::class, $marking);
        $this->assertCount(0, $marking->getPlaces());

        $marking->mark($place);

        $markingStore->setMarking($subject, $marking);

        $this->assertSame($expectedResult, $subject->getMarking());

        $marking2 = $markingStore->getMarking($subject);

        $this->assertEquals($marking, $marking2);
    }

    public function providePlaceAndExpectedResultForMultipleState(): \Generator
    {
        yield ['first_place', ['first_place' => 1]];

        if (\PHP_VERSION_ID >= 80100) {
            yield [FooEnum::Bar, [FooEnum::Bar]];
        }
    }

    /**
     * @dataProvider providePlaceAndExpectedResultForSingleState
     */
    public function testGetSetMarkingWithSingleState(string|\UnitEnum $place)
    {
        $subject = new Subject();

        $markingStore = new MethodMarkingStore(true);

        $marking = $markingStore->getMarking($subject);

        $this->assertInstanceOf(Marking::class, $marking);
        $this->assertCount(0, $marking->getPlaces());

        $marking->mark($place);

        $markingStore->setMarking($subject, $marking);

        $this->assertSame($place, $subject->getMarking());

        $marking2 = $markingStore->getMarking($subject);

        $this->assertEquals($marking, $marking2);
    }

    public function providePlaceAndExpectedResultForSingleState(): \Generator
    {
        yield ['first_place'];

        if (\PHP_VERSION_ID >= 80100) {
            yield [FooEnum::Bar];
        }
    }

    public function testGetSetMarkingWithSingleStateAndAlmostEmptyPlaceName()
    {
        $subject = new Subject(0);

        $markingStore = new MethodMarkingStore(true);

        $marking = $markingStore->getMarking($subject);

        $this->assertInstanceOf(Marking::class, $marking);
        $this->assertCount(1, $marking->getPlaces());
    }

    /**
     * @dataProvider provideValueObjectMarkingValue
     */
    public function testGetMarkingWithValueObject(string|\UnitEnum $value)
    {
        $subject = new Subject($this->createValueObject($value));

        $markingStore = new MethodMarkingStore(true);

        $marking = $markingStore->getMarking($subject);

        $this->assertInstanceOf(Marking::class, $marking);
        $this->assertCount(1, $marking->getPlaces());
        $this->assertSame(PlaceEnumerationUtils::getPlaceKey($value), (string) $subject->getMarking());
    }

    public function provideValueObjectMarkingValue(): \Generator
    {
        yield ['first_place'];

        if (\PHP_VERSION_ID >= 80100) {
            yield [FooEnum::Bar];
        }
    }

    private function createValueObject(string|\UnitEnum $markingValue)
    {
        return new class($markingValue) {
            /** @var string|\UnitEnum */
            private $markingValue;

            public function __construct(string|\UnitEnum $markingValue)
            {
                $this->markingValue = $markingValue;
            }

            public function __toString(): string
            {
                return PlaceEnumerationUtils::getPlaceKey($this->markingValue);
            }
        };
    }
}
