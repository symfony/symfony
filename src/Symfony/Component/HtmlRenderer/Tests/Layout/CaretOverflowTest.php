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
use Symfony\Component\HtmlRenderer\Paint\Painter;
use Symfony\Component\HtmlRenderer\Text\TextSelection;

final class CaretOverflowTest extends TestCase
{
    public function testEndCaretIsIncludedInScrollableOverflow()
    {
        foreach ([1.0, 8.0] as $caretWidth) {
            $metrics = $this->metrics($caretWidth);
            $value = new Box(null, 'inline', ['white-space' => 'pre'], [], 'ABCDE', null, new TextSelection(5, 5));
            $box = new Box(null, 'block', ['width' => '32px', 'height' => '16px', 'font-size' => '16px', 'line-height' => '16px', 'overflow' => 'hidden', '--html-scroll-x' => '100'], [$value]);
            $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $metrics))->layout($box, 200, 200);
            self::assertSame(8.0 + $caretWidth, $layout->scroll->maxX);
            self::assertSame(8.0 + $caretWidth, $layout->scroll->offsetX);
            $carets = array_values(array_filter((new Painter(null, null, $metrics))->paint($layout)->getOperations(), static fn (array $operation): bool => 'caret' === $operation['type']));
            self::assertCount(1, $carets);
            self::assertSame(32.0 - $caretWidth, $carets[0]['x']);
            self::assertSame($caretWidth, $carets[0]['width']);
        }
    }

    public function testWrappedBoundaryUsesTheFollowingLineWithoutFalseHorizontalOverflow()
    {
        $metrics = $this->metrics(8);
        $value = new Box(null, 'block', ['width' => '24px', 'height' => '32px', 'font-size' => '16px', 'line-height' => '16px', 'overflow' => 'auto'], [], '中文日本', null, new TextSelection(3, 3));
        $layout = (new LayoutEngine(new MonospaceTextMeasurer(), $metrics))->layout($value, 200, 200);
        self::assertSame(0.0, $layout->scroll->maxX);
        self::assertFalse($layout->scroll->horizontalBar);
        self::assertFalse($layout->scroll->verticalBar);
        $carets = array_values(array_filter((new Painter(null, null, $metrics))->paint($layout)->getOperations(), static fn (array $operation): bool => 'caret' === $operation['type']));
        self::assertCount(1, $carets);
        self::assertSame(0.0, $carets[0]['x']);
        self::assertSame(16.0, $carets[0]['y']);
    }

    private function metrics(float $caretWidth): LayoutMetricsInterface
    {
        return new class($caretWidth) implements LayoutMetricsInterface {
            public function __construct(private readonly float $caretWidth)
            {
            }

            public function resolveUnit(float $number, string $unit, bool $vertical, float $resolved): float
            {
                return $resolved;
            }

            public function constrainLength(float $length, string $property, bool $vertical, ?FontStyle $font = null): float
            {
                return 'caret-width' === $property ? $this->caretWidth : $length;
            }

            public function outsideMarkerGutter(float $naturalGroupWidth): float
            {
                return 0;
            }

            public function scrollbarThickness(bool $vertical, bool $thin = false): float
            {
                return $vertical ? 8 : 16;
            }

            public function distribute(array $lengths, bool $vertical): array
            {
                return $lengths;
            }
        };
    }
}
