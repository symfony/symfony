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
use Symfony\Component\HtmlRenderer\Font\TextMeasurerInterface;
use Symfony\Component\HtmlRenderer\Font\TextMetrics;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Layout\LayoutMetricsInterface;
use Symfony\Component\HtmlRenderer\Paint\Painter;
use Symfony\Component\HtmlRenderer\RenderingResult;

final class InlineBaselineTest extends TestCase
{
    private function measurer(): TextMeasurerInterface
    {
        return new class implements TextMeasurerInterface {
            public function measure(string $text, FontStyle $font): TextMetrics
            {
                return new TextMetrics(\strlen($text) * 5.0, 'low' === $font->family ? 5.0 : 8.0, 'low' === $font->family ? 5.0 : 2.0);
            }
        };
    }

    public function testDifferentFontMetricsShareOneBaselineAndExpandLineDescent()
    {
        $box = new Box(null, 'block', ['font-size' => '10px', 'line-height' => '10px'], [new Box(null, 'inline', ['font-family' => 'low'], [], 'A'), new Box(null, 'inline', [], [], 'B')]);
        $layout = (new LayoutEngine($this->measurer()))->layout($box, 100, 100);
        $text = array_values(array_filter((new Painter())->paint($layout)->getOperations(), static fn (array $op): bool => 'text' === $op['type']));
        self::assertSame(8.0, $text[0]['baseline']);
        self::assertSame(8.0, $text[1]['baseline']);
        self::assertSame(3.0, $text[0]['y']);
        self::assertSame(13.0, $layout->bounds->height);
    }

    public function testSuperSubAndPercentageOffsetsRemainFractionalAndSemantic()
    {
        foreach ([['super', 5.0], ['sub', -2.5], ['22.5%', 2.25], ['2.25px', 2.25]] as [$align,$shift]) {
            $box = new Box(null, 'block', ['font-size' => '10px', 'line-height' => '10px'], [new Box(null, 'inline', [], [], 'A'), new Box(null, 'inline', ['vertical-align' => $align], [], '2')]);
            $layout = (new LayoutEngine($this->measurer()))->layout($box, 100, 100);
            $text = array_values(array_filter((new Painter())->paint($layout)->getOperations(), static fn (array $op): bool => 'text' === $op['type']));
            self::assertSame($shift, $text[0]['baseline'] - $text[1]['baseline']);
            self::assertSame($shift, $text[1]['baseline-shift']);
            if (\in_array($align, ['super', 'sub'], true)) {
                self::assertSame($align, $text[1]['vertical-align']);
                self::assertSame('2', $text[1]['text']);
            }
        }
    }

    public function testTopAndBottomUseLineBoxEdges()
    {
        $box = new Box(null, 'block', ['font-size' => '10px', 'line-height' => '10px'], [new Box(null, 'inline-block', ['height' => '20px', 'width' => '5px']), new Box(null, 'inline', ['vertical-align' => 'top'], [], 'T'), new Box(null, 'inline', ['vertical-align' => 'bottom'], [], 'B')]);
        $layout = (new LayoutEngine($this->measurer()))->layout($box, 100, 100);
        self::assertSame(0.0, $layout->children[1]->bounds->y);
        self::assertSame(12.0, $layout->children[2]->bounds->y);
        self::assertSame(22.0, $layout->bounds->height);
    }

    public function testMiddleUsesParentMetricApproximationAndRelativeOffsetsRemainVisual()
    {
        $box = new Box(null, 'block', ['font-size' => '10px', 'line-height' => '10px'], [new Box(null, 'inline', [], [], 'A'), new Box(null, 'inline', ['vertical-align' => 'middle'], [], 'M')]);
        $layout = (new LayoutEngine($this->measurer()))->layout($box, 100, 100);
        $text = array_values(array_filter((new Painter())->paint($layout)->getOperations(), static fn (array $op): bool => 'text' === $op['type']));
        self::assertSame(.5, $text[1]['y']);
        self::assertSame('middle', $text[1]['vertical-align']);
        self::assertSame(10.5, $layout->bounds->height);
        $box = new Box(null, 'block', ['font-size' => '10px', 'line-height' => '10px'], [new Box(null, 'inline', ['position' => 'relative', 'top' => '2.25px'], [], 'A'), new Box(null, 'inline', [], [], 'B')]);
        $layout = (new LayoutEngine($this->measurer()))->layout($box, 100, 100);
        self::assertSame(2.25, $layout->children[0]->bounds->y);
        self::assertSame(0.0, $layout->children[1]->bounds->y);
        self::assertSame(10.0, $layout->bounds->height);
    }

    public function testDeviceConstraintsQuantizeUsedBaselinePositionBeforeHitTesting()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><main><input></main>');
        $input = $document->querySelector('input');
        $box = new Box($document->querySelector('main'), 'block', ['font-size' => '16px', 'line-height' => '16px'], [new Box(null, 'inline-block', ['width' => '10px', 'height' => '28.8px']), new Box($input, 'inline-block', ['width' => '10px', 'height' => '16px'])]);
        $measurer = new MonospaceTextMeasurer();
        $headless = (new LayoutEngine($measurer))->layout($box, 100, 100);
        self::assertEqualsWithDelta(12.8, $headless->children[1]->bounds->y, 1e-12);
        $metrics = new class implements LayoutMetricsInterface {
            public function resolveUnit(float $number, string $unit, bool $vertical, float $resolved): float
            {
                return $resolved;
            }

            public function constrainLength(float $length, string $property, bool $vertical, ?FontStyle $font = null): float
            {
                return 'inline-baseline-position' === $property ? floor($length / 16) * 16 : $length;
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
        $device = (new LayoutEngine($measurer, $metrics))->layout($box, 100, 100);
        self::assertSame(0.0, $device->children[1]->bounds->y);
        $result = new RenderingResult($document, new \SplObjectStorage(), $device, (new Painter())->paint($device));
        self::assertSame($input, $result->hitTest(15, 8));
    }
}
