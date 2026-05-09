<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Render;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Render\WidgetRect;

class WidgetRectTest extends TestCase
{
    #[DataProvider('containsProvider')]
    public function testContains(int $row, int $col, bool $expected)
    {
        // Rect at (5, 10) with size 40x20 → covers rows 5..24, cols 10..49
        $rect = new WidgetRect(5, 10, 40, 20);

        $this->assertSame($expected, $rect->contains($row, $col));
    }

    /**
     * @return iterable<string, array{int, int, bool}>
     */
    public static function containsProvider(): iterable
    {
        // Inside
        yield 'top-left corner' => [5, 10, true];
        yield 'center' => [15, 30, true];
        yield 'bottom-right edge (just inside)' => [24, 49, true];

        // Outside
        yield 'above' => [4, 20, false];
        yield 'below' => [25, 20, false];
        yield 'left' => [10, 9, false];
        yield 'right' => [10, 50, false];
        yield 'bottom-right (just outside row)' => [25, 49, false];
        yield 'bottom-right (just outside col)' => [24, 50, false];
    }

    public function testToRelative()
    {
        $rect = new WidgetRect(5, 10, 40, 20);

        $this->assertSame(['row' => 0, 'col' => 0], $rect->toRelative(5, 10));
        $this->assertSame(['row' => 10, 'col' => 20], $rect->toRelative(15, 30));
        $this->assertSame(['row' => 19, 'col' => 39], $rect->toRelative(24, 49));
    }
}
