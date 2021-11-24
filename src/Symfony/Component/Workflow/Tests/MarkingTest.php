<?php

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Tests\fixtures\FooEnum;

class MarkingTest extends TestCase
{
    public function testMarkingWithPlacesAsString()
    {
        $marking = new Marking(['a' => 1]);

        $this->assertTrue($marking->has('a'));
        $this->assertFalse($marking->has('b'));
        $this->assertSame(['a' => 1], $marking->getPlaces());

        $marking->mark('b');

        $this->assertTrue($marking->has('a'));
        $this->assertTrue($marking->has('b'));
        $this->assertSame(['a' => 1, 'b' => 1], $marking->getPlaces());

        $marking->unmark('a');

        $this->assertFalse($marking->has('a'));
        $this->assertTrue($marking->has('b'));
        $this->assertSame(['b' => 1], $marking->getPlaces());

        $marking->unmark('b');

        $this->assertFalse($marking->has('a'));
        $this->assertFalse($marking->has('b'));
        $this->assertSame([], $marking->getPlaces());
    }

    /**
     * @requires PHP 8.1
     */
    public function testMarkingWithPlacesAsEnumerations()
    {
        $marking = new Marking([FooEnum::Bar]);

        $this->assertTrue($marking->has(FooEnum::Bar));
        $this->assertFalse($marking->has(FooEnum::Baz));
        $this->assertSame(['Symfony\Component\Workflow\Tests\fixtures\FooEnum::Bar' => FooEnum::Bar], $marking->getPlaces());

        $marking->mark(FooEnum::Baz);

        $this->assertTrue($marking->has(FooEnum::Bar));
        $this->assertTrue($marking->has(FooEnum::Baz));
        $this->assertSame(['Symfony\Component\Workflow\Tests\fixtures\FooEnum::Bar' => FooEnum::Bar, 'Symfony\Component\Workflow\Tests\fixtures\FooEnum::Baz' => FooEnum::Baz], $marking->getPlaces());

        $marking->unmark(FooEnum::Bar);

        $this->assertFalse($marking->has(FooEnum::Bar));
        $this->assertTrue($marking->has(FooEnum::Baz));
        $this->assertSame(['Symfony\Component\Workflow\Tests\fixtures\FooEnum::Baz' => FooEnum::Baz], $marking->getPlaces());

        $marking->unmark(FooEnum::Baz);

        $this->assertFalse($marking->has(FooEnum::Bar));
        $this->assertFalse($marking->has(FooEnum::Baz));
        $this->assertSame([], $marking->getPlaces());
    }
}
