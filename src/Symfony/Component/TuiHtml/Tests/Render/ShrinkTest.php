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
use Symfony\Component\TuiHtml\Style\Direction;
use Symfony\Component\TuiHtml\Style\Style;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Widget\ContainerWidget;
use Symfony\Component\TuiHtml\Widget\TextWidget;

final class ShrinkTest extends TestCase
{
    public function testZeroKeepsTheIntrinsicWidth()
    {
        [$a, $b] = $this->widths(20, [0, 1], ['keep this width', 'this one gives way']);
        $this->assertSame([15, 5], [$a, $b]);
    }

    public function testFactorsWeighTheLoss()
    {
        // 10 + 10 columns in 16: the deficit of 4 splits 3 to 1
        $this->assertSame([7, 9], $this->widths(16, [3, 1], ['aaaaaaaaaa', 'bbbbbbbbbb']));
    }

    public function testTheRoundingGoesToTheHeaviestShare()
    {
        // A deficit of 3 weighed 2:1 is 2 and 1, whatever the order
        $this->assertSame([9, 8], $this->widths(17, [1, 2], ['aaaaaaaaaa', 'bbbbbbbbbb']));
        $this->assertSame([8, 9], $this->widths(17, [2, 1], ['aaaaaaaaaa', 'bbbbbbbbbb']));
    }

    public function testFractionalFactors()
    {
        // 20 + 20 columns in 30: the deficit of 10 splits 2 to 1 for factors 1 and 0.5
        $this->assertSame([13, 17], $this->widths(30, [1, 0.5], ['aaaaaaaaaaaaaaaaaaaa', 'bbbbbbbbbbbbbbbbbbbb']));
        $this->assertSame([13, 17], $this->widths(30, [0.4, 0.2], ['aaaaaaaaaaaaaaaaaaaa', 'bbbbbbbbbbbbbbbbbbbb']), 'Only the ratio counts.');
    }

    public function testEveryoneGivesWayWhenTheFixedOnesDoNotFit()
    {
        $this->assertSame(10, array_sum($this->widths(10, [0, 0], ['aaaaaaaaaa', 'bbbbbbbbbb'])));
    }

    public function testMinimumWidthIsKeptWhileOthersCanGiveWay()
    {
        // Without a minimum, 10 + 10 in 12 is 6 + 6
        $this->assertSame([6, 6], $this->widths(12, [1, 1], ['aaaaaaaaaa', 'bbbbbbbbbb']));
        $this->assertSame([9, 3], $this->widths(12, [1, 1], ['aaaaaaaaaa', 'bbbbbbbbbb'], [9]));
    }

    public function testAnItemDoesNotShrinkBelowItsLongestWord()
    {
        // Like the min-width: auto of CSS: 'Volume' keeps its 6 columns, the long text gives way
        $this->assertSame([6, 15], $this->widths(21, [1, 1], ['Volume', 'a long text that gives way']));
        $this->assertSame([3, 4], $this->widths(7, [1, 1], ['Volume', 'abcdef']), 'When the words do not fit, they are broken.');
    }

    public function testMinimumWidthWidensANarrowItem()
    {
        $this->assertSame([5, 1], $this->widths(20, [1, 1], ['a', 'b'], [5]));
    }

    public function testMinimumsThatDoNotFitAreIgnored()
    {
        $this->assertSame(10, array_sum($this->widths(10, [1, 1], ['aaaaaaaaaa', 'bbbbbbbbbb'], [8, 8])));
    }

    private function widths(int $columns, array $shrinks, array $texts, array $minimums = []): array
    {
        $tui = new Tui(null, new VirtualTerminal($columns, 10));
        $row = StyleBridge::set(new ContainerWidget(), new Style(null, null, null, null, null, null, null, null, null, null, Direction::Horizontal));
        $cells = [];
        foreach ($texts as $i => $text) {
            $row->add($cells[] = StyleBridge::set(new TextWidget($text), (new Style(null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 0))->withShrink($shrinks[$i])->withMinColumns($minimums[$i] ?? null)));
        }
        $tui->add($row);
        $tui->start();
        $tui->processRender();

        return array_map(static fn (TextWidget $cell) => $tui->getWidgetRect($cell)->columns, $cells);
    }
}
