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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\ColorStop;

final class ColorStopTest extends TestCase
{
    public function testAtAcceptsString()
    {
        $stop = ColorStop::at('red', 30);
        $this->assertSame(Color::named('red')->toBackgroundCode(), $stop->color->toBackgroundCode());
        $this->assertSame(30, $stop->position);
    }

    public function testAtAcceptsColorObject()
    {
        $color = Color::rgb(255, 128, 0);
        $stop = ColorStop::at($color, 50);
        $this->assertSame($color->toBackgroundCode(), $stop->color->toBackgroundCode());
    }

    public function testAtAcceptsPaletteInt()
    {
        $stop = ColorStop::at(196, 100);
        $this->assertSame(Color::palette(196)->toBackgroundCode(), $stop->color->toBackgroundCode());
    }

    public function testAtRejectsPositionBelow0()
    {
        $this->expectException(\InvalidArgumentException::class);
        ColorStop::at('red', -1);
    }

    public function testAtRejectsPositionAbove100()
    {
        $this->expectException(\InvalidArgumentException::class);
        ColorStop::at('red', 101);
    }

    public function testNormalizeAllExplicit()
    {
        $stops = ColorStop::normalize([
            ColorStop::at('#ff0000', 0),
            ColorStop::at('#00ff00', 30),
            ColorStop::at('#0000ff', 100),
        ]);

        $this->assertCount(3, $stops);
        $this->assertEqualsWithDelta(0.0, $stops[0]['offset'], 1e-9);
        $this->assertEqualsWithDelta(0.3, $stops[1]['offset'], 1e-9);
        $this->assertEqualsWithDelta(1.0, $stops[2]['offset'], 1e-9);
    }

    public function testNormalizePlainColorsDistributedUniformly()
    {
        // No positions: first=0, last=1, middle=0.5
        $stops = ColorStop::normalize(['#ff0000', '#00ff00', '#0000ff']);

        $this->assertEqualsWithDelta(0.0, $stops[0]['offset'], 1e-9);
        $this->assertEqualsWithDelta(0.5, $stops[1]['offset'], 1e-9);
        $this->assertEqualsWithDelta(1.0, $stops[2]['offset'], 1e-9);
    }

    public function testNormalizeMixedImplicitPositionsInterpolated()
    {
        // Only the middle stop has an explicit position
        $stops = ColorStop::normalize([
            '#ff0000',                     // → 0
            ColorStop::at('#00ff00', 30),  // → 0.3
            '#0000ff',                     // → 1.0
        ]);

        $this->assertEqualsWithDelta(0.0, $stops[0]['offset'], 1e-9);
        $this->assertEqualsWithDelta(0.3, $stops[1]['offset'], 1e-9);
        $this->assertEqualsWithDelta(1.0, $stops[2]['offset'], 1e-9);
    }

    public function testNormalizeRunOfImplicitsBetweenTwoPositioned()
    {
        // Two plain colors between 0 and 60 → 20 and 40
        $stops = ColorStop::normalize([
            ColorStop::at('#ff0000', 0),
            '#aaaaaa',   // → 20
            '#bbbbbb',   // → 40
            ColorStop::at('#0000ff', 60),
            '#ffffff',   // → 100 (last)
        ]);

        $this->assertEqualsWithDelta(0.0, $stops[0]['offset'], 1e-9);
        $this->assertEqualsWithDelta(0.2, $stops[1]['offset'], 1e-9);
        $this->assertEqualsWithDelta(0.4, $stops[2]['offset'], 1e-9);
        $this->assertEqualsWithDelta(0.6, $stops[3]['offset'], 1e-9);
        $this->assertEqualsWithDelta(1.0, $stops[4]['offset'], 1e-9);
    }

    public function testNormalizeClampsOutOfOrderStopsInsteadOfSortingThem()
    {
        $stops = ColorStop::normalize([
            ColorStop::at('#0000ff', 100),
            ColorStop::at('#00ff00', 50),
            ColorStop::at('#ff0000', 0),
        ]);

        // The declaration order is kept: blue stays first, and the two positions
        // that go backwards are both clamped up to 100.
        $this->assertSame(Color::from('#0000ff')->toBackgroundCode(), $stops[0]['color']->toBackgroundCode());
        $this->assertSame(Color::from('#00ff00')->toBackgroundCode(), $stops[1]['color']->toBackgroundCode());
        $this->assertSame(Color::from('#ff0000')->toBackgroundCode(), $stops[2]['color']->toBackgroundCode());

        $this->assertEqualsWithDelta(1.0, $stops[0]['offset'], 1e-9);
        $this->assertEqualsWithDelta(1.0, $stops[1]['offset'], 1e-9);
        $this->assertEqualsWithDelta(1.0, $stops[2]['offset'], 1e-9);
    }

    public function testNormalizeClampsASingleBackwardsStopToAHardStop()
    {
        // CSS: blue runs from 0 to 80%, then red takes over at a hard stop.
        $stops = ColorStop::normalize([
            ColorStop::at('#0000ff', 80),
            ColorStop::at('#ff0000', 20),
        ]);

        $this->assertSame(Color::from('#0000ff')->toBackgroundCode(), $stops[0]['color']->toBackgroundCode());
        $this->assertEqualsWithDelta(0.8, $stops[0]['offset'], 1e-9);

        $this->assertSame(Color::from('#ff0000')->toBackgroundCode(), $stops[1]['color']->toBackgroundCode());
        $this->assertEqualsWithDelta(0.8, $stops[1]['offset'], 1e-9);
    }

    public function testNormalizeKeepsAlreadyOrderedStopsUntouched()
    {
        $stops = ColorStop::normalize([
            ColorStop::at('#ff0000', 0),
            ColorStop::at('#00ff00', 50),
            ColorStop::at('#0000ff', 100),
        ]);

        $this->assertEqualsWithDelta(0.0, $stops[0]['offset'], 1e-9);
        $this->assertEqualsWithDelta(0.5, $stops[1]['offset'], 1e-9);
        $this->assertEqualsWithDelta(1.0, $stops[2]['offset'], 1e-9);
    }
}
