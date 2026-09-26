<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\WeightedPlace;

final class WeightedPlaceTest extends TestCase
{
    public function testConstructor()
    {
        $place = new WeightedPlace(WeightedBackedPlace::Draft, 3);

        $this->assertSame(WeightedBackedPlace::Draft, $place->place);
        $this->assertSame(3, $place->weight);
    }

    #[DataProvider('provideInvalidPlaces')]
    public function testConstructorRejectsInvalidPlace(\BackedEnum $place, string $message)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new WeightedPlace($place, 1);
    }

    public static function provideInvalidPlaces(): iterable
    {
        yield 'integer-backed' => [WeightedIntegerPlace::Draft, 'Integer-backed enums cannot be used as workflow places.'];
        yield 'empty string-backed' => [WeightedEmptyPlace::Draft, 'The place name cannot be empty.'];
    }

    public function testConstructorRejectsInvalidWeight()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The weight must be greater than 0, 0 given.');

        new WeightedPlace(WeightedBackedPlace::Draft, 0);
    }
}

enum WeightedBackedPlace: string
{
    case Draft = 'draft';
}

enum WeightedIntegerPlace: int
{
    case Draft = 1;
}

enum WeightedEmptyPlace: string
{
    case Draft = '';
}
