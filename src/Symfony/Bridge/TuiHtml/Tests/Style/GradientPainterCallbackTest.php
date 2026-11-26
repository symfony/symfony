<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Style;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Render\GradientPainter;
use Symfony\Bridge\TuiHtml\Style\Color;

class GradientPainterCallbackTest extends TestCase
{
    public function testCallbackReceivesMappedSourceCoordinatesAndExtentInsteadOfClipCoordinates()
    {
        $seen = [];
        $paint = static function (int $x, int $y, int $width, int $height) use (&$seen): Color {
            $seen[] = [$x, $y, $width, $height];

            return Color::rgb($x, $y, $width + $height);
        };
        $sample = static fn (int $x, int $y): array => [$x + 7, $y + 9, 30, 40];
        $lines = GradientPainter::paint(['untouched', '  ABC'], $paint, null, 1, 2, 3, 1, $sample, true);
        self::assertSame([[7, 9, 30, 40], [8, 9, 30, 40], [9, 9, 30, 40]], $seen);
        self::assertSame('untouched', $lines[0]);
        self::assertStringContainsString("\x1b[38;2;7;9;70m", $lines[1]);
        self::assertStringContainsString("\x1b[38;2;9;9;70m", $lines[1]);
    }

    public function testExistingTwoArgumentCallbacksCanIgnoreTheSourceExtent()
    {
        $sample = static fn (int $x, int $y): array => [$x + 7, $y + 9, 30, 40];
        $short = static fn (int $x, int $y): Color => Color::rgb($x, $y, 70);
        $full = static fn (int $x, int $y, int $width, int $height): Color => Color::rgb($x, $y, $width + $height);
        self::assertSame(GradientPainter::paint(['ABC'], $full, null, 0, 0, 3, 1, $sample, true), GradientPainter::paint(['ABC'], $short, null, 0, 0, 3, 1, $sample, true));
    }
}
