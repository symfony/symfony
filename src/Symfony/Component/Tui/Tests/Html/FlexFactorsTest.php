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
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

final class FlexFactorsTest extends TestCase
{
    public function testFactorsShareTheFreeColumns()
    {
        $this->assertSame([13, 27], $this->widths('#a { flex: 1 } #b { flex: 2 }'));
        $this->assertSame([10, 30], $this->widths('#a { flex: 0.5 } #b { flex: 1.5 }'), 'Fractions weigh like whole numbers.');
    }

    public function testFactorsSummingToLessThanOneShareThatPart()
    {
        // b keeps its content, a column; a takes half of the 39 left
        $this->assertSame([19, 1], $this->widths('#a { flex: 0.5 } #b { flex: none }'));
        $this->assertSame([10, 20], $this->widths('#a { flex: 0.25 } #b { flex: 0.5 }'), 'A quarter and a half of the 40 columns, the last quarter left free.');
    }

    public function testAGrowingItemOfABasisOfAutoStartsFromItsContent()
    {
        // Like the buttons of a group of Bootstrap, flex: 1 1 auto: the 30 free columns shared over their 4 and 6
        foreach (['flex-grow: 1', 'flex: 1 1 auto', 'flex: auto', 'flex: 1; flex-basis: auto'] as $css) {
            $this->assertSame([19, 21], $this->widths('.row > * { '.$css.' }', 'Left', 'Middle'), $css);
        }
        $this->assertSame([20, 20], $this->widths('.row > * { flex: 1 }', 'Left', 'Middle'), 'flex: 1 is a basis of 0.');
    }

    public function testAWrapperIsAsWideAsTheWidthSetInIt()
    {
        // Like the empty dark mode switch of VitePress, width: 40px in a flex wrapper: the wrapper as wide, not as its content
        $this->assertSame([5, 1], $this->widths('#a { display: flex; flex: none } #a span { display: block; width: 5ch; flex-shrink: 0 } #b { flex: none }', '<span></span>'));
    }

    public function testTheWidthOfAGrowingItemOnlyStartsIt()
    {
        // Like the w-0 flex-1 of Tailwind, that lets a file name be truncated beside its link: the row's share, not the width
        $this->assertSame(['resume.pdf          Download'], $this->lines('#a { width: 0; flex: 1 1 0% }'));
        $this->assertSame(['resume.pdf          Download'], $this->lines('#a { width: 2ch; flex-grow: 1 }'), 'It grows past its width.');
        $this->assertSame(['resume              Download', '.pdf'], $this->lines('#a { width: 0; flex: 1 1 0%; max-width: 6ch }'), 'A max-width still bounds it.');
    }

    /**
     * @return list<int|null>
     */
    public function testAFlexBasisIsTheSizeInTheLineOverTheWidth()
    {
        // Like the grid of Bootstrap: every item of a row 100% wide, its columns flex: 1 0 0%, sharing a line
        $tui = new Tui(terminal: new VirtualTerminal(40, 5));
        $view = (new HtmlBuilder())->build('<style>.row { display: flex; flex-wrap: wrap } .row > * { flex-shrink: 0; width: 100%; max-width: 100% } .col { flex: 1 0 0% }</style><div class="row"><div class="col" id="a">a</div><div class="col" id="b">b</div></div>')->mount($tui);
        $tui->start();
        $tui->processRender();

        $this->assertSame([0, 0], [$tui->getWidgetRect($view->getById('a'))?->row, $tui->getWidgetRect($view->getById('b'))?->row], 'On one line, not one each.');
        $this->assertSame([20, 20], [$tui->getWidgetRect($view->getById('a'))?->columns, $tui->getWidgetRect($view->getById('b'))?->columns]);
        $tui->stop();
    }

    public function testAPercentageMaxWidthBoundsTheContent()
    {
        // Like the bubbles of a chat: as wide as their message, up to that share of the row
        $tui = new Tui(terminal: new VirtualTerminal(40, 10));
        $view = (new HtmlBuilder())->build('<style>.row { display: flex } .bubble { max-width: 50% }</style><div class="row"><div class="bubble" id="a">short</div></div><div class="row"><div class="bubble" id="b">a much longer message that needs to wrap</div></div><div class="row"><div style="width: 50%" id="c">short</div></div>')->mount($tui);
        $tui->start();
        $tui->processRender();

        $this->assertSame([5, 20], [$tui->getWidgetRect($view->getById('a'))?->columns, $tui->getWidgetRect($view->getById('c'))?->columns], 'A max-width bounds the content; a width is the share.');
        $this->assertLessThanOrEqual(20, $tui->getWidgetRect($view->getById('b'))?->columns, 'A longer message wraps within the bound.');
        $this->assertGreaterThan(10, $tui->getWidgetRect($view->getById('b'))?->columns);
        $tui->stop();
    }

    /**
     * @return list<string>
     */
    private function lines(string $css): array
    {
        $terminal = new VirtualTerminal(28, 5);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->build('<style>.row { display: flex } #b { flex: none } '.$css.'</style><div class="row"><div id="a">resume.pdf</div><div id="b">Download</div></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(28, 5);
        $buffer->write($terminal->consumeOutput());
        $tui->stop();

        return array_values(array_filter(array_map(rtrim(...), explode("\n", $buffer->getScreen())), static fn (string $line) => '' !== $line));
    }

    public function testAGrowingItemKeepsItsMinContentFromItsSiblingsOfASetWidth()
    {
        // Like the menu of htmx beside its logo of a set width: the links whole, the logo narrower, where a browser
        // overflows the row
        $menu = '<span>reference</span><span>examples</span><span>essays</span>';
        $this->assertSame([15, 25], $this->widths('#a { width: 20ch } #b { flex: 1; display: flex; gap: 1ch }', 'a', $menu));
        $this->assertSame([20, 20], $this->widths('#a { width: 20ch } #b { flex: 1; display: flex; gap: 1ch; overflow: hidden }', 'a', $menu), 'A clipped item has no automatic minimum.');
        $this->assertSame([18, 22], $this->widths('#a { width: 20ch } #b { flex: 1; max-width: 22ch; display: flex; gap: 1ch }', 'a', $menu), 'Never wider than its max-width.');
        $this->assertSame([20, 20], $this->widths('#a { width: 20ch; flex-shrink: 0 } #b { flex: 1; display: flex; gap: 1ch }', 'a', $menu), 'An item that does not shrink keeps its width.');
    }

    private function widths(string $css, string $a = 'a', string $b = 'b'): array
    {
        $tui = new Tui(terminal: new VirtualTerminal(40, 5));
        $view = (new HtmlBuilder())->build('<style>.row { display: flex } '.$css.'</style><div class="row"><div id="a">'.$a.'</div><div id="b">'.$b.'</div></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $widths = [$tui->getWidgetRect($view->getById('a'))?->columns, $tui->getWidgetRect($view->getById('b'))?->columns];
        $tui->stop();

        return $widths;
    }
}
