<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Style;

use PHPUnit\Framework\TestCase;
use Symfony\Component\TuiHtml\Css\DeclarationMapper;
use Symfony\Component\TuiHtml\Render\GradientPainter;
use Symfony\Component\TuiHtml\Style\BackgroundLength;
use Symfony\Component\TuiHtml\Style\Color;
use Symfony\Component\TuiHtml\Style\Gradient;
use Symfony\Component\TuiHtml\Style\MediaContext;

final class GradientTest extends TestCase
{
    public function testColorsAlongTheLine()
    {
        $gradient = new Gradient('linear', 90, [[Color::hex('#000000'), 0.0], [Color::hex('#ff0000'), 0.5], [Color::hex('#ffffff'), 1.0]]);
        $this->assertSame('#000000', $gradient->at(-1)->toHex());
        $this->assertSame('#800000', $gradient->at(0.25)->toHex());
        $this->assertSame('#ff0000', $gradient->at(0.5)->toHex());
        $this->assertSame('#ffffff', $gradient->at(2)->toHex());
        $this->assertSame('#ff0000', $gradient->colorAt(0, 0, 1, 1)->toHex(), 'One cell: the middle of the line.');
    }

    public function testDirections()
    {
        $toRight = new Gradient('linear', 90, [[Color::hex('#000000'), 0.0], [Color::hex('#ffffff'), 1.0]]);
        $this->assertLessThan($this->gray($toRight->colorAt(9, 0, 10, 3)), $this->gray($toRight->colorAt(0, 0, 10, 3)));
        $this->assertSame($toRight->colorAt(4, 0, 10, 3)->toHex(), $toRight->colorAt(4, 2, 10, 3)->toHex(), 'To the right: the same down a column.');
        $toBottom = new Gradient('linear', 180, $toRight->stops);
        $this->assertLessThan($this->gray($toBottom->colorAt(0, 2, 10, 3)), $this->gray($toBottom->colorAt(0, 0, 10, 3)));
        $radial = new Gradient('radial', 0, $toRight->stops);
        $this->assertLessThan($this->gray($radial->colorAt(0, 0, 10, 5)), $this->gray($radial->colorAt(5, 2, 10, 5)), 'Radial: darker at the center.');
    }

    public function testVerticalGradientsPaintEveryColumnOfARowTheSame()
    {
        $stops = [[Color::hex('#000000'), 0.0], [Color::hex('#ffffff'), 1.0]];
        $this->assertTrue((new Gradient('linear', 180, $stops))->isVertical());
        $this->assertTrue((new Gradient('linear', 0, $stops))->isVertical());
        $this->assertFalse((new Gradient('linear', 90, $stops))->isVertical());
        $this->assertFalse((new Gradient('linear', 135, $stops))->isVertical());
        $this->assertFalse((new Gradient('radial', 0, $stops))->isVertical());
        // Painted from the colors of a row, those of its first cell, where a diagonal one differs from column to column
        $lines = GradientPainter::paint(array_fill(0, 3, str_repeat(' ', 4)), new Gradient('linear', 180, $stops), null, 0, 0, 4, 3);
        $this->assertCount(1, array_unique(preg_split('/(?=\e\[48)/', $lines[1], -1, \PREG_SPLIT_NO_EMPTY)), 'The same color across the row.');
        $this->assertNotSame($lines[0], $lines[2]);
        $diagonal = GradientPainter::paint(array_fill(0, 3, str_repeat(' ', 4)), new Gradient('linear', 135, $stops), null, 0, 0, 4, 3);
        $this->assertNotSame($diagonal[1], $lines[1]);
    }

    public function testRepeatingAndConic()
    {
        $black = Color::hex('#000000');
        $white = Color::hex('#ffffff');
        $stripes = new Gradient('linear', 90, Gradient::resolve([[$black, 0.0, 0.0], [$black, 0.0, 2.0], [$white, 0.0, 2.0], [$white, 0.0, 4.0]], 0.0), true, 0.0, [[$black, 0.0, 0.0], [$black, 0.0, 2.0], [$white, 0.0, 2.0], [$white, 0.0, 4.0]]);
        $this->assertSame('#000000#000000#ffffff#ffffff#000000#000000#ffffff#ffffff', implode('', array_map(static fn (int $column) => $stripes->colorAt($column, 0, 8, 1)->toHex(), range(0, 7))), 'Stops of 2 cells, again and again.');
        $quarters = new Gradient('conic', 180, [[$white, 0.0], [$white, 0.25], [$black, 0.25], [$black, 0.5], [$white, 0.5], [$white, 0.75], [$black, 0.75], [$black, 1.0]], false, 90.0);
        $this->assertSame('#ffffff', $quarters->colorAt(0, 0, 4, 2)->toHex(), 'From 90deg: the top left is the fourth quarter.');
        $this->assertSame('#000000', $quarters->colorAt(3, 0, 4, 2)->toHex());
        $this->assertSame('#000000', $quarters->colorAt(0, 1, 4, 2)->toHex());
        $this->assertSame('#ffffff', $quarters->colorAt(3, 1, 4, 2)->toHex());
        $this->assertSame([0.0, 0.25, 0.625, 1.0], array_map(static fn (array $stop) => $stop[1], Gradient::resolve([[$black, null, 0.0], [$white, 0.0, 2.0], [$black, null, 0.0], [$white, null, 0.0]], 8.0)), 'Cells over the length of the line, the others spread.');
    }

    public function testRadialExtents()
    {
        $stops = [[Color::hex('#ffffff'), 0.0], [Color::hex('#000000'), 1.0]];
        $at = static fn (Gradient $g, int $column, int $row) => $g->colorAt($column, $row, 20, 5)->toRgb()['r'];
        $closest = new Gradient('radial', 0, $stops, false, 0.0, null, true, 'closest-side', [new BackgroundLength(0.0, 5), new BackgroundLength(50.0)]);
        $this->assertSame(0, $at($closest, 0, 0), 'A circle of 5 columns at the fifth: the corner is past it.');
        $this->assertGreaterThan(200, $at($closest, 4, 2));
        $this->assertSame(0, $at($closest, 15, 2));
        $farthest = new Gradient('radial', 0, $stops, false, 0.0, null, true, 'farthest-corner', [new BackgroundLength(0.0, 5), new BackgroundLength(50.0)]);
        $this->assertGreaterThan(0, $at($farthest, 15, 2), 'To the farthest corner: the far side is inside.');
    }

    public function testParsing()
    {
        $map = static fn (string $property, string $value, ?MediaContext $media = null) => (new DeclarationMapper())->map([$property => $value], $media ?? new MediaContext());
        $style = $map('background-image', 'linear-gradient(to right, #000000, #ff0000 20%, #ffffff)');
        $gradient = $style->getGradient();
        $this->assertSame(90.0, round($gradient->angle, 6));
        $this->assertSame([0.0, 0.2, 1.0], array_map(static fn (array $stop) => $stop[1], $gradient->stops));
        $this->assertSame('#000000', $style->getBackground()?->toHex(), 'The first color is the background under it.');
        $this->assertSame(45.0, round($map('background', 'linear-gradient(to top right, red, blue)')->getGradient()->angle, 6));
        $this->assertSame(180.0, $map('background', 'linear-gradient(0.5turn, red, blue)')->getGradient()->angle);
        $this->assertSame([0.0, 0.25, 0.5, 0.75, 1.0], array_map(static fn (array $stop) => $stop[1], $map('background-image', 'linear-gradient(red, orange, yellow, green, blue)')->getGradient()->stops), 'Spread evenly.');
        $this->assertSame([0.0, 0.5, 0.5, 1.0], array_map(static fn (array $stop) => $stop[1], $map('background-image', 'linear-gradient(red 0 50%, blue 30%, green)')->getGradient()->stops), 'Never before the previous one.');
        $this->assertSame('radial', $map('background-image', 'radial-gradient(circle at center, red, blue)')->getGradient()->kind);
        $this->assertSame('#123456', $map('background', '#123456 linear-gradient(red, blue)')->getBackground()?->toHex(), 'A color with it: under it.');
        $this->assertTrue($map('background', 'repeating-linear-gradient(45deg, red 0 2ch, blue 2ch 4ch)')->getGradient()->repeating);
        $this->assertNotNull($map('background', 'repeating-linear-gradient(45deg, red 0 2ch, blue 2ch 4ch)')->getGradient()->rawStops, 'Lengths are resolved when painted.');
        $this->assertNull($map('background', 'linear-gradient(red 10%, blue)')->getGradient()->rawStops);
        $conic = $map('background-image', 'conic-gradient(from 0.25turn at 10% 20%, red, blue 90deg, green)')->getGradient();
        $this->assertSame(['conic', 90.0], [$conic->kind, $conic->from]);
        $this->assertSame([0.0, 0.25, 1.0], array_map(static fn (array $stop) => $stop[1], $conic->stops), 'Angles are shares of a turn.');
        $this->assertTrue($map('background-image', 'repeating-radial-gradient(red, blue 2ch)')->getGradient()->repeating);
        $this->assertNull($map('background-image', 'conic-gradient(red 2ch, blue)')->getGradient(), 'No lengths around a conic one.');
        $spot = $map('background-image', 'radial-gradient(circle closest-side at 20% 50%, red, blue)')->getGradient();
        $this->assertSame([true, 'closest-side'], [$spot->circle, $spot->extent]);
        $this->assertEquals(new BackgroundLength(20.0), $spot->center[0]);
        $this->assertEquals([new BackgroundLength(0.0, 4), new BackgroundLength(50.0)], $map('background-image', 'radial-gradient(4ch 50%, red, blue)')->getGradient()->extent, 'Two radii: an ellipse.');
        $this->assertTrue($map('background-image', 'radial-gradient(3ch, red, blue)')->getGradient()->circle, 'One radius: a circle.');
        $this->assertNull($map('background-image', 'radial-gradient(closest-side 3ch, red, blue)')->getGradient());
        $this->assertNull($map('background-image', 'conic-gradient(at nowhere, red, blue)')->getGradient());
        $this->assertEquals(new BackgroundLength(25.0), $map('background-image', 'conic-gradient(from 90deg at 25% 50%, red, blue)')->getGradient()->center[0]);
        $this->assertNull($map('background-image', 'linear-gradient(to nowhere, red)')->getGradient());
        $this->assertNull($map('background-image', 'url(a.png)')->getGradient());
        $this->assertNull($map('background-image', 'linear-gradient(red, blue)', new MediaContext(80, 24, \Symfony\Component\TuiHtml\Style\ColorScheme::Dark, \Symfony\Component\TuiHtml\Style\Density::Regular, false, true))->getGradient(), 'Forced colors: none.');
    }

    public function testPaintingKeepsWhatHasItsOwnBackground()
    {
        $gradient = new Gradient('linear', 90, [[Color::hex('#000000'), 0.0], [Color::hex('#ffffff'), 1.0]]);
        $base = Color::hex('#000000');
        [$line] = GradientPainter::paint(["\x1b[48;2;0;0;0mab\x1b[41mc\x1b[48;2;0;0;0md\x1b[0m"], $gradient, $base, 0, 0, 4, 1);
        $this->assertSame('abcd', preg_replace('/\e\[[0-9;]*m/', '', $line));
        $this->assertStringContainsString("\x1b[41mc", $line, 'The red cell stays red.');
        $this->assertSame(3, preg_match_all('/\e\[48;2;(\d+);\1;\1m[abd]/', $line), 'The others take the gradient.');
        // codespell:ignore abd
    }

    public function testTranslucentStopsArePremultiplied()
    {
        $red = Color::hex('#ff0000');
        $gradient = new Gradient('linear', 90, [[$red, 0.0], [Color::hex('#000000'), 1.0, 0.0]]);
        $this->assertTrue($gradient->isTranslucent());
        $this->assertSame('#ff8080', $gradient->paintAt(5, 0, 11, 1, Color::hex('#ffffff'))->toHex(), 'Half red over white: the black of transparent not mixed in.');
        $this->assertSame('#800000', $gradient->paintAt(5, 0, 11, 1)->toHex(), 'Over black without a backdrop.');
        $this->assertSame('#800080', $gradient->withBackdrop(Color::hex('#0000ff'))->paintAt(5, 0, 11, 1)->toHex(), 'Over the backdrop.');
        $this->assertNull((new Gradient('linear', 90, [[$red, 0.0], [$red, 0.5], [$red, 0.5, 0.0], [$red, 1.0, 0.0]]))->paintAt(9, 0, 10, 1), 'Transparent: nothing painted.');
        $this->assertFalse((new Gradient('linear', 90, [[$red, 0.0], [$red, 1.0]]))->isTranslucent());
    }

    public function testTransparentStopsAreParsed()
    {
        $media = new MediaContext();
        $parse = static fn (string $value) => (new \ReflectionMethod(DeclarationMapper::class, 'gradient'))->invoke(null, $value, $media);
        $this->assertTrue($parse('linear-gradient(red, transparent)')->isTranslucent());
        $this->assertTrue($parse('linear-gradient(rgb(255 0 0 / .5) 1ch, blue)')->isTranslucent(), 'With a length, resolved when painted.');
        $this->assertSame('#808080', $parse('linear-gradient(to right, #ffffff80, #ffffff80)')->paintAt(0, 0, 2, 1)->toHex());
    }

    public function testATransparentStopFadesTheColorInAnyColorSpace()
    {
        $media = new MediaContext();
        $parse = static fn (string $value) => (new \ReflectionMethod(DeclarationMapper::class, 'gradient'))->invoke(null, $value, $media);
        $white = Color::hex('#ffffff');
        foreach (['srgb', 'oklab', 'oklch'] as $space) {
            $red = $parse('linear-gradient(to right in '.$space.', transparent, #ff0000)')->paintAt(5, 0, 11, 1, $white)->toRgb();
            $this->assertSame([255, 128, 128], [$red['r'], $red['g'], $red['b']], $space.': half the red over white, not a gray from black.');
        }
    }

    public function testTheColorsKeptForAPlaceOnTheLineAreThoseOfItsBoxAndOfWhatIsUnder()
    {
        $gradient = new Gradient('linear', 135, [[Color::hex('#ff0000'), 0.0, 0.5], [Color::hex('#0000ff'), 1.0, 0.5]]);
        // The same place on the line, over two colors: each blended over its own
        $this->assertNotEquals($gradient->paintAt(5, 5, 20, 10, Color::hex('#000000'))?->toRgb(), $gradient->paintAt(5, 5, 20, 10, Color::hex('#ffffff'))?->toRgb());
        // The same cell of boxes of other sizes, at another place on the line
        $this->assertNotEquals($gradient->paintAt(0, 0, 20, 10)?->toRgb(), $gradient->paintAt(0, 0, 4, 2)?->toRgb());
        // Asked again, the same
        $this->assertEquals($gradient->paintAt(3, 7, 20, 10, Color::hex('#00ff00'))?->toRgb(), $gradient->paintAt(3, 7, 20, 10, Color::hex('#00ff00'))?->toRgb());
    }

    private function gray(Color $color): int
    {
        return $color->toRgb()['r'];
    }
}
