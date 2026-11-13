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
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Internal\StyleBridge;
use Symfony\Component\TuiHtml\Style\Border;
use Symfony\Component\TuiHtml\Style\Direction;
use Symfony\Component\TuiHtml\Style\Padding;
use Symfony\Component\TuiHtml\Style\Style;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Widget\ContainerWidget;
use Symfony\Component\TuiHtml\Widget\TextWidget;

final class MarginCollapseTest extends TestCase
{
    public function testSiblingMarginsCollapseToTheLargest()
    {
        [$a, $b] = $this->rows(null, [new Padding(0, 0, 1, 0), new Padding(3, 0, 0, 0)]);
        // The rects are margin boxes: a's bottom margin is gone, b's 3 top rows separate them
        $this->assertSame(0, $a);
        $this->assertSame(1, $b, 'b starts right after the text of a: its text is on row 4, 3 rows below.');
    }

    public function testAFlexColumnKeepsBothMargins()
    {
        [, $b] = $this->rows(Direction::Vertical, [new Padding(0, 0, 1, 0), new Padding(3, 0, 0, 0)]);
        $this->assertSame(2, $b, 'b starts after the margin of a: its text is on row 5, 4 rows below.');
    }

    public function testAParentAndItsFirstChildShareTheirTopMargin()
    {
        $tui = new Tui(null, new VirtualTerminal(20, 12));
        $parent = StyleBridge::set(new ContainerWidget(), new Style(null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, new Padding(2, 0, 0, 0)));
        $parent->add($child = StyleBridge::set(new TextWidget('x'), new Style(null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, new Padding(1, 0, 0, 0))));
        $tui->add($parent);
        $tui->start();
        $tui->processRender();
        // The child's text is 2 rows down, not 3: its rect, a margin box, starts after the shared row
        $this->assertSame(1, $tui->getWidgetRect($child)->row);
    }

    public function testABorderKeepsThemApart()
    {
        $tui = new Tui(null, new VirtualTerminal(20, 12));
        $parent = StyleBridge::set(new ContainerWidget(), new Style(null, Border::all(1), null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, new Padding(2, 0, 0, 0)));
        $parent->add($child = StyleBridge::set(new TextWidget('x'), new Style(null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, new Padding(1, 0, 0, 0))));
        $tui->add($parent);
        $tui->start();
        $tui->processRender();
        $this->assertSame(3, $tui->getWidgetRect($child)->row);
    }

    private function rows(?Direction $direction, array $margins): array
    {
        $tui = new Tui(null, new VirtualTerminal(20, 12));
        $column = StyleBridge::set(new ContainerWidget(), new Style(null, null, null, null, null, null, null, null, null, null, $direction));
        $cells = [];
        foreach ($margins as $i => $margin) {
            $column->add($cells[] = StyleBridge::set(new TextWidget('x'.$i), new Style(null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, $margin)));
        }
        $tui->add($column);
        $tui->start();
        $tui->processRender();

        return array_map(static fn (TextWidget $cell) => $tui->getWidgetRect($cell)->row, $cells);
    }
}
