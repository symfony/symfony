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
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\Shadow;
use Symfony\Component\Tui\Style\ShadowOrientation;

final class ShadowTest extends TestCase
{
    // ---------------------------------------------------------------
    // getChar, spread=true (gradient)
    // ---------------------------------------------------------------

    /**
     * @param array{int, string} $cases [distance => expectedChar]
     */
    #[DataProvider('getCharSpreadProvider')]
    public function testGetCharWithSpread(int $density, array $cases)
    {
        $shadow = new Shadow(density: $density, spread: true);

        foreach ($cases as [$distance, $expectedChar]) {
            $this->assertSame($expectedChar, $shadow->getChar($distance), "density=$density, distance=$distance");
        }
    }

    /**
     * @return iterable<string, array{int, array<array{int, string}>}>
     */
    public static function getCharSpreadProvider(): iterable
    {
        yield 'density 4 fades outward' => [4, [
            [1, '█'],
            [2, '▓'],
            [3, '▒'],
            [4, '░'],
        ]];
        yield 'density 3 fades outward' => [3, [
            [1, '▓'],
            [2, '▒'],
            [3, '░'],
            [4, '░'], // clamped at ░
        ]];
        yield 'density 2 fades outward' => [2, [
            [1, '▒'],
            [2, '░'],
            [3, '░'], // clamped
        ]];
        yield 'density 1 always lightest' => [1, [
            [1, '░'],
            [2, '░'],
            [3, '░'],
        ]];
    }

    // ---------------------------------------------------------------
    // getChar, spread=false (solid)
    // ---------------------------------------------------------------

    #[DataProvider('getCharSolidProvider')]
    public function testGetCharWithoutSpreadReturnsSameCharAtAllDistances(int $density, string $expectedChar)
    {
        $shadow = new Shadow(density: $density, spread: false);

        $this->assertSame($expectedChar, $shadow->getChar(1));
        $this->assertSame($expectedChar, $shadow->getChar(2));
        $this->assertSame($expectedChar, $shadow->getChar(3));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function getCharSolidProvider(): iterable
    {
        yield 'density 1' => [1, '░'];
        yield 'density 2' => [2, '▒'];
        yield 'density 3' => [3, '▓'];
        yield 'density 4' => [4, '█'];
    }

    // ---------------------------------------------------------------
    // color
    // ---------------------------------------------------------------

    public function testColorDefaultsToGray()
    {
        $shadow = new Shadow();

        $this->assertSame(Color::named('gray')->toForegroundCode(), $shadow->color->toForegroundCode());
    }

    public function testCustomColorIsKept()
    {
        $shadow = new Shadow(color: Color::named('red'));

        $this->assertSame(Color::named('red')->toForegroundCode(), $shadow->color->toForegroundCode());
    }

    // ---------------------------------------------------------------
    // clamping
    // ---------------------------------------------------------------

    public function testDensityClampedToMin()
    {
        $this->assertSame(1, (new Shadow(density: 0))->density);
    }

    public function testDensityClampedToMax()
    {
        $this->assertSame(4, (new Shadow(density: 5))->density);
    }

    public function testOffsetClampedToMin()
    {
        $this->assertSame(1, (new Shadow(offset: 0))->offset);
    }

    public function testOffsetClampedToMax()
    {
        $this->assertSame(3, (new Shadow(offset: 4))->offset);
    }

    // ---------------------------------------------------------------
    // defaults
    // ---------------------------------------------------------------

    public function testDefaultOrientationIsBottomRight()
    {
        $shadow = new Shadow();

        $this->assertSame(ShadowOrientation::BottomRight, $shadow->orientation);
    }

    public function testDefaultParameterValues()
    {
        $shadow = new Shadow();

        $this->assertSame(2, $shadow->density);
        $this->assertSame(1, $shadow->offset);
        $this->assertTrue($shadow->spread);
    }
}
