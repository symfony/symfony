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
use Symfony\Bridge\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;

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

    public function testAWrapperCountsTheMarginsAroundTheWidthSetInIt()
    {
        $this->assertSame([9, 1], $this->widths('#a { display: flex; flex: none } #a span { display: block; width: 5ch; margin: 0 2ch; flex-shrink: 0 } #b { flex: none }', '<span></span>'));
        $this->assertSame([9, 1], $this->widths('#a { width: fit-content; flex: none } #a span { display: block; width: 5ch; margin: 0 2ch } #b { flex: none }', '<span></span>'), 'A block in it.');
    }

    public function testTheWidthOfAGrowingItemOnlyStartsIt()
    {
        // Like the w-0 flex-1 of Tailwind, that lets a file name be truncated beside its link: the row's share, not the width
        $this->assertSame(['resume.pdf          Download'], $this->lines('#a { width: 0; flex: 1 1 0% }'));
        $this->assertSame(['resume.pdf          Download'], $this->lines('#a { width: 2ch; flex-grow: 1 }'), 'It grows past its width.');
        $this->assertSame(['resumeDownload', '.pdf'], $this->lines('#a { width: 0; flex: 1 1 0%; max-width: 6ch }'), 'A max-width still bounds it.');
    }

    public function testAnItemStaysWithinItsMinAndMaxWidths()
    {
        // Like CSS, a share past them is frozen there, the other items sharing the rest
        $this->assertSame([5, 35], $this->widths('#a { flex: 1; max-width: 5ch } #b { flex: 1 }'));
        $this->assertSame([10, 30], $this->widths('#a { flex: 1; max-width: 25% } #b { flex: 1 }'), 'A percentage of the row.');
        $this->assertSame([30, 10], $this->widths('#a { flex: 1; min-width: 30ch } #b { flex: 1 }'));
        $this->assertSame([5, 20], $this->widths('#a { flex: 0.5; max-width: 5ch } #b { flex: 0.5 }'), 'Factors summing to less than 1 share that part of the row still.');
        $this->assertSame([6, 1], $this->widths('#a { flex: 1; width: 50%; max-width: 6ch }'), 'A growing percentage.');
        $this->assertSame([20, 20], $this->widths('#a { flex: 1; min-width: 30ch } #b { flex: 1; min-width: 30ch }'), 'Minimums the row cannot hold.');
        // Its width or its basis, not growing
        $this->assertSame([6, 1], $this->widths('#a { width: 30ch; max-width: 6ch }'));
        $this->assertSame([6, 1], $this->widths('#a { flex-basis: 30ch; max-width: 6ch }'));
        $this->assertSame([6, 1], $this->widths('#a { width: 50%; max-width: 6ch }'));
        $this->assertSame([30, 1], $this->widths('#a { width: 50%; min-width: 30ch }'));
    }

    public function testTheMarginsOfAnItemComeAroundItsWidth()
    {
        // Like CSS, the width or the basis is that of the box: its margins on top, not taken from it, its border whole
        foreach (['width: 6ch', 'flex: none; width: 6ch', 'flex: 0 0 6ch', 'flex-basis: 6ch'] as $css) {
            $this->assertSame([10, 1], $this->widths('#a { '.$css.'; margin: 0 2ch }'), $css);
        }
        $this->assertSame([14, 1], $this->widths('#a { width: 25%; margin: 0 2ch }'), 'A percentage.');
        $this->assertSame([10, 30], $this->widths('#a { flex: 1; max-width: 6ch; margin: 0 2ch } #b { flex: 1 }'), 'A max-width bounds the box.');
        $this->assertSame([22, 18], $this->widths('#a { flex: 1 1 6ch; margin: 0 2ch } #b { flex: 1 1 6ch }'), 'A growing item from its basis.');
    }

    public function testAFlexBasisIsTheSizeInTheLineOverTheWidth()
    {
        // Like the grid of Bootstrap: every item of a row 100% wide, its columns flex: 1 0 0%, sharing a line
        $tui = new Tui(null, new VirtualTerminal(40, 5));
        $view = (new HtmlBuilder())->build('<style>.row { display: flex; flex-wrap: wrap } .row > * { flex-shrink: 0; width: 100%; max-width: 100% } .col { flex: 1 0 0% }</style><div class="row"><div class="col" id="a">a</div><div class="col" id="b">b</div></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertSame([0, 0], [$tui->getWidgetRect($view->getById('a'))?->row, $tui->getWidgetRect($view->getById('b'))?->row], 'On one line, not one each.');
        $this->assertSame([20, 20], [$tui->getWidgetRect($view->getById('a'))?->columns, $tui->getWidgetRect($view->getById('b'))?->columns]);
        $tui->stop();
    }

    public function testAWrappingRowBreaksItsLinesAtTheSizesOfItsItems()
    {
        // Like CSS, each item takes in a line its size within its min-width and max-width, its margins around it
        $this->assertSame([0, 0, 0], $this->rows('.row > * { max-width: 50% }'), 'As wide as their content, up to half of the row.');
        $this->assertSame([0, 0, 1], $this->rows('.row > * { width: 6ch; margin: 0 2ch }'));
        $this->assertSame([0, 0, 0], $this->rows('.row > * { flex-basis: 30ch; max-width: 6ch }'));
        $this->assertSame([0, 0, 1], $this->rows('.row > * { width: 30%; min-width: 10ch }'));
    }

    public function testAPercentageMaxWidthBoundsTheContent()
    {
        // Like the bubbles of a chat: as wide as their message, up to that share of the row
        $tui = new Tui(null, new VirtualTerminal(40, 10));
        $view = (new HtmlBuilder())->build('<style>.row { display: flex } .bubble { max-width: 50% }</style><div class="row"><div class="bubble" id="a">short</div></div><div class="row"><div class="bubble" id="b">a much longer message that needs to wrap</div></div><div class="row"><div style="width: 50%" id="c">short</div></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertSame([5, 20], [$tui->getWidgetRect($view->getById('a'))?->columns, $tui->getWidgetRect($view->getById('c'))?->columns], 'A max-width bounds the content; a width is the share.');
        $this->assertLessThanOrEqual(20, $tui->getWidgetRect($view->getById('b'))?->columns, 'A longer message wraps within the bound.');
        $this->assertGreaterThan(10, $tui->getWidgetRect($view->getById('b'))?->columns);
        $tui->stop();
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

    public function testTheMarginsOfAnItemOfASetWidthAreAroundThatWidth()
    {
        $this->assertSame(['  ┌──────┐  Download', '  │resume│', '  │.pdf  │', '  └──────┘'], $this->lines('#a { width: 8ch; margin: 0 2ch; border: 1px solid }'));
        $this->assertSame(['  ┌──────┐  Download', '  │resume│', '  │.pdf  │', '  └──────┘'], $this->lines('#a { flex: 0 1 8ch; margin: 0 2ch; border: 1px solid }'), 'A flex basis too.');
        // Like a badge of a set width in a line of text, boxed: 2 columns of margin on each side of its 4
        $this->assertSame(['   ┌──┐', 'a  │b │  c', '   └──┘'], $this->lines('', '<p>a<span style="display: inline-block; width: 4ch; margin: 0 2ch; border: 1px solid">b</span>c</p>'));
        // Like the chevrons between the links of a breadcrumb: the item measured with them, its link whole
        $this->assertSame(['Web     CSS'], $this->lines('ul { display: flex; list-style: none; margin: 0; padding: 0 } li { display: flex } li:first-child::after { content: ""; width: 3ch; margin: 0 1ch; background: red }', '<ul><li><a>Web</a></li><li><a>CSS</a></li></ul>'));
    }

    public function testABoxAsWideAsItsBordersDrawsBothOfThem()
    {
        // Like CSS: width: 2ch and a border of a column each side, nothing left for the content, not a side cut
        $this->assertSame([' ┌┐', 'x││z', ' └┘'], $this->lines('', '<p>x<span style="display: inline-block; width: 2ch; border: 1px solid">y</span>z</p>'));
        $this->assertSame(['┌┐', '││', '└┘'], $this->lines('', '<div style="width: 2ch; border: 1px solid">y</div>'), 'A block too.');
        $this->assertSame(['──', 'yy', '──'], $this->lines('', '<div style="display: flex; width: 2ch"><div style="border: 1px solid">y</div><div style="border: 1px solid">y</div></div>'), 'Squeezed by its row, its content kept a column.');
    }

    private function lines(string $css, ?string $html = null): array
    {
        $terminal = new VirtualTerminal(28, 5);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->build('<style>.row { display: flex } #b { flex: none } '.$css.'</style>'.($html ?? '<div class="row"><div id="a">resume.pdf</div><div id="b">Download</div></div>'))->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(28, 5);
        $buffer->write($terminal->consumeOutput());
        $tui->stop();

        return array_values(array_filter(array_map(rtrim(...), explode("\n", $buffer->getScreen())), static fn (string $line) => '' !== $line));
    }

    public function testAContentBoxItemIsAsWideAsABlock()
    {
        // Like CSS: content-box adds the padding and the border to the width, and to the flex basis
        $box = '#a { box-sizing: content-box; padding: 0 1ch; border: 1px solid; %s }';
        $this->assertSame(12, $this->widths(\sprintf($box, 'width: 8ch'))[0]);
        $this->assertSame(12, $this->widths(\sprintf($box, 'flex: 0 0 8ch'))[0]);
        $this->assertSame(10, $this->widths(\sprintf(str_replace('content-box', 'border-box', $box), 'width: 10ch'))[0], 'Not border-box.');
    }

    public function testTheChildrenOfAnElementDisplayedAsContentsAreItems()
    {
        // Like CSS, whatever the element: a <span> as a <div>
        foreach (['span', 'div'] as $tag) {
            $this->assertSame(['a  c  d  z'], $this->lines('', '<div style="display: flex; gap: 2"><b>a</b><'.$tag.' style="display: contents"><b>c</b><b>d</b></'.$tag.'><b>z</b></div>'), $tag);
        }
    }

    public function testAPercentageMinWidthIsAShareOfTheRow()
    {
        // Like CSS, and like a block: 40% of the 40 columns of the row, not of the width the item would take
        $this->assertSame(16, $this->widths('#a { width: 2ch; min-width: 40% } #b { flex: none }')[0]);
        $this->assertSame(16, $this->widths('#a { flex: 0 0 2ch; min-width: 40% }')[0]);
    }

    public function testAGrowingItemKeepsItsMinContentFromOtherGrowingItems()
    {
        // Like the min-width: auto of CSS: a word not broken at its share, the other item taking less
        $this->assertSame([22, 18], $this->widths('.row > * { flex: 1 }', str_repeat('a', 22), 'b'));
        $this->assertSame([20, 20], $this->widths('.row > * { flex: 1 } #b { overflow: hidden }', str_repeat('a', 20), str_repeat('b', 20)), 'Both whole when they fit.');
        $this->assertSame([25, 15], $this->widths('.row > * { flex: 1 }', str_repeat('a', 25), 'b'));
        $this->assertSame([20, 20], $this->widths('.row > * { flex: 1 } #a { overflow: hidden }', str_repeat('a', 25), 'b'), 'A clipped item has no automatic minimum.');
        $this->assertSame([20, 20], $this->widths('.row > * { flex: 1 }', str_repeat('a', 25), str_repeat('b', 25)), 'Minimums the row cannot hold.');
    }

    public function testAReverseDirectionStartsAtItsEnd()
    {
        // Like CSS, flex-start and the default are on the right of row-reverse, flex-end on its left; start and end are
        // those of the writing, center the same
        $this->assertSame([18, 16], $this->columns('.row { flex-direction: row-reverse }'));
        $this->assertSame([18, 16], $this->columns('.row { flex-direction: row-reverse; justify-content: flex-start }'));
        $this->assertSame([2, 0], $this->columns('.row { flex-direction: row-reverse; justify-content: flex-end }'));
        $this->assertSame([2, 0], $this->columns('.row { flex-flow: row-reverse wrap; justify-content: start }'));
        $this->assertSame([18, 16], $this->columns('.row { flex-direction: row-reverse; justify-content: end }'));
        $this->assertSame([10, 8], $this->columns('.row { flex-direction: row-reverse; justify-content: center }'));
        $this->assertSame([0, 2], $this->columns('.row { justify-content: flex-start }'));
    }

    public function testWrapReverseStartsItsLinesAtTheBottom()
    {
        // The cross start of wrap-reverse is at the bottom: align-content and align-items of flex-start there, like CSS
        $this->assertSame([5, 5, 4], $this->rows('.row { flex-wrap: wrap-reverse; height: 6em; align-content: flex-start } .row > * { width: 8ch }'));
        $this->assertSame([1, 1, 0], $this->rows('.row { flex-wrap: wrap-reverse; height: 6em; align-content: start } .row > * { width: 8ch }'));
        $this->assertSame([1, 1, 0], $this->rows('.row { flex-wrap: wrap-reverse; height: 6em; align-content: flex-end } .row > * { width: 8ch }'));
        $this->assertSame([2, 2, 2], $this->rows('.row { flex-wrap: wrap-reverse; height: 3em; align-items: flex-start }'));
    }

    public function testAnItemOfMinWidthZeroKeepsAColumnBesideAGrowingItemTooWideForTheRow()
    {
        // Not dropped, the growing item drawn over it
        $long = str_repeat('b', 50);
        $this->assertSame([25, 15], $this->widths('#a { min-width: 0px } #b { flex: 1 }', 'aaaaaaaaaaaaaaaaaaaa aaaa', $long));
        $this->assertSame([25, 15], $this->widths('#a { overflow: hidden } #b { flex: 1 }', 'aaaaaaaaaaaaaaaaaaaa aaaa', $long));
    }

    public function testAMinWidthOfZeroRemovesTheAutomaticMinimum()
    {
        // Like the min-w-0 of Tailwind: the item takes its share, its word broken; auto keeps the word whole
        foreach (['0', '0px'] as $zero) {
            $this->assertSame([20, 20], $this->widths('.row > * { flex: 1 } #a { min-width: '.$zero.' }', str_repeat('a', 22), 'b'), $zero);
        }
        $this->assertSame([22, 18], $this->widths('.row > * { flex: 1 } #a { min-width: auto }', str_repeat('a', 22), 'b'));
    }

    public function testARowSizedByItsContentGivesEachGrowingItemItsContent()
    {
        // Like the flex fractions of CSS: two buttons of flex: 1 as wide as the wider, not the sum of them shared and one cut
        $buttons = '<span>Yes yes</span><span>No</span>';
        $this->assertSame([14, 1], $this->widths('#a { display: flex; flex: none } #a > span { display: block; flex: 1; min-width: 0 } #b { flex: none }', $buttons));
        $this->assertSame([9, 1], $this->widths('#a { display: flex; flex: none } #a > span { display: block } #b { flex: none }', $buttons), 'Items that do not grow: the sum.');
        // The margins are outside the basis: 6 and 0 columns, then 6 more each, the most the first needs, not 12
        $this->assertSame([18, 1], $this->widths('#a { display: flex; flex: none } #a > span { display: block; flex: 1 0 0% } #a > span:first-child { margin: 0 3ch } #b { flex: none }', '<span>aaaaaa</span><span>bbbb</span>'), 'Margins of their own.');
    }

    public function testAGrowingItemOfASetBasisGrowsToItsContentInARowSizedByItsContent()
    {
        // A flex-basis is not a width: the item grows to fit its word, like CSS; the other growing item as much
        $items = '<span>aaaaaa</span><span>bbbb</span>';
        $row = '#a { display: flex; flex: none } #a > span { display: block; margin: 0 1ch; %s } #b { flex: none }';
        $this->assertSame([16, 1], $this->widths(\sprintf($row, 'flex: 1 0 4ch'), $items));
        $this->assertSame([12, 1], $this->widths(\sprintf($row, 'flex: 1 0 4ch'), '<span>a</span><span>b</span>'), 'At least their basis.');
        // One that does not grow, and a width, stay what is set
        $this->assertSame([12, 1], $this->widths(\sprintf($row, 'flex: 0 0 4ch'), $items));
        $this->assertSame([12, 1], $this->widths(\sprintf($row, 'flex-grow: 1; width: 4ch'), $items));
    }

    public function testABoxOfAMaxWidthIsItsContentUpToIt()
    {
        // Like the shrink-to-fit width of CSS: all of the max-width when its content is wider, not the lines wrapped in it
        $text = 'one two three four';
        $this->assertSame([10, 1], $this->widths('#a { max-width: 10ch; border: 1px solid; flex: none } #b { flex: none }', $text), 'A flex item.');
        $this->assertSame([10, 1], $this->widths('#a { display: flex; flex: none } #a > span { display: inline-block; max-width: 10ch; border: 1px solid } #b { flex: none }', '<span>'.$text.'</span>'), 'An inline-block.');
        $this->assertSame([7, 1], $this->widths('#a { max-width: 10ch; flex: none } #b { flex: none }', 'one two'), 'Its content when narrower.');
        // A growing item of a set basis and a width: its width
        $this->assertSame([12, 1], $this->widths('#a { display: flex; flex: none } #a > span { display: block; flex: 1 0 4ch; width: 12ch } #b { flex: none }', '<span>aa</span>'));
    }

    public function testAnEmptyItemTakesNoColumn()
    {
        // Like the empty clearer of Sphinx beside its document: none of the row, like CSS
        $this->assertSame([0, 40], $this->widths('#a { clear: both } #b { flex: 1 }', ''));
        $this->assertSame([2, 38], $this->widths('#a { padding: 0 1ch } #b { flex: 1 }', ''), 'Its padding.');
    }

    public function testAGrowingItemOfAColumnStaysWithinItsMaxHeight()
    {
        // Like CSS, its share past it frozen there, the other items sharing the rest, or justify-content placing it
        $heights = static function (string $css): array {
            $tui = new Tui(null, new VirtualTerminal(20, 10));
            $view = (new HtmlBuilder())->build('<style>.column { display: flex; flex-direction: column; height: 8em } '.$css.'</style><div class="column"><div id="a">a</div><div id="b">b</div><div id="c">c</div></div>')->mount($tui);
            $tui->start();
            $tui->processRender();
            $rects = array_map(static fn (string $id) => [$tui->getWidgetRect($view->getById($id))?->row, $tui->getWidgetRect($view->getById($id))?->rows], ['a', 'b', 'c']);
            $tui->stop();

            return $rects;
        };
        $this->assertSame([[0, 1], [1, 3], [4, 1]], $heights('#b { flex: 1; max-height: 3em; border: 1px solid }'));
        $this->assertSame([[0, 2], [2, 5], [7, 1]], $heights('#a { flex: 1; max-height: 2em } #b { flex: 1 }'));
        $this->assertSame([[0, 1], [3, 3], [7, 1]], $heights('.column { justify-content: space-between } #b { flex: 1; max-height: 3em; border: 1px solid }'));
    }

    public function testAFlexColumnOfASetHeightPlacesItsItemsAsJustifyContentSays()
    {
        $rows = static function (string $css): array {
            $tui = new Tui(null, new VirtualTerminal(20, 10));
            $view = (new HtmlBuilder())->build('<style>.column { display: flex; flex-direction: column; height: 6em } '.$css.'</style><div class="column"><div id="a">a</div><div id="b">b</div></div>')->mount($tui);
            $tui->start();
            $tui->processRender();
            $rows = [$tui->getWidgetRect($view->getById('a'))?->row, $tui->getWidgetRect($view->getById('b'))?->row];
            $tui->stop();

            return $rows;
        };
        $this->assertSame([4, 5], $rows('.column { justify-content: flex-end }'));
        $this->assertSame([2, 3], $rows('.column { justify-content: center }'));
        $this->assertSame([1, 4], $rows('.column { justify-content: space-around }'));
        $this->assertSame([5, 4], $rows('.column { flex-direction: column-reverse }'), 'From the bottom.');
        $this->assertSame([1, 0], $rows('.column { flex-direction: column-reverse; justify-content: flex-end }'));
        $this->assertSame([0, 1], $rows('.column { height: auto; justify-content: center }'), 'No free rows.');
    }

    public function testItemsShrinkByTheirSizesInsideTheirMargins()
    {
        // Like CSS, the overflow shared as their flex-basis times their flex-shrink, their margins kept
        $this->assertSame([25, 15], $this->widths('#a { flex: 0 1 30ch; margin: 0 5ch } #b { flex: 0 1 30ch }'));
        $this->assertSame([32, 8], $this->widths('#a { flex: 0 1 30ch; margin: 0 5ch } #b { flex: 0 3 30ch }'));
        $this->assertSame([22, 18], $this->widths('#a { flex: 1 1 30ch; margin: 0 2ch } #b { flex: 1 1 30ch }'));
    }

    public function testAlignSelfOfFlexStartOrFlexEndSwapsInAWrapReverseRow()
    {
        // Like CSS, the cross start of wrap-reverse is at the bottom; start and end stay those of the writing
        $this->assertSame([2, 0, 0], $this->rows('.row { flex-wrap: wrap-reverse; height: 3em } #a { align-self: flex-start } #b { height: 3em }'));
        $this->assertSame([0, 0, 0], $this->rows('.row { flex-wrap: wrap-reverse; height: 3em } #a { align-self: flex-end } #b { height: 3em }'));
        $this->assertSame([0, 0, 0], $this->rows('.row { flex-wrap: wrap-reverse; height: 3em } #a { align-self: start } #b { height: 3em }'));
        $this->assertSame([2, 0, 0], $this->rows('.row { flex-wrap: wrap; height: 3em } #a { align-self: flex-end } #b { height: 3em }'));
    }

    private function columns(string $css): array
    {
        $tui = new Tui(null, new VirtualTerminal(20, 5));
        $view = (new HtmlBuilder())->build('<style>.row { display: flex } '.$css.'</style><div class="row"><div id="a">aa</div><div id="b">bb</div></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $columns = [$tui->getWidgetRect($view->getById('a'))?->col, $tui->getWidgetRect($view->getById('b'))?->col];
        $tui->stop();

        return $columns;
    }

    private function rows(string $css): array
    {
        $tui = new Tui(null, new VirtualTerminal(20, 5));
        $view = (new HtmlBuilder())->build('<style>.row { display: flex; flex-wrap: wrap } '.$css.'</style><div class="row"><div id="a">aa</div><div id="b">bb</div><div id="c">cc</div></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $rows = array_map(static fn (string $id) => $tui->getWidgetRect($view->getById($id))?->row, ['a', 'b', 'c']);
        $tui->stop();

        return $rows;
    }

    private function widths(string $css, string $a = 'a', string $b = 'b'): array
    {
        $tui = new Tui(null, new VirtualTerminal(40, 5));
        $view = (new HtmlBuilder())->build('<style>.row { display: flex } '.$css.'</style><div class="row"><div id="a">'.$a.'</div><div id="b">'.$b.'</div></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $widths = [$tui->getWidgetRect($view->getById('a'))?->columns, $tui->getWidgetRect($view->getById('b'))?->columns];
        $tui->stop();

        return $widths;
    }
}
