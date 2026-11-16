<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Widget;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\TuiHtml\Render\RenderContext;
use Symfony\Component\TuiHtml\Widget\SliderWidget;

final class SliderWidgetTest extends TestCase
{
    public function testKeysMoveTheValueWithinTheBounds()
    {
        $slider = new SliderWidget(0, 10, 2, 4);
        $changes = [];
        $slider->onChange(static function ($event) use (&$changes) {
            $changes[] = $event->getValue();
        });
        $slider->handleInput("\x1b[C");
        $this->assertSame(6.0, $slider->getValue());
        $slider->handleInput("\x1b[F");
        $this->assertSame(10.0, $slider->getValue());
        $slider->handleInput("\x1b[C");
        $this->assertSame(10.0, $slider->getValue(), 'Within max.');
        $slider->handleInput("\x1b[H");
        $this->assertSame(0.0, $slider->getValue());
        $this->assertSame(['6', '10', '0'], $changes, 'A change per move, none when nothing moved.');
    }

    public function testTheValueSnapsToTheStep()
    {
        $this->assertSame(4.0, (new SliderWidget(0, 10, 2, 4.9))->getValue());
        $this->assertSame(8.0, (new SliderWidget(0, 9, 2, 9))->getValue(), 'The highest step below the maximum.');
        $this->assertSame(50.0, (new SliderWidget())->getValue(), 'The middle by default.');
        $this->assertSame('0.3', (new SliderWidget(0, 1, 0.1, 0.3))->getValueText());
    }

    public function testRender()
    {
        $slider = new SliderWidget(0, 10, 1, 5);
        $line = AnsiUtils::stripAnsiCodes($slider->render(new RenderContext(14, 1))[0]);
        $this->assertSame('━━━━━●─────  5', $line, 'A track of 11 cells, the label as wide as that of 10: the thumb at the middle.');
    }

    public function testTheTrackKeepsItsWidthForValuesWiderThanTheBounds()
    {
        foreach ([[0, 1, 0.5], [-1, 1, 0.1]] as [$min, $max, $step]) {
            $slider = new SliderWidget($min, $max, $step);
            $widths = [];
            foreach ([$min, $min + $step, ($min + $max) / 2 + $step, $max] as $value) {
                $line = AnsiUtils::stripAnsiCodes($slider->setValue($value)->render(new RenderContext(20, 1))[0]);
                $widths[] = mb_strlen(rtrim(preg_replace('/[^━●─].*$/u', '', $line)));
            }
            $this->assertCount(1, array_unique($widths), \sprintf('Between %s and %s by %s, like 0.5 or -0.9: %s.', $min, $max, $step, implode(', ', $widths)));
        }
    }

    public function testAStepWrittenWithAnExponentKeepsItsDecimals()
    {
        $slider = new SliderWidget(0, 1, 0.00001, 0.00003);
        $this->assertSame('0.00003', $slider->getValueText());
    }

    public function testANegativeMinimumNarrowerThanTheMaximumTakesNoCellOfTheTrack()
    {
        $slider = new SliderWidget(-1, 100, 1, 100);
        $this->assertSame(' 100', mb_substr(AnsiUtils::stripAnsiCodes($slider->render(new RenderContext(20, 1))[0]), -4));
        $this->assertSame(16, mb_strlen(rtrim(preg_replace('/[^━●─].*$/u', '', AnsiUtils::stripAnsiCodes($slider->render(new RenderContext(20, 1))[0])))), 'The label as wide as " 100".');
    }

    public function testTheTrackKeepsItsWidthAsTheValueChanges()
    {
        $slider = new SliderWidget(0, 100, 1, 5);
        $lines = [];
        foreach ([5, 84, 100] as $value) {
            $lines[] = AnsiUtils::stripAnsiCodes($slider->setValue($value)->render(new RenderContext(22, 1))[0]);
        }
        $this->assertSame(['━●────────────────   5', '━━━━━━━━━━━━━━●───  84', '━━━━━━━━━━━━━━━━━● 100'], $lines, 'The labels right-aligned, as wide as the widest.');
        $this->assertSame(100.0, $slider->valueAt(17, 22), 'The last cell of the track is the maximum, whatever the value.');
    }
}
