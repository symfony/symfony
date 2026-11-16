<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Internal\StyleBridge;
use Symfony\Component\TuiHtml\Style\Border;
use Symfony\Component\TuiHtml\Style\Direction;
use Symfony\Component\TuiHtml\Style\Style;
use Symfony\Component\TuiHtml\Style\VerticalAlign;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Widget\ContainerWidget;
use Symfony\Component\TuiHtml\Widget\TextWidget;

final class StretchTest extends TestCase
{
    public function testShorterChildrenTakeTheHeightOfTheRow()
    {
        [$screen, $rects] = $this->render(VerticalAlign::Stretch);
        $this->assertSame(['┌─┐tall', '│a│two', '│ │three', '└─┘four'], $screen);
        $this->assertSame([4, 4], $rects);
    }

    public function testTopKeepsTheNaturalHeight()
    {
        [$screen, $rects] = $this->render(VerticalAlign::Top);
        $this->assertSame(['┌─┐tall', '│a│two', '└─┘three', '   four'], $screen);
        $this->assertSame([3, 4], $rects);
    }

    private function render(VerticalAlign $align): array
    {
        $terminal = new VirtualTerminal(10, 6);
        $tui = new Tui(null, $terminal);
        $row = StyleBridge::set(new ContainerWidget(), new Style(null, null, null, null, null, null, null, null, null, null, Direction::Horizontal, null, null, null, null, null, null, null, $align));
        $row->add($box = StyleBridge::set(new TextWidget('a'), new Style(null, Border::all(1), null, null, null, null, null, null, null, null, null, null, null, null, null, null, 5, null, null, 0)));
        $row->add($tall = StyleBridge::set(new TextWidget("tall\ntwo\nthree\nfour"), new Style(null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 1)));
        $tui->add($row);
        $tui->start();
        $tui->processRender();
        $screen = new ScreenBuffer(10, 6);
        $screen->write($terminal->getOutput());

        return [array_map('rtrim', \array_slice(explode("\n", $screen->getScreen()), 0, 4)), [$tui->getWidgetRect($box)->rows, $tui->getWidgetRect($tall)->rows]];
    }
}
