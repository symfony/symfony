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
        $this->assertSame('━━━━━━●───── 5', $line, 'A track of 12 cells: the thumb at the middle, rounded.');
    }
}
