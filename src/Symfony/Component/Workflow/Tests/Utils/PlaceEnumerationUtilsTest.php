<?php

namespace Symfony\Component\Workflow\Tests\Utils;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Tests\Fixtures\FooEnum;
use Symfony\Component\Workflow\Utils\PlaceEnumerationUtils;

class PlaceEnumerationUtilsTest extends TestCase
{
    public function testPlaceKey()
    {
        $this->assertSame('my_place', PlaceEnumerationUtils::getPlaceKey('my_place'));

        if (\PHP_VERSION_ID >= 80100) {
            $this->assertSame('Symfony\Component\Workflow\Tests\fixtures\FooEnum::Bar', PlaceEnumerationUtils::getPlaceKey(FooEnum::Bar));
        }
    }

    public function testTypedValue()
    {
        $this->assertSame('my_place', PlaceEnumerationUtils::getTypedValue('my_place'));

        if (\PHP_VERSION_ID >= 80100) {
            $this->assertSame(FooEnum::Bar, PlaceEnumerationUtils::getTypedValue('Symfony\Component\Workflow\Tests\fixtures\FooEnum::Bar'));
        }
    }
}
