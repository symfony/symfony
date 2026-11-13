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
use Symfony\Component\Tui\Style\AutoMargin;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Justify;
use Symfony\Component\Tui\Style\Padding;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\TextWidget;

final class JustifyTest extends TestCase
{
    public function testSpaceBetween()
    {
        $this->assertSame(['a      b      c', [0, 7, 14]], $this->layout(15, Justify::SpaceBetween, ['a', 'b', 'c']));
    }

    public function testSpaceBetweenGivesTheRoundingToTheFirstGaps()
    {
        $this->assertSame(['a    b   c', [0, 5, 9]], $this->layout(10, Justify::SpaceBetween, ['a', 'b', 'c']));
    }

    public function testSpaceAround()
    {
        // 12 free columns: half a share (2) at the edges, a share (4) between
        $this->assertSame(['  a    b    c', [2, 7, 12]], $this->layout(15, Justify::SpaceAround, ['a', 'b', 'c']));
    }

    public function testSpaceEvenly()
    {
        $this->assertSame(['   a   b   c', [3, 7, 11]], $this->layout(15, Justify::SpaceEvenly, ['a', 'b', 'c']));
    }

    public function testTheGapIsAMinimum()
    {
        $this->assertSame(['aaaa  bbbb', [0, 6]], $this->layout(10, Justify::SpaceBetween, ['aaaa', 'bbbb'], gap: 2));
        $this->assertSame(['aaaa bbbb', [0, 5]], $this->layout(9, Justify::SpaceEvenly, ['aaaa', 'bbbb'], gap: 1));
    }

    public function testSingleChildOfSpaceBetweenStaysAtTheStart()
    {
        $this->assertSame(['a', [0]], $this->layout(9, Justify::SpaceBetween, ['a']));
        $this->assertSame(['    a', [4]], $this->layout(9, Justify::SpaceEvenly, ['a']));
    }

    public function testFlexibleChildrenLeaveNothingToShare()
    {
        $this->assertSame([0, 5], $this->layout(10, Justify::SpaceBetween, ['a', 'b'], flex: 1)[1]);
    }

    public function testAutoMarginsTakeTheFreeColumnsFirst()
    {
        $this->assertSame(['a b      c', [0, 2, 9]], $this->layout(10, Justify::SpaceEvenly, ['a', 'b', 'c'], gap: 1, autoMargins: [2 => AutoMargin::Left]));
        $this->assertSame(['   a', [3]], $this->layout(7, null, ['a'], autoMargins: [AutoMargin::Both]));
        $this->assertSame(['a     b', [0, 6]], $this->layout(7, null, ['a', 'b'], autoMargins: [AutoMargin::Right, AutoMargin::Left]));
    }

    public function testAutoMarginsCenterANarrowerBlock()
    {
        $terminal = new VirtualTerminal(20, 5);
        $tui = new Tui(terminal: $terminal);
        $tui->add($block = (new TextWidget('box'))->setStyle((new Style(maxColumns: 6, margin: new Padding(0, 0, 0, 1)))->withAutoMargin(AutoMargin::Both)));
        $tui->start();
        $tui->processRender();

        $screen = new ScreenBuffer(20, 5);
        $screen->write($terminal->getOutput());
        // 6 columns of box and 1 of margin leave 13: 6 on the left, 7 on the right
        $this->assertSame('       box', rtrim(explode("\n", $screen->getScreen())[0]));
        $this->assertSame(20, $tui->getWidgetRect($block)->columns);
    }

    public function testStyleCarriesTheDistribution()
    {
        $style = Style::mergeAll([(new Style())->withJustify(Justify::SpaceAround), new Style(gap: 1)]);

        $this->assertSame(Justify::SpaceAround, $style->getJustify());
        $this->assertNull($style->withoutLayoutProperties()->getJustify());
        $this->assertNull($style->withJustify(null)->getJustify());
    }

    /**
     * @param list<string> $texts
     *
     * @return array{string, list<int>}
     */
    /**
     * @param array<int, AutoMargin> $autoMargins
     */
    private function layout(int $columns, ?Justify $justify, array $texts, int $gap = 0, int $flex = 0, array $autoMargins = []): array
    {
        $terminal = new VirtualTerminal($columns, 5);
        $tui = new Tui(terminal: $terminal);
        $row = (new ContainerWidget())->setStyle((new Style(direction: Direction::Horizontal, gap: $gap))->withJustify($justify));
        $cells = [];
        foreach ($texts as $i => $text) {
            $row->add($cells[] = (new TextWidget($text))->setStyle((new Style(flex: $flex))->withAutoMargin($autoMargins[$i] ?? null)));
        }
        $tui->add($row);
        $tui->start();
        $tui->processRender();

        $screen = new ScreenBuffer($columns, 5);
        $screen->write($terminal->getOutput());

        return [
            rtrim(explode("\n", $screen->getScreen())[0]),
            array_map(static fn (TextWidget $cell) => $tui->getWidgetRect($cell)->col, $cells),
        ];
    }
}
