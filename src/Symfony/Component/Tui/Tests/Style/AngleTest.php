<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Style;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Style\Angle;

final class AngleTest extends TestCase
{
    public function testDegrees()
    {
        $this->assertEqualsWithDelta(0.0, Angle::degrees(0)->toRadians(), 1e-10);
        $this->assertEqualsWithDelta(\M_PI / 2, Angle::degrees(90)->toRadians(), 1e-10);
        $this->assertEqualsWithDelta(\M_PI, Angle::degrees(180)->toRadians(), 1e-10);
        $this->assertEqualsWithDelta(3 * \M_PI / 2, Angle::degrees(270)->toRadians(), 1e-10);
    }

    public function testRadians()
    {
        $this->assertEqualsWithDelta(0.0, Angle::radians(0.0)->toDegrees(), 1e-10);
        $this->assertEqualsWithDelta(90.0, Angle::radians(\M_PI / 2)->toDegrees(), 1e-10);
        $this->assertEqualsWithDelta(180.0, Angle::radians(\M_PI)->toDegrees(), 1e-10);
        $this->assertEqualsWithDelta(45.0, Angle::radians(\M_PI / 4)->toDegrees(), 1e-10);
    }

    public function testDegreesAndRadiansAreEquivalent()
    {
        $this->assertEqualsWithDelta(
            Angle::degrees(45)->toRadians(),
            Angle::radians(\M_PI / 4)->toRadians(),
            1e-10
        );
    }

    #[DataProvider('nonFiniteProvider')]
    public function testDegreesRejectsNonFiniteValues(float $value)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An angle must be a finite number of degrees');

        Angle::degrees($value);
    }

    #[DataProvider('nonFiniteProvider')]
    public function testRadiansRejectsNonFiniteValues(float $value)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An angle must be a finite number of radians');

        Angle::radians($value);
    }

    public static function nonFiniteProvider(): iterable
    {
        yield 'NAN' => [\NAN];
        yield 'INF' => [\INF];
        yield '-INF' => [-\INF];
    }
}
