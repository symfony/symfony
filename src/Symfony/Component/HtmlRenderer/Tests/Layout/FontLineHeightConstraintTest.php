<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Layout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Layout\LayoutMetricsInterface;

final class FontLineHeightConstraintTest extends TestCase
{
    public function testStandaloneAuthoredLineHeightMayBeSmallerThanMeasuredGlyphHeight()
    {
        $root = $this->box();
        $layout = (new LayoutEngine(new MonospaceTextMeasurer()))->layout($root, 200, 200);
        self::assertSame(10.0, $layout->children[0]->bounds->height);
        self::assertSame(10.0, $layout->children[1]->bounds->y);
        self::assertSame(20.0, $layout->bounds->height);
    }

    public function testExplicitDevicePolicyReceivesResolvedFontContext()
    {
        $metrics = new class implements LayoutMetricsInterface {
            public array $fonts = [];

            public function resolveUnit(float $number, string $unit, bool $vertical, float $resolved): float
            {
                return $resolved;
            }

            public function constrainLength(float $length, string $property, bool $vertical, ?FontStyle $font = null): float
            {
                if ('line-height' === $property && null !== $font) {
                    $this->fonts[] = $font;

                    return max($length, $font->size);
                }

                return $length;
            }

            public function outsideMarkerGutter(float $naturalGroupWidth): float
            {
                return 0.0;
            }

            public function scrollbarThickness(bool $vertical, bool $thin = false): float
            {
                return 0.0;
            }

            public function distribute(array $lengths, bool $vertical): array
            {
                return $lengths;
            }
        };
        $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $metrics))->layout($this->box(), 200, 200);
        self::assertSame(40.0, $layout->children[0]->bounds->height);
        self::assertSame(40.0, $layout->children[1]->bounds->y);
        self::assertSame(80.0, $layout->bounds->height);
        self::assertSame('Example', $metrics->fonts[0]->family);
        self::assertSame(700, $metrics->fonts[0]->weight);
        self::assertSame('rtl', $metrics->fonts[0]->direction);
    }

    private function box(): Box
    {
        return new Box(null, 'block', ['font-family' => 'Example', 'font-size' => '40px', 'font-weight' => '700', 'direction' => 'rtl', 'line-height' => '10px'], [new Box(null, 'block', [], [], 'A'), new Box(null, 'block', [], [], 'B')]);
    }
}
