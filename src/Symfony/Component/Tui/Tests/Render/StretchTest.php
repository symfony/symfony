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
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\VerticalAlign;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;

final class StretchTest extends TestCase
{
    public function testShorterChildrenTakeTheHeightOfTheRow()
    {
        [$screen, $rects] = $this->render(VerticalAlign::Stretch);

        $this->assertSame([
            '┌─┐tall',
            '│a│two',
            '│ │three',
            '└─┘four',
        ], $screen);
        $this->assertSame([4, 4], $rects);
    }

    public function testTopKeepsTheNaturalHeight()
    {
        [$screen, $rects] = $this->render(VerticalAlign::Top);

        $this->assertSame([
            '┌─┐tall',
            '│a│two',
            '└─┘three',
            '   four',
        ], $screen);
        $this->assertSame([3, 4], $rects);
    }

    /**
     * @return array{list<string>, list<int>}
     */
    private function render(VerticalAlign $align): array
    {
        $terminal = new VirtualTerminal(10, 6);
        $tui = new Tui(terminal: $terminal);
        $row = (new ContainerWidget())->setStyle(new Style(direction: Direction::Horizontal, verticalAlign: $align));
        $row->add($box = (new TextWidget('a'))->setStyle(new Style(border: Border::all(1), flex: 0, maxColumns: 5)));
        $row->add($tall = (new TextWidget("tall\ntwo\nthree\nfour"))->setStyle(new Style(flex: 1)));
        $tui->add($row);
        $tui->start();
        $tui->processRender();

        $screen = new ScreenBuffer(10, 6);
        $screen->write($terminal->getOutput());

        return [
            array_map('rtrim', \array_slice(explode("\n", $screen->getScreen()), 0, 4)),
            [$tui->getWidgetRect($box)->rows, $tui->getWidgetRect($tall)->rows],
        ];
    }
}
