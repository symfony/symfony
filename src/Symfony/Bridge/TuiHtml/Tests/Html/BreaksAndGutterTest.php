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

final class BreaksAndGutterTest extends TestCase
{
    public function testAForcedColumnBreak()
    {
        $screen = $this->render('<style>.cols { columns: 3; column-gap: 1 } p { margin: 0 } .new { break-before: column } .last { break-after: column }</style>
            <div class="cols"><p>a</p><p>b</p><p class="new">c</p><p class="last">d</p><p>e</p><p>f</p></div>', 32);
        $this->assertSame(['a          c          e', 'b          d          f'], array_map('rtrim', \array_slice($screen, 0, 2)), 'c starts the second column, e the third, after d.');
    }

    public function testBreaksDoNotGoPastTheLastColumn()
    {
        $screen = $this->render('<style>.cols { columns: 2; column-gap: 1 } p { margin: 0; break-before: column }</style><div class="cols"><p>a</p><p>b</p><p>c</p></div>', 32);
        $this->assertSame(['a               b', '                c'], array_map('rtrim', \array_slice($screen, 0, 2)), 'What is left goes into the last column.');
    }

    public function testOrphansAndWidowsKeepLinesTogether()
    {
        $html = '<style>.cols { columns: 2; column-gap: 1 } p { margin: 0; orphans: %d; widows: %d }</style><div class="cols"><p>1<br>2<br>3<br>4</p></div>';
        $columns = fn (int $orphans, int $widows) => array_map('rtrim', \array_slice($this->render(\sprintf($html, $orphans, $widows), 24, 6), 0, 3));
        $this->assertSame(['1           3', '2           4'], $columns(1, 1), 'Balanced: two lines in each column.');
        $this->assertSame(['1           4', '2', '3'], $columns(3, 1), 'Three lines before the break.');
        $this->assertSame(['1           2', '            3', '            4'], $columns(1, 3), 'Three lines after it.');
    }

    public function testColumnFillAutoFillsTheColumnsOneAfterTheOther()
    {
        $html = '<style>.cols { columns: 3; column-gap: 1; %s } p { margin: 0 }</style><div class="cols"><p>a</p><p>b</p><p>c</p><p>d</p></div><p>end</p>';
        $screen = fn (string $css) => array_map('rtrim', \array_slice($this->render(\sprintf($html, $css), 23), 0, 4));
        $this->assertSame(['a       c', 'b       d', '', 'end'], $screen('height: 3'), 'Balanced by default, in a box of its height.');
        $this->assertSame(['a       d', 'b', 'c', 'end'], $screen('height: 3; column-fill: auto'), 'Filled up to the height.');
        $this->assertSame(['a       c', 'b       d', 'end'], $screen('column-fill: auto'), 'Balanced without a height, like browsers.');
        $this->assertSame(['a       c', 'end'], $screen('height: 1; column-fill: auto'), 'Content that does not fit makes the columns taller, cut at the height like any content.');
    }

    public function testARuleIsOnlyDrawnBetweenColumnsThatHaveContent()
    {
        $html = '<style>.cols { columns: 3; column-gap: 1; column-rule: 1px solid; %s } p { margin: 0 }</style><div class="cols"><p>a</p><p>b</p><p>c</p><p>d</p><p>e</p></div>';
        $screen = fn (string $css) => array_map('rtrim', \array_slice($this->render(\sprintf($html, $css), 23), 0, 4));
        $this->assertSame(['a      │c      │e', 'b      │d      │'], \array_slice($screen(''), 0, 2), 'Between columns with content, on every row.');
        $this->assertSame(['a      │e', 'b      │', 'c      │', 'd      │'], $screen('height: 4; column-fill: auto'), 'None next to the empty last column.');
        $this->assertSame(['a'], \array_slice(array_map('rtrim', $this->render('<div style="columns: 3; column-rule: 1px solid"><p style="margin: 0">a</p></div>', 23)), 0, 1), 'None at all with one column of content.');
    }

    public function testTheBeforeAndAfterOfAMultiColumnBoxAreInItsColumns()
    {
        $screen = $this->render('<style>.cols { columns: 2; column-gap: 1 } .cols::before { content: "first"; display: block } .cols::after { content: "last"; display: block } p { margin: 0 }</style><div class="cols"><p>a</p><p>b</p></div>', 21);
        $this->assertSame(['first      b', 'a          last'], array_map('rtrim', \array_slice($screen, 0, 2)), 'The first and the last content of its flow.');
    }

    public function testTheMarginsOfTheChildrenOfAMultiColumnBoxDoNotCollapseThroughIt()
    {
        $screen = $this->render('<style>.cols { columns: 2; column-gap: 1 } p { margin: 1 0 }</style><div class="cols"><p>a</p><p>b</p><p>c</p></div><p>next</p>', 21);
        // Its own formatting context, like CSS: the margins of its first and last children in it, not past its last row,
        // which is in a column, the b of the first one
        $this->assertSame(['           c', 'a', '', 'b', '', 'next'], array_map('rtrim', \array_slice($screen, 0, 6)));
    }

    public function testTheMarginsAtABreakAreTruncated()
    {
        $html = '<style>.cols { columns: %d; column-gap: 1 } p { margin: 0 } .m { margin: 2 0 } .new { break-before: column }</style><div class="cols">%s</div><p>after</p>';
        $screen = fn (int $count, string $content) => array_map('rtrim', \array_slice($this->render(\sprintf($html, $count, $content), 23), 0, 5));
        $this->assertSame(['x           b', '            c', 'a', 'after'], \array_slice($screen(2, '<p>x</p><p style="margin: 1 0">a</p><p>b</p><p>c</p>'), 0, 4), 'The second column starts with b, not with the margin under a.');
        $this->assertSame(['        b       c', '', 'a', 'after'], \array_slice($screen(3, '<p class="m">a</p><p class="m">b</p><p class="m">c</p>'), 0, 4), 'As short as the columns are without the margins truncated.');
        $this->assertSame(['                c', '', 'a       b', 'after'], \array_slice($screen(3, '<p class="m">a</p><p class="m new">b</p><p>c</p>'), 0, 4), 'Not after a forced break: b under its margin, c past the one of b.');

        $tui = new Tui(null, new VirtualTerminal(21, 8));
        $view = (new HtmlBuilder())->build(\sprintf($html, 2, '<p>x</p><p style="margin: 1 0">a</p><p id="b">b</p><p>c</p>'))->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertSame([0, 11], [$tui->getWidgetRect($view->getById('b'))->row, $tui->getWidgetRect($view->getById('b'))->col], 'b at the top of the second column.');
    }

    public function testAChildInAPlainBlockSpansTheColumns()
    {
        $html = '<style>.cols { columns: 2; column-gap: 1 } p, h2 { margin: 0 } h2 { column-span: all }</style><div class="cols"><section id="s"><p>a</p><p>b</p><h2 id="t">Title</h2><p>c</p><p id="d">d</p></section></div>';
        $this->assertSame(['a          b', 'Title', 'c          d'], array_map('rtrim', \array_slice($this->render($html, 21), 0, 3)), 'The blocks before and after it in columns of their own, like CSS.');

        $tui = new Tui(null, new VirtualTerminal(21, 8));
        $view = (new HtmlBuilder())->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertSame([1, 0, 21], [$tui->getWidgetRect($view->getById('t'))->row, $tui->getWidgetRect($view->getById('t'))->col, $tui->getWidgetRect($view->getById('t'))->columns], 'Across the columns.');
        $this->assertSame([2, 11], [$tui->getWidgetRect($view->getById('d'))->row, $tui->getWidgetRect($view->getById('d'))->col]);
        $this->assertSame([0, 3], [$tui->getWidgetRect($view->getById('s'))->row, $tui->getWidgetRect($view->getById('s'))->rows], 'The block around them over all of them.');
    }

    public function testAChildInABoxSpansTheColumnsAndSlicesTheBox()
    {
        // Like CSS: the box, which is no formatting context of its own, is cut around it, its top edge before it, its
        // bottom edge after it, the child across the columns outside its border
        $html = '<style>.cols { columns: 2; column-gap: 1 } section { border: 1px solid } p, h2 { margin: 0 } h2 { column-span: all }</style><div class="cols"><section id="s"><p>a</p><p>b</p><p>c</p><h2 id="t">Title</h2><p>d</p><p>e</p><p id="f">f</p></section></div>';
        $screen = array_map('rtrim', \array_slice($this->render($html, 21), 0, 5));
        $this->assertSame(['┌────────┐ │b       │', '│a       │ │c       │', 'Title', '│d       │ │f       │', '│e       │ └────────┘'], $screen);

        $tui = new Tui(null, new VirtualTerminal(21, 8));
        $view = (new HtmlBuilder())->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertSame([2, 0, 21], [$tui->getWidgetRect($view->getById('t'))->row, $tui->getWidgetRect($view->getById('t'))->col, $tui->getWidgetRect($view->getById('t'))->columns], 'Across the columns.');
        $this->assertSame([3, 12], [$tui->getWidgetRect($view->getById('f'))->row, $tui->getWidgetRect($view->getById('f'))->col], 'In the second column, inside the border.');
    }

    public function testAChildInAFormattingContextOfItsOwnDoesNotSpanTheColumns()
    {
        $html = '<style>.cols { columns: 2; column-gap: 1 } section { border: 1px solid; overflow: hidden } p, h2 { margin: 0 } h2 { column-span: all }</style><div class="cols"><section><p>a</p><h2>Title</h2><p>b</p></section></div>';
        $this->assertSame('│Title   │', rtrim($this->render($html, 21)[2] ?? ''), 'Like CSS: inside its box, in a column.');
    }

    public function testTheBeforeAndAfterOfAMultiColumnBoxAreAroundItsSpanningChildren()
    {
        $html = '<style>.cols { columns: 2; column-gap: 1 } .cols::before { content: "first"; display: block } .cols::after { content: "last"; display: block } p, h2 { margin: 0 } h2 { column-span: all }</style><div class="cols">%s</div>';
        $screen = fn (string $content) => array_map('rtrim', \array_slice($this->render(\sprintf($html, $content), 21), 0, 4));
        $this->assertSame(['first      b', 'Title', 'c          last'], \array_slice($screen('<p>b</p><h2>Title</h2><p>c</p>'), 0, 3), 'In the first and the last run of columns.');
        $this->assertSame(['first', 'Title', 'last'], \array_slice($screen('<h2>Title</h2>'), 0, 3), 'In runs of their own around a spanning child alone.');
    }

    public function testANestedBlockThatAvoidsBreaksIsKeptWhole()
    {
        $screen = $this->render('<style>.cols { columns: 2; column-gap: 1 } p { margin: 0 } .keep { break-inside: avoid }</style><div class="cols"><section><p>a</p><p class="keep">b<br>c<br>d</p><p>f</p></section></div>', 16, 6);
        $this->assertSame(['a       f', 'b', 'c', 'd'], array_map('rtrim', \array_slice($screen, 0, 4)));
    }

    public function testAClonedBoxHasItsEdgesAroundEachPiece()
    {
        $html = '<style>.cols { columns: 2; column-gap: 1 } section { border: 1px solid } p { margin: 0; orphans: 1; widows: 1 } .clone { box-decoration-break: clone }</style><div class="cols"><section class="%s"><p>a<br>b<br>c<br>d</p></section></div>';
        $this->assertSame(['┌─────┐ │c    │', '│a    │ │d    │', '│b    │ └─────┘'], array_map('rtrim', \array_slice($this->render(\sprintf($html, 'slice'), 15, 6), 0, 4)), 'Sliced: the top edge in the first piece, the bottom one in the last.');
        $this->assertSame(['┌─────┐ ┌─────┐', '│a    │ │c    │', '│b    │ │d    │', '└─────┘ └─────┘'], array_map('rtrim', \array_slice($this->render(\sprintf($html, 'clone'), 15, 6), 0, 4)), 'Cloned: both edges around each piece.');
    }

    public function testTheBlocksOfACutBoxAreWhereTheyAreDrawn()
    {
        $tui = new Tui(null, new VirtualTerminal(21, 8));
        $view = (new HtmlBuilder())->build('<style>.cols { columns: 2; column-gap: 1 } p { margin: 0 }</style><div class="cols"><section><p id="a">a</p><p id="b">b</p></section></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertSame([0, 0], [$tui->getWidgetRect($view->getById('a'))->row, $tui->getWidgetRect($view->getById('a'))->col]);
        $this->assertSame([0, 11], [$tui->getWidgetRect($view->getById('b'))->row, $tui->getWidgetRect($view->getById('b'))->col], 'In the second column, though its section starts in the first.');
    }

    public function testABlockCutAcrossColumnsIsUnderThePointerInEachOfThem()
    {
        $tui = new Tui(null, new VirtualTerminal(21, 8));
        $view = (new HtmlBuilder())->build('<style>.cols { columns: 2; column-gap: 1 } p { margin: 0; orphans: 1; widows: 1 }</style><div class="cols"><p id="p">1<br>2<br>3<br>4</p></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $paragraph = $view->getById('p');
        $this->assertSame($paragraph, $view->widgetAt(0, 0));
        $this->assertSame($paragraph, $view->widgetAt(1, 11), 'Its other piece, in the second column.');
        $this->assertNotSame($paragraph, $view->widgetAt(0, 10), 'Not in the gap between them.');
    }

    public function testABlockCutAcrossColumnsInAScrollContainerIsUnderThePointerInEachOfThem()
    {
        $tui = new Tui(null, new VirtualTerminal(21, 8));
        $view = (new HtmlBuilder())->build('<style>.box { overflow: auto; max-height: 3 } .cols { columns: 2; column-gap: 1 } p { margin: 0; orphans: 1; widows: 1 }</style><div class="box" id="box"><p>0</p><div class="cols"><p id="p">1<br>2<br>3<br>4</p></div><p>5</p><p>6</p></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $paragraph = $view->getById('p');
        $this->assertSame($paragraph, $view->widgetAt(1, 0));
        $this->assertSame($paragraph, $view->widgetAt(2, 11), 'Its other piece, in the second column.');

        $view->getById('box')->scrollTo(2);
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame($paragraph, $view->widgetAt(0, 11), 'Its other piece, scrolled with it.');
        $this->assertNotSame($paragraph, $view->widgetAt(2, 11), 'Not where its piece is no more.');
    }

    public function testAStableGutterKeepsTheScrollbarColumn()
    {
        $width = static function (string $class): int {
            $tui = new Tui(null, new VirtualTerminal(30, 8));
            $view = (new HtmlBuilder())->build('<style>.box { overflow: auto; max-height: 4; width: 20ch } p { margin: 0 } .stable { scrollbar-gutter: stable }</style><div class="box '.$class.'"><p id="p">fits</p></div>')->mount($tui);
            $tui->start();
            $tui->processRender();

            return $tui->getWidgetRect($view->getById('p'))->columns;
        };
        $this->assertSame(20, $width(''), 'The content fits: the whole width.');
        $this->assertSame(19, $width('stable'), 'Stable: the column of the scrollbar is kept.');
    }

    private function render(string $html, int $columns, int $rows = 8): array
    {
        $terminal = new VirtualTerminal($columns, $rows);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer($columns, 8);
        $buffer->write($terminal->consumeOutput());

        return explode("\n", $buffer->getScreen());
    }
}
