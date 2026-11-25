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
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;
use Symfony\Component\HtmlRenderer\Layout\LayoutMetricsInterface;

final class LayoutMetricsTest extends TestCase
{
    public function testExplicitDeviceConstraintsApplyDuringLayoutAndKeepMathFactorsScalar()
    {
        $metrics = new class implements LayoutMetricsInterface {
            public array $axes = [];

            public function resolveUnit(float $number, string $unit, bool $vertical, float $resolved): float
            {
                return '' === $unit ? $number * 10 : $resolved;
            }

            public function constrainLength(float $length, string $property, bool $vertical, ?\Symfony\Component\HtmlRenderer\Font\FontStyle $font = null): float
            {
                $this->axes[$property] = $vertical;

                return 'width' === $property ? floor($length) : ('height' === $property ? ceil($length) : $length);
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
        $engine = new LayoutEngine(new MonospaceTextMeasurer(), $metrics);
        $box = new Box(null, 'block', ['width' => 'calc(5.25px * 2)', 'height' => '5.5px']);
        $fragment = $engine->layout($box, 100, 100);
        self::assertSame(10.0, $fragment->bounds->width);
        self::assertSame(6.0, $fragment->bounds->height);
        self::assertFalse($metrics->axes['width']);
        self::assertTrue($metrics->axes['height']);
        $fragment = $engine->layout(new Box(null, 'block', ['width' => '2']), 100, 100);
        self::assertSame(20.0, $fragment->bounds->width);
    }

    public function testFinalFlexSharesUseExplicitDistributionContract()
    {
        $metrics = new class implements LayoutMetricsInterface {
            public int $distributions = 0;

            public function resolveUnit(float $number, string $unit, bool $vertical, float $resolved): float
            {
                return $resolved;
            }

            public function constrainLength(float $length, string $property, bool $vertical, ?\Symfony\Component\HtmlRenderer\Font\FontStyle $font = null): float
            {
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
                ++$this->distributions;
                $given = 0.0;
                $out = [];
                foreach ($lengths as $length) {
                    $out[] = round($given + $length) - round($given);
                    $given += $length;
                }

                return $out;
            }
        };
        $box = new Box(null, 'flex', [], [new Box(null, 'block', ['flex-grow' => '1']), new Box(null, 'block', ['flex-grow' => '2'])]);
        $fragment = (new LayoutEngine(new MonospaceTextMeasurer(), $metrics))->layout($box, 10, 100);
        self::assertSame(3.0, $fragment->children[0]->bounds->width);
        self::assertSame(7.0, $fragment->children[1]->bounds->width);
        self::assertGreaterThan(0, $metrics->distributions);
    }

    public function testLengthResolutionKeepsAxesAndPropertiesDistinctAndRefreshesBetweenLayouts()
    {
        $metrics = new class implements LayoutMetricsInterface {
            public float $unit = 2.0;

            public function resolveUnit(float $number, string $unit, bool $vertical, float $resolved): float
            {
                return $resolved + (0.0 === $number ? $this->unit : 0);
            }

            public function constrainLength(float $length, string $property, bool $vertical, ?\Symfony\Component\HtmlRenderer\Font\FontStyle $font = null): float
            {
                return $length + match ($property) {
                    'width' => $this->unit, 'height' => $this->unit * 2, default => 0.0,
                };
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
        $engine = new LayoutEngine(new MonospaceTextMeasurer(), $metrics);
        $box = new Box(null, 'block', ['width' => '10px', 'height' => '10px', 'box-sizing' => 'border-box', 'padding' => '0']);
        $first = $engine->layout($box, 100, 100);
        self::assertSame(2.0, $first->bounds->x);
        self::assertSame(12.0, $first->bounds->width);
        self::assertSame(14.0, $first->bounds->height);
        $metrics->unit = 3.0;
        $second = $engine->layout($box, 100, 100);
        self::assertSame(3.0, $second->bounds->x);
        self::assertSame(13.0, $second->bounds->width);
        self::assertSame(16.0, $second->bounds->height);
    }
}
