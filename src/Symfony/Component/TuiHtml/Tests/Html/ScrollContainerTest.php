<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Css\DeclarationMapper;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Internal\StyleBridge;
use Symfony\Component\TuiHtml\Style\MediaContext;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Widget\ContainerWidget;

final class ScrollContainerTest extends TestCase
{
    public function testAHiddenOverflowBoxProvidesItsScrollTimeline()
    {
        [$tui, $view] = $this->mount('<style>@keyframes DetectOverflow { from, to { --More: block } } #box { overflow: hidden; height: 2; --More: none; animation: linear DetectOverflow; animation-timeline: scroll(self) } #more { display: var(--More) }</style><div id="box"><div style="height: 2"><div>one</div><div>two</div><div>three</div><div>four</div></div><p id="more">More</p></div>');
        $this->assertFalse((fn () => StyleBridge::resolve($this->getRenderer(), $view->getById('more'))?->getHidden())->call($tui));
    }

    public function testAScrollTimelineWithoutOverflowIsInactive()
    {
        [$tui, $view] = $this->mount('<style>@keyframes DetectOverflow { from, to { --More: block } } #box { overflow: auto; max-height: 4; --More: none; animation: linear DetectOverflow; animation-timeline: scroll(self) } #more { display: var(--More) }</style><div id="box"><div>one</div><p id="more">More</p></div>');
        $this->assertTrue((fn () => StyleBridge::resolve($this->getRenderer(), $view->getById('more'))?->getHidden())->call($tui));
    }

    public function testAScrollTimelineAnimatesAnInheritedCustomProperty()
    {
        [$tui, $view, $screen] = $this->mount('<style>@keyframes DetectOverflow { from, to { --More: block } } #box { overflow: auto; max-height: 2; --More: none; animation: linear DetectOverflow; animation-timeline: scroll(self) } #more { display: var(--More) }</style><div id="box"><div>one<br>two<br>three<br>four</div><p id="more">More</p></div>');
        $box = $view->getById('box');
        $this->assertGreaterThan($box->getClientHeight(), $box->getScrollHeight());
        $this->assertFalse((fn () => StyleBridge::resolve($this->getRenderer(), $view->getById('more'))?->getHidden())->call($tui));
        $box->scrollTo(1);
        $tui->requestRender();
        $tui->processRender();
        $this->assertFalse((fn () => StyleBridge::resolve($this->getRenderer(), $view->getById('more'))?->getHidden())->call($tui));
    }

    public function testTheContentScrollsInsideTheContainer()
    {
        [$tui, $view, $screen] = $this->mount('<div id="box" style="overflow: auto; max-height: 3">'.$this->lines(8).'</div><p>after</p>');
        $box = $view->getById('box');
        $this->assertInstanceOf(ContainerWidget::class, $box);
        $this->assertSame(['line 1', 'line 2', 'line 3', 'after'], $this->text($screen(), 4));
        $this->assertSame([0, 8, 3], [$box->getScrollTop(), $box->getScrollHeight(), $box->getClientHeight()]);
        $this->assertStringEndsWith('┃', $screen()[0] ?? '', 'The thumb is at the top.');
        $box->scrollBy(2);
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(['line 3', 'line 4', 'line 5', 'after'], $this->text($screen(), 4));
        $box->scrollTo(100);
        $this->assertSame(5, $box->getScrollTop(), 'Kept within the content.');
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(['line 6', 'line 7', 'line 8'], $this->text($screen(), 3));
        $this->assertStringEndsWith('┃', $screen()[2] ?? '', 'The thumb is at the bottom.');
    }

    public function testContentThatFitsIsNotScrolled()
    {
        [, $view, $screen] = $this->mount('<div id="auto" style="overflow: auto; max-height: 3">'.$this->lines(2).'</div><div style="overflow-y: scroll; height: 2; scrollbar-width: none"><p>no bar</p></div><div style="overflow: scroll; height: 2"><p>bar</p></div>');
        $lines = $screen();
        $this->assertSame('line 1', rtrim($lines[0]), 'auto draws no scrollbar when it fits.');
        $this->assertSame('no bar', rtrim($lines[2]));
        $this->assertSame(['bar', '┃'], [trim(mb_substr($lines[4], 0, 49)), mb_substr(rtrim($lines[4]), -1)], 'scroll always draws one, all thumb when the content fits.');
        $this->assertSame(0, $view->getById('auto')->getScrollTop());
    }

    public function testFocusMovingInScrollsTheFocusedWidgetIntoView()
    {
        [$tui, $view, $screen] = $this->mount('<button id="top">Top</button><div id="box" style="overflow: auto; max-height: 3">'.$this->lines(6).'<input id="field" value="deep"></div>');
        $box = $view->getById('box');
        $this->assertSame(0, $box->getScrollTop());
        $tui->setFocus($view->getById('field'));
        $tui->processRender();
        $this->assertSame(4, $box->getScrollTop());
        $this->assertStringContainsString('deep', implode("\n", $screen()));
        $box->scrollTo(0);
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(0, $box->getScrollTop(), 'Once in view, the user can scroll away from it.');
        $this->assertNull($tui->getWidgetRect($view->getById('field')), 'A widget scrolled out has no position.');
    }

    public function testTheWheelScrollsTheContainerUnderThePointer()
    {
        [$tui, $view] = $this->mount('<div id="outer" style="overflow: auto; max-height: 6"><div id="inner" style="overflow: auto; max-height: 2">'.$this->lines(4).'</div>'.$this->lines(8).'</div>');
        $inner = $view->getById('inner');
        $outer = $view->getById('outer');
        $this->assertTrue($view->scrollAt(0, 2, 1));
        $this->assertSame([1, 0], [$inner->getScrollTop(), $outer->getScrollTop()]);
        $tui->processRender();
        $view->scrollAt(0, 2, 5);
        $this->assertSame(2, $inner->getScrollTop());
        $tui->processRender();
        $this->assertTrue($view->scrollAt(0, 2, 1), 'At its end, the container around it scrolls.');
        $this->assertSame(1, $outer->getScrollTop());
        $this->assertFalse($view->scrollAt(0, 2, -0));
        $this->assertFalse($view->scrollAt(40, 0, 1), 'Nothing there.');
    }

    public function testOverscrollBehaviorContainStopsTheChain()
    {
        [$tui, $view] = $this->mount('<div id="outer" style="overflow: auto; max-height: 6"><div id="inner" style="overflow: auto; max-height: 2; overscroll-behavior: contain"><button id="go">Go</button>'.$this->lines(3).'</div>'.$this->lines(8).'</div>');
        $inner = $view->getById('inner');
        $outer = $view->getById('outer');
        $view->scrollAt(0, 2, 5);
        $tui->processRender();
        $this->assertSame(2, $inner->getScrollTop());
        $this->assertFalse($view->scrollAt(0, 2, 1), 'At its end, the containers around it do not scroll.');
        $this->assertSame(0, $outer->getScrollTop());
        $tui->setFocus($view->getById('go'));
        $tui->processRender();
        $inner->scrollTo(2);
        $tui->processRender();
        $tui->handleInput("\x1b[6~");
        $this->assertSame(0, $outer->getScrollTop(), 'Nor do the page keys scroll them.');
        [$tui, $view] = $this->mount('<div id="outer" style="overflow: auto; max-height: 6"><div id="inner" style="overflow: auto; max-height: 2; overscroll-behavior: contain auto">'.$this->lines(3).'</div>'.$this->lines(8).'</div>');
        $view->scrollAt(0, 2, 5);
        $tui->processRender();
        $this->assertTrue($view->scrollAt(0, 2, 1), 'The second value is the vertical one.');
    }

    public function testScrollSnap()
    {
        $slides = implode('', array_map(static fn (int $i) => "<section><p>slide {$i}</p><p>a</p><p>b</p></section>", range(1, 4)));
        [$tui, $view] = $this->mount('<style>section { scroll-snap-align: start }</style><div id="box" style="overflow: auto; max-height: 3; scroll-snap-type: y mandatory">'.$slides.'</div>');
        $box = $view->getById('box');
        $box->scrollBy(1);
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(3, $box->getScrollTop(), 'A small scroll goes to the next slide.');
        $box->scrollBy(5);
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(9, $box->getScrollTop(), 'To the slide nearest where it was scrolled to, on the way.');
        $box->scrollBy(-1);
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(6, $box->getScrollTop(), 'Back up too.');
        [$tui, $view] = $this->mount('<style>section { scroll-snap-align: start } section:nth-child(2) { scroll-snap-stop: always }</style><div id="box" style="overflow: auto; max-height: 3; scroll-snap-type: y">'.$slides.'</div>');
        $box = $view->getById('box');
        $box->scrollBy(8);
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(3, $box->getScrollTop(), 'scroll-snap-stop: always, it does not go past the second slide.');
        $box->scrollTo(4);
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(4, $box->getScrollTop(), 'proximity, the default: no slide is within a third of its height.');
        [$tui, $view] = $this->mount('<style>p { scroll-snap-align: center }</style><div id="box" style="overflow: auto; max-height: 3; scroll-snap-type: block mandatory; scroll-padding-top: 0">'.$this->lines(9).'</div>');
        $box = $view->getById('box');
        $box->scrollBy(1);
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(1, $box->getScrollTop(), 'Centered: line 3 in the middle of the 3 rows.');
    }

    public function testWideContentScrollsSideways()
    {
        $wide = implode('', range('a', 'z')).implode('', range('A', 'Z'));
        [$tui, $view, $screen] = $this->mount('<div id="box" style="overflow-x: auto; width: 20ch"><pre>'.$wide.'</pre><p>Text that wraps in the box.</p><button id="go">Go</button></div><p>after</p>');
        $box = $view->getById('box');
        $lines = $screen();
        $this->assertSame([0, 52, 20], [$box->getScrollLeft(), $box->getScrollWidth(), $box->getClientWidth()]);
        $this->assertSame('abcdefghijklmnopqrst', rtrim($lines[0]));
        $this->assertSame('Text that wraps in', rtrim($lines[1]), 'The text wraps in the window, not in the content.');
        $this->assertSame('━━━━━━━━────────────', $lines[4], 'The bar under the content: 20 of the 52 columns shown, at the left.');
        $this->assertSame('after', rtrim($lines[5]));
        $box->scrollToColumn(40);
        $tui->requestRender();
        $tui->processRender();
        $lines = $screen();
        $this->assertSame(32, $box->getScrollLeft(), 'Not past the end.');
        $this->assertSame('GHIJKLMNOPQRSTUVWXYZ', rtrim($lines[0]));
        $this->assertSame('────────────━━━━━━━━', $lines[4]);
        $this->assertNull($tui->getWidgetRect($view->getById('go')), 'Scrolled out: no position.');
        $box->scrollByColumns(-30);
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame([2, 0, 4], [$box->getScrollLeft(), $tui->getWidgetRect($view->getById('go'))?->col, $tui->getWidgetRect($view->getById('go'))?->columns], 'The widgets move along, cut at the edge: the button, as wide as its content, 2 of its 6 columns out.');
    }

    public function testAGridScrollsSidewaysOverItsTracks()
    {
        [$tui, $view, $screen] = $this->mount('<div id="box" style="display: grid; grid-template-columns: repeat(3, 12ch); gap: 1ch; overflow-x: auto; width: 20ch"><p>one</p><p>two</p><p>three</p></div>');
        $box = $view->getById('box');
        $this->assertSame([38, 20], [$box->getScrollWidth(), $box->getClientWidth()], 'As wide as its tracks, like the cards of the Guardian, not squeezed in the window.');
        $this->assertSame('one          two', rtrim($screen()[0]));
        $box->scrollToColumn(13);
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('two          three', rtrim($screen()[0]));
        [, $view] = $this->mount('<div id="box" style="display: grid; grid-template-columns: repeat(3, 60%); overflow-x: auto; width: 20ch"><p>one</p><p>two</p><p>three</p></div>');
        $this->assertSame(36, $view->getById('box')->getScrollWidth(), 'Percentages of the window.');
    }

    public function testWhatChangesInAContainerScrolledSidewaysIsDrawn()
    {
        $wide = implode('', range('a', 'z')).implode('', range('A', 'Z'));
        [$tui, $view, $screen] = $this->mount('<style>#t:hover::after { content: " hovered" }</style><div id="box" style="overflow-x: auto; width: 20ch"><pre>'.$wide.'</pre><p id="t">text</p></div>');
        $this->assertSame('text', rtrim($screen()[1]));
        $view->getById('t')->setState('hover');
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('text hovered', rtrim($screen()[1]), 'The line that changed, the others as they were.');
        $this->assertSame('abcdefghijklmnopqrst', rtrim($screen()[0]));
        $view->getById('t')->setState('hover', false);
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame('text', rtrim($screen()[1]));
    }

    public function testFocusAndTargetsScrollSideways()
    {
        $buttons = implode('', array_map(static fn (int $i) => "<button id=\"b{$i}\">Button {$i}</button>", range(1, 6)));
        [$tui, $view] = $this->mount('<div id="bar" style="display: flex; gap: 1ch; overflow-x: auto; width: 30ch; scroll-padding-inline: 2ch">'.$buttons.'</div><div id="box" style="display: flex; overflow-x: auto; width: 10ch"><pre>'.str_repeat('-', 30).'</pre><p id="far" style="scroll-margin-left: 1ch">far</p></div>');
        $bar = $view->getById('bar');
        $this->assertSame(0, $bar->getScrollLeft());
        $width = $bar->getScrollWidth();
        $tui->setFocus($view->getById('b6'));
        $tui->processRender();
        $this->assertSame($width - 30, $bar->getScrollLeft(), 'The last button in view, at the right end.');
        $tui->setFocus($view->getById('b1'));
        $tui->processRender();
        $this->assertSame(0, $bar->getScrollLeft(), 'Back to the first one, the scroll-padding kept clear.');
        $tui->setFocus($view->getById('b3'));
        $tui->processRender();
        $rect = $tui->getWidgetRect($view->getById('b3'));
        $this->assertGreaterThanOrEqual(2, $rect->col);
        $this->assertLessThanOrEqual(28, $rect->col + $rect->columns, 'Within the scroll-padding on both sides.');
        $view->navigate('#far');
        $tui->processRender();
        $this->assertSame(23, $view->getById('box')->getScrollLeft(), 'A target: in view at the right, its scroll-margin kept at its left.');
    }

    public function testTheWheelAndTheArrowKeysScrollSideways()
    {
        [$tui, $view] = $this->mount('<div id="box" style="overflow-x: auto; width: 20ch"><pre>'.str_repeat('0123456789', 5).'</pre><button id="go">Go</button></div><input id="field" value="text">');
        $box = $view->getById('box');
        $this->assertTrue($view->scrollAt(0, 2, 0, 3));
        $this->assertSame(3, $box->getScrollLeft());
        $tui->processRender();
        $this->assertTrue($view->scrollAt(0, 2, 2, -1), 'Both ways at once: sideways here, down nowhere.');
        $this->assertSame(2, $box->getScrollLeft());
        $tui->setFocus($view->getById('go'));
        $tui->processRender();
        $box->scrollToColumn(0);
        $tui->requestRender();
        $tui->processRender();
        $tui->handleInput("\x1b[C");
        $this->assertSame(4, $box->getScrollLeft(), 'Right scrolls the container around the focus a few columns.');
        $tui->processRender();
        $tui->handleInput("\x1b[D");
        $this->assertSame(0, $box->getScrollLeft());
        $tui->setFocus($view->getById('field'));
        $tui->processRender();
        $tui->handleInput("\x1b[C");
        $this->assertSame(0, $box->getScrollLeft(), 'A text field moves its cursor instead, and it is not in the container.');
    }

    public function testOverscrollBehaviorXStopsTheChainSideways()
    {
        $pre = '<pre>'.str_repeat('0123456789', 5).'</pre>';
        [$tui, $view] = $this->mount('<div id="outer" style="overflow-x: auto; width: 30ch"><div id="inner" style="overflow-x: auto; width: 20ch; overscroll-behavior-x: contain">'.$pre.'</div>'.str_repeat('-', 60).'</div>');
        $view->getById('outer')->scrollToColumn(5);
        $tui->requestRender();
        $tui->processRender();
        $this->assertFalse($view->scrollAt(0, 2, 0, -3), 'At its left end, the container around it does not scroll.');
        $this->assertSame(5, $view->getById('outer')->getScrollLeft());
        [$tui, $view] = $this->mount('<div id="outer" style="overflow-x: auto; width: 30ch"><div id="inner" style="overflow-x: auto; width: 20ch">'.$pre.'</div>'.str_repeat('-', 60).'</div>');
        $view->getById('outer')->scrollToColumn(5);
        $tui->requestRender();
        $tui->processRender();
        $this->assertTrue($view->scrollAt(0, 2, 0, -3), 'Without it, the one around it does.');
        $this->assertSame(2, $view->getById('outer')->getScrollLeft());
    }

    public function testBothScrollbars()
    {
        [$tui, $view, $screen] = $this->mount('<div id="box" style="overflow: auto; width: 12ch; max-height: 4"><pre>'.implode("\n", array_map(static fn (int $i) => "line {$i} ".str_repeat('-', 10), range(1, 6))).'</pre></div><div style="overflow-x: scroll; width: 12ch; scrollbar-width: thin">fits</div><div style="overflow-x: auto; overflow-y: hidden; width: 12ch">'.str_repeat('<p style="white-space: nowrap">a nowrap line, too wide</p>', 2).'</div>');
        $box = $view->getById('box');
        $lines = $screen();
        $this->assertSame([0, 0, 6, 3, 17, 11], [$box->getScrollTop(), $box->getScrollLeft(), $box->getScrollHeight(), $box->getClientHeight(), $box->getScrollWidth(), $box->getClientWidth()]);
        $this->assertSame('line 1 ----┃', $lines[0]);
        $this->assertSame('line 3 ----│', $lines[2]);
        $this->assertSame('━━━━━━━────', $lines[3], 'The bars meet at a blank corner.');
        $this->assertSame('fits', rtrim($lines[4]));
        $this->assertSame('────────────', $lines[5], 'overflow-x: scroll, the bar even when it fits; thin.');
        $this->assertSame('a nowrap lin', $lines[6]);
        $this->assertSame('a nowrap lin', $lines[7], 'overflow-y: hidden: no vertical scrolling.');
        $this->assertSame('━━━━━━──────', $lines[8]);
    }

    public function testScrollSnapSideways()
    {
        $cards = implode('', array_map(static fn (int $i) => "<pre>card {$i}    </pre>", range(1, 5)));
        [$tui, $view] = $this->mount('<style>pre { scroll-snap-align: none start }</style><div id="row" style="display: flex; overflow-x: auto; width: 20ch; scroll-snap-type: x mandatory">'.$cards.'</div>');
        $row = $view->getById('row');
        $row->scrollByColumns(1);
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(10, $row->getScrollLeft(), 'A small scroll goes to the next card.');
        $row->scrollByColumns(-2);
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(0, $row->getScrollLeft());
    }

    public function testStickyFootersStayAboveTheBottom()
    {
        [$tui, $view, $screen] = $this->mount('<style>.foot { position: sticky; bottom: 0 } section p { margin: 0 }</style><div id="box" style="overflow: auto; max-height: 4; scrollbar-width: none"><section><p>A 1</p><p>A 2</p><p class="foot">A end</p></section>'.$this->lines(4).'<p class="foot">Footer</p></div>');
        $lines = array_map('rtrim', $screen());
        $this->assertSame(['A 1', 'A 2', 'A end', 'Footer'], \array_slice($lines, 0, 4), 'The footer of the box above its bottom, the one of the section where it is.');
        $view->getById('box')->scrollTo(4);
        $tui->requestRender();
        $tui->processRender();
        $lines = array_map('rtrim', $screen());
        $this->assertSame('Footer', $lines[3]);
    }

    public function testPageKeysScrollTheContainerAroundTheFocus()
    {
        [$tui, $view] = $this->mount('<div id="box" style="overflow: auto; max-height: 4"><button id="go" autofocus>Go</button>'.$this->lines(10).'</div><div id="other" style="overflow: auto; max-height: 2"><textarea id="notes">a</textarea>'.$this->lines(4).'</div>');
        $box = $view->getById('box');
        $this->assertSame($view->getById('go'), $tui->getFocus());
        $tui->handleInput("\x1b[6~");
        $this->assertSame(3, $box->getScrollTop(), 'A page is the rows shown but one.');
        $tui->processRender();
        $tui->handleInput("\x1b[6~");
        $tui->handleInput("\x1b[5~");
        $this->assertSame(3, $box->getScrollTop());
        $tui->setFocus($view->getById('notes'));
        $tui->processRender();
        $tui->handleInput("\x1b[6~");
        $this->assertSame(0, $view->getById('other')->getScrollTop(), 'A textarea pages itself.');
    }

    public function testPageKeysScrollTheFirstContainerWithoutFocus()
    {
        [$tui, $view] = $this->mount('<p>Read me</p><div id="box" style="overflow: auto; max-height: 3">'.$this->lines(6).'</div>');
        $this->assertNull($tui->getFocus());
        $tui->handleInput("\x1b[6~");
        $this->assertSame(2, $view->getById('box')->getScrollTop());
        $tui->handleInput('x');
        $this->assertSame(2, $view->getById('box')->getScrollTop(), 'Other keys do nothing.');
    }

    public function testStickyHeadersStayInViewWhileTheirSectionIs()
    {
        $section = static fn (string $name, int $count) => "<section><h2>{$name}</h2>".implode('', array_map(static fn (int $i) => "<p>{$name} {$i}</p>", range(1, $count))).'</section>';
        [$tui, $view, $screen] = $this->mount('<style>.top { position: sticky; top: 0 } h2 { position: sticky; top: 1; margin: 0 }</style><div id="box" style="overflow: auto; max-height: 4"><p class="top" id="top">Header</p>'.$section('A', 3).$section('B', 4).'</div>');
        $box = $view->getById('box');
        $box->scrollTo(2);
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(['Header', 'A', 'A 3', 'B'], $this->text($screen(), 4));
        $this->assertSame('top', $view->widgetAt(0, 2)?->getId(), 'A pinned widget is where it is drawn.');
        $this->assertSame([1, 1], [$tui->getWidgetRect($view->querySelector('h2'))?->row, $tui->getWidgetRect($view->querySelector('h2'))?->rows]);
        $box->scrollTo(5);
        $tui->requestRender();
        $tui->processRender();
        $this->assertSame(['Header', 'B', 'B 2', 'B 3'], $this->text($screen(), 4), 'The next section pushed the first header out.');
    }

    public function testStickyNeedsATop()
    {
        $mapper = new DeclarationMapper();
        $media = new MediaContext();
        $this->assertSame(2, $mapper->map(['position' => 'sticky', 'top' => '2'], $media)->getStickyTop());
        $this->assertSame(1, $mapper->map(['position' => 'sticky', 'top' => '16px'], $media)->getStickyTop(), 'In rows.');
        $this->assertNull($mapper->map(['position' => 'sticky'], $media)->getStickyTop(), 'Without a top, sticky does nothing, like CSS.');
        $this->assertNull($mapper->map(['position' => 'relative', 'top' => '2'], $media)->getStickyTop());
        $this->assertTrue($mapper->supports('position', 'absolute'));
        $this->assertFalse($mapper->supports('position', 'sideways'));
    }

    private function mount(string $html): array
    {
        $terminal = new VirtualTerminal(50, 20);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(50, 20);

        return [$tui, $view, static function () use ($terminal, $buffer): array {
            $buffer->write($terminal->consumeOutput());

            return explode("\n", $buffer->getScreen());
        }];
    }

    private function lines(int $count): string
    {
        return implode('', array_map(static fn (int $i) => "<p>line {$i}</p>", range(1, $count)));
    }

    private function text(array $lines, int $count): array
    {
        return array_map(static fn (string $line) => rtrim($line, ' │┃'), \array_slice($lines, 0, $count));
    }
}
