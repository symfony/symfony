<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Html\HtmlView;
use Symfony\Component\Tui\Render\WidgetRect;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

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

    /**
     * @return list<string>
     */
    private function screen(int $rows): array
    {
        $terminal = (fn () => $this->terminal)->call($this->tui);
        $buffer = new ScreenBuffer($terminal->getColumns(), 20);
        $buffer->write($terminal->getOutput());

        return array_map(rtrim(...), \array_slice(explode("\n", $buffer->getScreen()), 0, $rows));
    }

    private function render(string $html, int $columns): HtmlView
    {
        $this->tui?->stop();
        $this->tui = new Tui(terminal: new VirtualTerminal($columns, 20));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build($html)->mount($this->tui);
        $this->tui->start();
        $this->tui->processRender();

        return $view;
    }

    private function rect(HtmlView $view, string $id): ?WidgetRect
    {
        return $this->tui?->getWidgetRect($view->getById($id));
    }

    /**
     * @return array{int|null, int|null} Where the widget starts, and how wide it is
     */
    private function columns(HtmlView $view, string $id): array
    {
        $rect = $this->rect($view, $id);

        return [$rect?->col, $rect?->columns];
    }
}
