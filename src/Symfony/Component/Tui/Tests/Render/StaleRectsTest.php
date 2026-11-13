<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * A layout renders some children once without tracking positions to
 * measure them: their cached render must not reuse the rects of an earlier frame.
 */
final class StaleRectsTest extends TestCase
{
    public function testRectsFollowAChangeInANestedRow()
    {
        $tui = new Tui(terminal: new VirtualTerminal(40, 10));
        $outer = new ContainerWidget();
        $row = (new ContainerWidget())->setStyle(new Style(direction: Direction::Horizontal, gap: 1));
        $row->add($a = (new TextWidget('a'))->setStyle(new Style(flex: 0)));
        $row->add($b = (new TextWidget('b'))->setStyle(new Style(flex: 0)));
        $outer->add($row);
        $tui->add($outer);
        $tui->start();
        $tui->processRender();
        $this->assertSame(2, $tui->getWidgetRect($b)->col);

        $a->setText('a longer text');
        $tui->requestRender();
        $tui->processRender();

        $this->assertSame(14, $tui->getWidgetRect($b)->col);
        $this->assertSame(13, $tui->getWidgetRect($a)->columns);
    }

    public function testAnUnchangedSubtreeKeepsItsRects()
    {
        $tui = new Tui(terminal: new VirtualTerminal(40, 10));
        $tui->add($top = new TextWidget('top'));
        $tui->add($box = new ContainerWidget());
        $box->add($inner = new TextWidget('inner'));
        $tui->start();
        $tui->processRender();
        $this->assertSame(1, $tui->getWidgetRect($inner)->row);

        $top->setText("top\nnow two rows");
        $tui->requestRender();
        $tui->processRender();

        $this->assertSame(2, $tui->getWidgetRect($inner)->row, 'Moved with its cached parent.');
    }
}
