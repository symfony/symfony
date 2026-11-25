<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Font;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Font\TextMeasurerInterface;
use Symfony\Component\HtmlRenderer\Font\TextMetrics;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;

final class TextMeasurementTest extends TestCase
{
    public function testDeterministicMetricsCountGraphemeClusters()
    {
        $measure = new MonospaceTextMeasurer();
        $metrics = $measure->measure("a\u{0301}b", new FontStyle('sans-serif', 10.5));
        self::assertSame(10.5, $metrics->width);
        self::assertSame(8.4, $metrics->ascent);
        self::assertEqualsWithDelta(2.1, $metrics->descent, 0.00001);
    }

    public function testProportionalMetricsControlWrappingAndBaseline()
    {
        $measure = new class implements TextMeasurerInterface {
            public function measure(string $text, FontStyle $font): TextMetrics
            {
                $width = 0.0;
                foreach (str_split($text) as $letter) {
                    $width += match ($letter) {
                        'W' => 12.5,
                        'i' => 2.25,
                        default => 3.0,
                    };
                }

                return new TextMetrics($width, 7.25, 1.75);
            }
        };
        $box = new Box(null, 'block', ['font-size' => '10px', 'line-height' => '12px'], [], 'Wi Wi');
        $layout = (new LayoutEngine($measure))->layout($box, 20.0, 100.0);
        self::assertSame(['Wi', 'Wi'], array_column($layout->children, 'text'));
        self::assertSame(14.75, $layout->children[0]->bounds->width);
        self::assertSame('8.75', $layout->children[0]->style['-renderer-baseline']);
    }

    public function testInvalidMetricsAreRejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        new TextMetrics(\INF, 1.0, 1.0);
    }

    public function testShapingMetricsCanSupplyNonMonotonicCaretPositions()
    {
        $font = new FontStyle('sans-serif', 10, 12, 400, 'rtl');
        $metrics = new TextMetrics(15, 8, 2, [10.0, 5.0, 0.0, 15.0]);
        self::assertSame('rtl', $font->direction);
        self::assertSame([10.0, 5.0, 0.0, 15.0], $metrics->caretPositions);
    }

    public function testNonFiniteCaretPositionsAreRejected()
    {
        $this->expectException(\InvalidArgumentException::class);
        new TextMetrics(15, 8, 2, [0.0, \NAN]);
    }
}
