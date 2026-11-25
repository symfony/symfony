<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Html\HtmlView;
use Symfony\Bridge\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\Tui\Render\WidgetRect;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;

final class PercentBoxTest extends TestCase
{
    private ?Tui $tui = null;

    protected function tearDown(): void
    {
        $this->tui?->stop();
    }

    public function testPercentagesOfTheContainingBlock()
    {
        $html = '<style>#box { border: 1px solid; padding: 0 10%; margin-left: 25% }</style><div id="box"><p id="text">text</p></div>';
        // The rect of the box counts its margins: the text is past the margin, the border and the padding
        $view = $this->render($html, 40);
        $this->assertSame([15, 20], $this->columns($view, 'text'), 'A margin of 10 columns, a quarter of 40, and a padding of 4 on each side.');
        $this->assertSame('          │    text                    │', $this->screen(2)[1]);
        $view = $this->render($html, 60);
        $this->assertSame([22, 31], $this->columns($view, 'text'), 'The same shares of a wider containing block: 15 and 6.');
    }

    public function testAnItemOfARowTakesPercentagesOfTheRow()
    {
        $this->render('<style>.row { display: flex } #a { flex: 1; padding-left: 10%; border: 1px solid } #b { flex: 1 }</style><div class="row"><div id="a">a</div><div id="b">b</div></div>', 40);
        $this->assertSame('│    a             │', rtrim(mb_substr($this->screen(2)[1], 0, 20)), '10% of the 40 columns of the row, not of the 20 of the item.');
    }

    public function testPercentageMarginsCollapse()
    {
        $this->render('<style>p { margin: 5% 0 }</style><main><p>a</p><p>b</p></main>', 40);
        // 5% of 40 is 2 rows above and below each paragraph, the two between them collapsing into 2
        $this->assertSame(['', '', 'a', '', '', 'b'], $this->screen(6));
    }

    public function testAnOutlineIsAroundTheBoxInsidePercentageMargins()
    {
        $this->render('<style>#box { width: 50%; margin: 10% 0 10% 1ch; outline: 1px solid }</style><p>top</p><div id="box">box</div><p>end</p>', 40);
        // 10% of the 40 columns of the containing block is 4 rows above and below, not 10% of the 20 of the box
        $this->assertSame(['top', '', '', '', '┌────────────────────┐', '│box                 │', '└────────────────────┘', '', '', '', 'end'], $this->screen(11));
    }

    public function testAnAspectRatioIsWithinTheMinAndMaxHeights()
    {
        $rows = function (string $css): int {
            $this->render('<div style="width: 20ch; aspect-ratio: 1; '.$css.'">x</div><p>after</p>', 40);

            return array_search('after', $this->screen(20), true);
        };
        $this->assertSame(10, $rows(''), 'As tall as wide: 20 columns are 10 rows.');
        $this->assertSame(3, $rows('max-height: 3'), 'No taller than its max-height.');
        $this->assertSame(5, $rows('max-height: 3; min-height: 5'), 'Its min-height winning over its max-height.');
        $this->assertSame(12, $rows('min-height: 12'), 'No shorter than its min-height.');
    }

    public function testAnAspectRatioIsThatOfTheBoxBoxSizingSizes()
    {
        $rows = function (string $css): int {
            $this->render('<div style="width: 20ch; aspect-ratio: 2; border: 1px solid; padding: 1 0; '.$css.'">x</div><p>after</p>', 40);

            return array_search('after', $this->screen(20), true);
        };
        $this->assertSame(5, $rows(''), 'border-box: the 20 columns of its border box over 2 are 5 rows, its border and padding in them.');
        $this->assertSame(9, $rows('box-sizing: content-box'), 'content-box: 5 rows of content, its border and padding around them.');
    }

    public function testAChIsHalfARowAlongTheHeight()
    {
        // A ch is 8 pixels, a row 16: 4ch above and below are 2 rows, 2ch of padding 1, a height of 10ch 5, its border
        // and padding in them; 4ch on the sides are 4 columns
        $this->render('<p>top</p><div style="margin: 4ch; padding: 2ch 0; height: 10ch; border: 1px solid">box</div><p>end</p>', 20);
        $this->assertSame(['top', '', '', '    ┌──────────┐', '    │          │', '    │box       │', '    │          │', '    └──────────┘', '', '', 'end'], $this->screen(11));
    }

    private function screen(int $rows): array
    {
        $terminal = $this->tui->getTerminal();
        $buffer = new ScreenBuffer($terminal->getColumns(), 20);
        $buffer->write($terminal->getOutput());

        return array_map(rtrim(...), \array_slice(explode("\n", $buffer->getScreen()), 0, $rows));
    }

    private function render(string $html, int $columns): HtmlView
    {
        $this->tui?->stop();
        $this->tui = new Tui(null, new VirtualTerminal($columns, 20));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build($html)->mount($this->tui);
        $this->tui->start();
        $this->tui->processRender();

        return $view;
    }

    private function rect(HtmlView $view, string $id): ?WidgetRect
    {
        return $this->tui?->getWidgetRect($view->getById($id));
    }

    private function columns(HtmlView $view, string $id): array
    {
        $rect = $this->rect($view, $id);

        return [$rect?->col, $rect?->columns];
    }
}
