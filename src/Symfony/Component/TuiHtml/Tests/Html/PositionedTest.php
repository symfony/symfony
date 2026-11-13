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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Html\HtmlView;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;

final class PositionedTest extends TestCase
{
    public function testAbsoluteElementsAreDrawnOverTheFlow()
    {
        [$tui, $view, $screen] = $this->mount('<style>.menu { position: relative } .drop { position: absolute; top: 100%; left: 2 }</style><div class="menu" id="menu"><p>Menu</p><p class="drop" id="drop">Drop</p></div><p>first line</p><p>second line</p>');
        $lines = $screen();
        $this->assertSame('Menu', rtrim($lines[0]), 'It takes no room in the flow.');
        $this->assertSame('fiDropline', rtrim($lines[1]), 'Below its containing block, over the text.');
        $this->assertSame('second line', rtrim($lines[2]));
        $this->assertSame([1, 2, 4, 1], self::rect($tui, $view, 'drop'));
        $this->assertSame('drop', $view->widgetAt(1, 3)?->getId(), 'The pointer finds it first.');
    }

    public function testAnEmptyContainingBlockIsWhereItWouldBeInTheFlow()
    {
        // Like an empty <div class="position-relative"> of Bootstrap holding a badge or a menu: no rows, but a place
        [$tui, $view, $screen] = $this->mount('<p>one</p><p>two</p><div style="position: relative; padding: 0 1"><p id="drop" style="position: absolute; top: 0; left: 2">Drop</p></div><p>three three</p>');
        $lines = $screen();
        $this->assertSame(['one', 'two', 'thDropthree'], array_map(rtrim(...), \array_slice($lines, 0, 3)));
        $this->assertSame([2, 2, 4, 1], self::rect($tui, $view, 'drop'));
    }

    public function testAnElementPlacedPastItsContainingBlockKeepsItsWords()
    {
        // Like a badge of Bootstrap at start-100: no room left in its containing block, over its right border, the word overflows it unbroken
        [$tui, $view, $screen] = $this->mount('<div style="position: relative; display: inline-block; border: 1px solid">Inbox<span id="badge" style="position: absolute; top: 0; left: 100%">99+</span></div>');
        $this->assertSame(['┌─────┐', '│Inbox99+', '└─────┘'], array_map(rtrim(...), \array_slice($screen(), 0, 3)));
        $this->assertSame([1, 6, 3, 1], self::rect($tui, $view, 'badge'));
    }

    public function testARelativeElementAfterAnAbsoluteOneIsDrawnOverIt()
    {
        // Like the label of a divider of Tailwind over the line placed under it: positioned elements in document order
        [, , $screen] = $this->mount('<div style="position: relative"><div style="position: absolute; inset: 0"><div style="border-top: 1px solid"></div></div><div style="position: relative; display: flex; justify-content: center"><span style="background: black; padding: 0 1ch">or</span></div></div>');
        $this->assertSame('──────── or ────────', rtrim($screen()[0]));
    }

    #[DataProvider('provideFloatSides')]
    public function testAnAbsoluteElementIsAsWideAsItsFloatsSideBySide(string $side, string $row)
    {
        // Like a dropdown menu of floats of cppreference.com: its floats on one row, not one under the other
        [, , $screen] = $this->mount('<div style="position: relative; height: 4"><div style="position: absolute; top: 0; left: 0; border: 1px solid"><span style="float: '.$side.'">alpha</span><span style="float: '.$side.'">beta</span><span style="float: '.$side.'">gamma</span></div></div>');
        $this->assertSame(['┌────────────────┐', $row, '└────────────────┘'], array_map(rtrim(...), \array_slice($screen(), 0, 3)));
    }

    public static function provideFloatSides(): iterable
    {
        yield 'left' => ['left', '│alpha beta gamma│'];
        yield 'right' => ['right', '│gamma beta alpha│'];
    }

    public function testAFloatThatClearsStartsANewRowOfTheWidthOfAnAbsoluteElement()
    {
        [, , $screen] = $this->mount('<div style="position: relative; height: 4"><div style="position: absolute; top: 0; left: 0; border: 1px solid"><span style="float: left">alpha</span><span style="float: left">beta</span><span style="float: left; clear: left">gamma</span></div></div>');
        $this->assertSame(['┌──────────┐', '│alpha beta│', '│gamma     │', '└──────────┘'], array_map(rtrim(...), \array_slice($screen(), 0, 4)));
    }

    public function testAFullScreenOverlayOfAPercentageLeastHeightCentersItsBox()
    {
        // Like the modal of Pico: fixed over the screen, min-height: 100%, its article in the middle
        [, , $screen] = $this->mount('<p>page</p><div style="position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; min-height: 100%"><b>modal</b></div>');
        $this->assertSame(4, array_search('modal', array_map(trim(...), $screen()), true), 'In the middle of the 10 rows.');
    }

    public function testARelativeElementAfterAFixedOneIsDrawnPastTheScreen()
    {
        // Like the content of the documentation of Node.js after its skip link, fixed above the screen: a page taller
        // than the screen, drawn to its end
        [, , $screen] = $this->mount('<a href="#content" style="position: fixed; top: -300%">Skip</a><div id="content" style="position: relative">'.implode('', array_map(static fn (int $i) => '<p>Line '.$i.'</p>', range(0, 14))).'</div>');
        $this->assertSame(['Line 5', 'Line 14'], [rtrim($screen()[0]), rtrim($screen()[9])]);
    }

    public function testFixedElementsAreAtTheirInsetsFromTheScreen()
    {
        [$tui, $view, $screen] = $this->mount('<style>.toast { position: fixed; right: 1; bottom: 0 } .low { position: fixed; top: 0; left: 0; z-index: -1 } .high { position: fixed; top: 0; left: 0; z-index: 5 }</style><p>page</p><p class="toast" id="toast">Saved</p><p class="high">high</p><p class="low">low!!</p>');
        $lines = $screen();
        $this->assertSame([9, 14, 5, 1], self::rect($tui, $view, 'toast'));
        $this->assertSame('Saved', trim($lines[9]));
        $this->assertSame('high!', rtrim($lines[0]), 'By z-index: the highest is drawn last.');
    }

    public function testModalDialogs()
    {
        [$tui, $view, $screen] = $this->mount('<button id="before" autofocus>Before</button><dialog id="dialog"><p>Sure?</p><button id="yes">Yes</button></dialog><p>page text under it</p>');
        $dialog = $view->getById('dialog');
        $this->assertSame($view->getById('before'), $tui->getFocus());
        $this->assertFalse($dialog->hasState('open'));
        $this->assertStringNotContainsString('Sure?', implode("\n", $screen()), 'Closed: not shown.');
        $view->showModal($dialog);
        $tui->processRender();
        $this->assertTrue($dialog->hasState('modal'));
        $this->assertSame($view->getById('yes'), $tui->getFocus(), 'The focus goes into the dialog.');
        $this->assertTrue($view->getById('before')->getParent()?->hasState('inert'), 'The page is inert: the line the button is in.');
        $rect = $tui->getWidgetRect($dialog);
        $this->assertNotNull($rect);
        $this->assertGreaterThan(0, $rect->col, 'Centered at its content width.');
        $this->assertSame(intdiv(20 - $rect->columns, 2), $rect->col);
        $this->assertStringContainsString('Sure?', implode("\n", $screen()));
        $tui->handleInput("\x1b");
        $tui->processRender();
        $this->assertFalse($dialog->hasState('open'), 'Escape closes it.');
        $this->assertFalse($view->getById('before')->hasState('inert'));
        $this->assertSame($view->getById('before'), $tui->getFocus(), 'The focus comes back.');
    }

    public function testARelativeNegativeLayerStaysUnderTheFlow()
    {
        [$tui, , $screen] = $this->mount('<div style="isolation: isolate; background: #0000ff"><p>Page text</p><div style="position: relative; top: -1; width: 9ch; height: 1; background: #ff0000; z-index: -1"></div></div>');
        $output = $tui->getTerminal()->getOutput();
        $this->assertSame('Page text', rtrim($screen()[0]));
        $this->assertMatchesRegularExpression('/48;2;255;0;0m(?:\e\[[0-9;]*m)*Page/', $output);
    }

    public function testANegativeZIndexIsOverTheBackgroundOfThePage()
    {
        $terminal = new VirtualTerminal(30, 4);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build('<style>body { background: #ffffff; color: #000000; margin: 0 }</style><div style="position: relative"><p>Page text</p><div style="position: absolute; top: 0; left: 0; width: 4ch; height: 1; background: #000080; z-index: -1"></div></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        // Its background under the text of the page, the background of the page past it
        $this->assertMatchesRegularExpression('/48;2;0;0;128m(?:\e\[[0-9;]*m)*Page/', $terminal->getOutput());
        $this->assertMatchesRegularExpression('/48;2;255;255;255m(?:\e\[[0-9;]*m)*text/', $terminal->getOutput());
        $tui->stop();
    }

    public function testAPositionedElementIsOrderedInsideItsStackingContext()
    {
        $html = '<style>.context { position: relative; z-index: 1 } .inner { position: absolute; top: 0; left: 0; z-index: 100 } .sibling { position: absolute; top: 0; left: 0; z-index: 2 }</style><div class="context"><p class="inner" id="inner">inner 100</p></div><p class="sibling" id="sibling">sib 2</p>';
        [, $view, $screen] = $this->mount($html);
        // The space of the sibling, without a background, shows the text under it, like CSS
        $this->assertSame('sibe2 100', rtrim($screen()[0]), 'Its z-index of 100 orders it inside its parent of z-index 1, under the sibling of z-index 2.');
        $this->assertSame('sibling', $view->widgetAt(0, 1)?->getId(), 'The pointer follows the same order.');
        $this->assertSame('inner', $view->widgetAt(0, 7)?->getId());
        // Without a z-index, the parent is no stacking context: the z-index of 100 is of the page
        [, $view, $screen] = $this->mount(str_replace('.context { position: relative; z-index: 1 }', '.context { position: relative }', $html));
        $this->assertSame('inner 100', rtrim($screen()[0]));
        $this->assertSame('inner', $view->widgetAt(0, 1)?->getId());
    }

    public function testAZIndexOfZeroAndIsolationMakeAStackingContext()
    {
        foreach (['position: relative; z-index: 0', 'isolation: isolate', 'opacity: .9'] as $context) {
            [, $view, $screen] = $this->mount('<div style="'.$context.'"><p id="inner" style="position: absolute; top: 0; left: 0; z-index: 5">inner 5</p></div><p id="over" style="position: absolute; top: 0; left: 0; z-index: 1">z1</p>');
            $this->assertSame('z1ner 5', rtrim($screen()[0]), $context);
            $this->assertSame('over', $view->widgetAt(0, 0)?->getId(), $context);
            $this->assertSame('inner', $view->widgetAt(0, 4)?->getId(), $context);
        }
    }

    public function testAModalDialogIsOverTheStackingContextsAroundIt()
    {
        [$tui, $view, $screen] = $this->mount('<div style="position: relative; z-index: 1"><dialog id="dialog"><p>Sure?</p></dialog></div><p style="position: fixed; top: 0; left: 0; width: 20ch; height: 10; background: red; z-index: 2000">cover</p>');
        $view->showModal($view->getById('dialog'));
        $tui->processRender();
        $this->assertStringContainsString('Sure?', implode("\n", $screen()), 'In the top layer, over a z-index of 2000 outside its context of 1.');
    }

    public function testANegativeZIndexInsideAStackingContextIsUnderItsContent()
    {
        $terminal = new VirtualTerminal(30, 4);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<div id="card" style="position: absolute; top: 0; left: 0; width: 10ch; height: 2; background: #000080; z-index: 1"><p id="text">Card</p><div id="under" style="position: absolute; top: 0; left: 2; width: 6ch; height: 3; background: #800000; z-index: -1"></div></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $output = $terminal->getOutput();
        // Over the background of its context, under its text, and over the page past it
        $this->assertMatchesRegularExpression('/48;2;0;0;128mCa\e\[[0-9;]*48;2;128;0;0mrd/', $output);
        $this->assertMatchesRegularExpression('/\n {2}\e\[[0-9;]*48;2;128;0;0m {6}/', $output, 'Its first frame draws it too.');
        $this->assertSame('text', $view->widgetAt(0, 3)?->getId(), 'Its context takes the pointer over it.');
        $this->assertSame('under', $view->widgetAt(2, 3)?->getId(), 'Below its context, it does.');
        $tui->stop();
    }

    public function testARelativeElementAfterAnAbsoluteOneInABoxOfASetHeightIsDrawnOverIt()
    {
        [, , $screen] = $this->mount('<div style="position: relative; height: 4"><div style="position: absolute; top: 0; left: 0; width: 10ch; height: 2; background: blue">ABS</div><p style="position: relative">REL</p></div>');
        $this->assertSame('REL', rtrim($screen()[0]));
    }

    public function testARelativeItemAfterAnAbsoluteOneInARowWithABeforeIsDrawnOverIt()
    {
        [, , $screen] = $this->mount('<style>.f::before { content: "*" }</style><div class="f" style="display: flex; position: relative; height: 3"><span style="position: absolute; left: 0; top: 0; background: blue">xxxxxx</span><b style="position: relative">label</b></div>');
        $this->assertSame('xlabel', rtrim($screen()[0]));
    }

    public function testARelativeElementAfterAnAbsoluteOneStaysCutByABoxThatClipsSideways()
    {
        [, , $screen] = $this->mount('<div style="position: relative"><div style="position: absolute; top: 0; left: 0">ABS</div><div style="width: 5ch; overflow: hidden"><p style="position: relative; left: 4">LONGTEXT</p></div></div>');
        $this->assertStringNotContainsString('LONGTEXT', implode("\n", $screen()));
    }

    public function testARelativeElementMovedPastABoxOfAPercentageOfNoDefiniteHeightIsDrawnPastIt()
    {
        [, , $screen] = $this->mount('<div><div style="height: 50%"><p style="position: relative; top: 3">x</p></div></div><p>after</p>');
        $this->assertSame(['', 'after', '', 'x'], array_map(rtrim(...), \array_slice($screen(), 0, 4)));
    }

    public function testARelativeElementAfterAnAbsoluteOneIsDrawnOverItInAPageThatClipsSideways()
    {
        [, , $screen] = $this->mount('<body style="overflow-x: hidden"><div style="position: relative"><div style="position: absolute; top: 0; left: 0; background: blue">ABSABS</div><p style="position: relative">label</p></div></body>');
        $this->assertSame('labelS', rtrim($screen()[0]));
    }

    public function testAnAbsoluteElementIsCutByAContainingBlockThatClips()
    {
        [, , $screen] = $this->mount('<div style="position: relative; overflow: hidden; width: 10ch; height: 4; border: 1px solid"><div style="position: absolute; top: 1; left: 6; width: 12ch; height: 4; background: blue">BLOBBLOBBLOB</div>card</div><p>after</p>');
        $this->assertSame(['┌────────┐', '│card    │', '│      BL│', '└────────┘', 'after'], array_map(rtrim(...), \array_slice($screen(), 0, 5)));
    }

    public function testAnAbsoluteElementIsCutByAContainingBlockThatClipsNotByItsMargin()
    {
        [, , $screen] = $this->mount('<div style="position: relative; overflow: hidden; width: 10ch; height: 4; border: 1px solid; margin-bottom: 1"><div style="position: absolute; top: 1; left: 6; width: 12ch; height: 6; background: blue">BLOBBLOBBLOB</div>card</div><p>after</p>');
        $this->assertSame(['└────────┘', '', 'after'], array_map(rtrim(...), \array_slice($screen(), 3, 3)));
    }

    public function testAnAbsoluteElementIsCutByABoxThatClipsAroundItsStickyContainingBlock()
    {
        [, , $screen] = $this->mount('<div style="overflow: hidden; width: 10ch; height: 3; border: 1px solid"><div style="position: sticky; top: 0"><div style="position: absolute; top: 0; left: 6; width: 12ch; background: blue">STICKYCLIP</div>x</div></div>');
        $this->assertSame('│x     ST│', rtrim($screen()[1]));
    }

    public function testAnAbsoluteElementIsPlacedInAStickyBoxAtTheBottomAndCutAroundIt()
    {
        [, , $screen] = $this->mount('<div style="overflow: hidden; width: 10ch; height: 3; border: 1px solid"><div style="position: sticky; bottom: 0">x<div style="position: absolute; top: 0; left: 6; width: 12ch">STICKYCLIP</div></div></div>');
        $this->assertSame('│x     ST│', rtrim($screen()[1]));
    }

    public function testAnAbsoluteElementInAFixedOneIsNotCutByABoxThatClipsAroundIt()
    {
        [, , $screen] = $this->mount('<div style="overflow: hidden; height: 2"><div style="position: fixed; top: 0; left: 0; width: 10ch; height: 6"><span style="position: absolute; top: 4; left: 0">BADGE</span></div></div>');
        $this->assertSame('BADGE', rtrim($screen()[4]));
    }

    public function testTheRowOfControlsInALineOfTextAfterAFloatIsMeasuredBesideIt()
    {
        [, , $screen] = $this->mount('<div style="position: relative; height: 4"><div style="position: absolute; top: 0; left: 0; border: 1px solid"><span style="float: left">*</span>Name <input size="4"></div></div>');
        $this->assertStringStartsWith('│* Name', $screen()[1]);
    }

    public function testAnAbsoluteChildBetweenFloatsDoesNotSizeAnAbsoluteElement()
    {
        [, , $screen] = $this->mount('<div style="position: relative; height: 4"><div style="position: absolute; top: 0; left: 0; border: 1px solid"><span style="float: left">ab</span><span style="position: absolute; top: 3; left: 0">long absolute text</span><span style="float: left">cd</span></div></div>');
        $this->assertSame('│ab cd│', rtrim($screen()[1]));
    }

    public function testAStickyElementWithNoInsetIsTheContainingBlockOfTheAbsoluteOnesInIt()
    {
        [, , $screen] = $this->mount('<p>first line here</p><div style="position: sticky"><p>x</p><span style="position: absolute; top: 0; right: 0">B</span></div>');
        $this->assertSame(['first line here', 'x                  B'], array_map(rtrim(...), \array_slice($screen(), 0, 2)));
    }

    public function testAnAbsoluteElementIsNotCutByABoxThatClipsInsideItsContainingBlock()
    {
        [, , $screen] = $this->mount('<div style="position: relative"><div style="overflow: hidden; width: 10ch; height: 3; border: 1px solid"><div style="position: absolute; top: 0; left: 6; width: 12ch; background: blue">ESCAPES</div>card</div></div>');
        $this->assertSame('┌─────ESCAPES', rtrim($screen()[0]));
    }

    public function testRaisedRelativeItemsWrappedPastABarOfASetHeightAreCut()
    {
        [, , $screen] = $this->mount('<div style="height: 1; width: 10ch"><span style="display: inline-block; position: relative; z-index: 1; width: 6ch">aaaaaa</span><span style="display: inline-block; position: relative; z-index: 1; width: 6ch">bbbbbb</span></div><p>after</p>');
        $this->assertSame(['aaaaaa', 'after'], array_map(rtrim(...), \array_slice($screen(), 0, 2)));
    }

    #[DataProvider('provideLayoutsOfShares')]
    public function testAnAbsoluteElementInABoxOfAShareIsInItsContainingBlockInEveryLayout(string $layout, array $expected)
    {
        [, , $screen] = $this->mount('<div style="'.$layout.'"><div style="width: 50%; margin-right: 10%"><p>item</p><div style="position: relative"><span style="position: absolute; top: 0; left: 0">BADGE</span>x</div></div><p>other</p></div>');
        $this->assertSame($expected, array_map(rtrim(...), \array_slice($screen(), 0, \count($expected))));
    }

    public static function provideLayoutsOfShares(): iterable
    {
        yield 'a grid' => ['display: grid; grid-template-columns: 1fr 1fr', ['item      other', 'BADGE']];
        yield 'a column' => ['display: flex; flex-direction: column', ['item', 'BADGE', 'other']];
        yield 'a row' => ['display: flex', ['item      other', 'BADGE']];
    }

    public function testTheMarginsOfAnEmptyBlockCollapseThroughItAndKeepWhatIsAroundApart()
    {
        // Like the spacer <div> of LWN under its fixed menu
        [, , $screen] = $this->mount('<p>before</p><div style="margin-top: 3"></div><p>after</p>');
        $this->assertSame(['before', '', '', '', 'after'], array_map(rtrim(...), \array_slice($screen(), 0, 5)));
        [, , $screen] = $this->mount('<p style="margin-bottom: 1">before</p><div style="margin: 3 0"></div><p style="margin-top: 2">after</p>');
        $this->assertSame(['before', '', '', '', 'after'], array_map(rtrim(...), \array_slice($screen(), 0, 5)), 'The larger of them all.');
        [, , $screen] = $this->mount('<div><p>title</p><details style="margin-top: 1"><summary>Contents</summary><p>x</p></details></div><p style="margin-top: 1">after</p>');
        $this->assertStringContainsString('Contents', implode("\n", $screen()), 'Not the summary of a <details> closed, drawn by it.');
        [, , $screen] = $this->mount('<div style="margin-bottom: 2">before</div><p style="margin: 0 0 2"></p><div>after</div>');
        $this->assertSame(['before', '', '', 'after'], array_map(rtrim(...), \array_slice($screen(), 0, 4)), 'An empty paragraph adds no rows to those it collapses with.');
        [, , $screen] = $this->mount('<div>before</div><div style="margin: 3 0"><div style="margin-top: 3"></div></div><div>after</div>');
        $this->assertSame(['before', '', '', '', 'after'], array_map(rtrim(...), \array_slice($screen(), 0, 5)), 'Through its parent too: those of both, collapsed.');
        [, , $screen] = $this->mount('<div style="display: flex; flex-direction: column"><div>before</div><div style="margin: 1 0"></div><div>after</div></div>');
        $this->assertSame(['before', '', '', 'after'], array_map(rtrim(...), \array_slice($screen(), 0, 4)), 'In a flex column, where margins do not collapse: both.');
    }

    public function testARaisedRelativeElementStaysClippedByItsContainer()
    {
        [, , $screen] = $this->mount('<div style="height: 2; overflow: hidden"><p>one</p><p style="position: relative; top: 2; z-index: 1">moved</p></div><p>after</p>');
        $lines = $screen();
        $this->assertSame('after', rtrim($lines[2]), 'Not drawn over the page past its container.');
        $this->assertStringNotContainsString('moved', implode("\n", $lines));
    }

    public function testARaisedRelativeElementPastTheHeightOfAContainerThatClipsIsCut()
    {
        [, , $screen] = $this->mount('<div style="height: 2; overflow: hidden"><p>a</p><p>b</p><p style="position: relative; z-index: 1">c</p></div><p>after</p>');
        $this->assertSame(['a', 'b', 'after'], array_map(rtrim(...), \array_slice($screen(), 0, 3)));
    }

    public function testWideTextWrittenVerticallyStaysInItsBox()
    {
        $terminal = new VirtualTerminal(10, 6);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build('<p style="writing-mode: vertical-rl; height: 2; width: 6ch">一二三四五六七八九十</p>')->mount($tui);
        $tui->start();
        // Three columns of two cells: no more fit in six, the others are cut
        $tui->processRender();
        $buffer = new ScreenBuffer(10, 6);
        $buffer->write($terminal->consumeOutput());
        $this->assertSame(['五三一', '六四二'], array_map('rtrim', \array_slice(explode("\n", $buffer->getScreen()), 0, 2)));
        $tui->stop();
    }

    public function testANegativeZIndexIsOverTheBackgroundOfItsContextInTheFlow()
    {
        $terminal = new VirtualTerminal(20, 5);
        $tui = new Tui(null, $terminal);
        // Not first on the page: its background is not that of the page
        (new HtmlBuilder())->setDefaultStyles(false)->build('<p>first</p><div style="isolation: isolate; background: #0000ff; height: 3"><p>Text over X</p><div style="position: absolute; top: 1; left: 5; width: 4ch; height: 2; background: #ff0000; z-index: -1"></div></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        // Over the blue of its context, under its text; the blue around it
        $this->assertMatchesRegularExpression('/48;2;255;0;0m(?:\e\[[0-9;]*m)*over/', $terminal->getOutput());
        $this->assertMatchesRegularExpression('/48;2;0;0;255m(?:\e\[[0-9;]*m)* X/', $terminal->getOutput());
        $tui->stop();
    }

    public function testRelativeElementsAreMovedTheirRoomKept()
    {
        [$tui, $view, $screen] = $this->mount('<p>one</p><p id="moved" style="position: relative; top: 1; left: 4">moved</p><p>three three</p>');
        $lines = $screen();
        $this->assertSame('one', rtrim($lines[0]));
        $this->assertSame('', rtrim($lines[1]), 'Its room in the flow is kept, empty.');
        $this->assertSame('thremovedee', rtrim($lines[2]), 'Drawn over what is there, transparent past its text.');
        $this->assertSame([2, 4], [$tui->getWidgetRect($view->getById('moved'))?->row, $tui->getWidgetRect($view->getById('moved'))?->col]);
    }

    public function testHugeInsetsAreBoundedByTheScreen()
    {
        [$tui, $view] = $this->mount('<p>page</p><div id="big" style="position: fixed; top: -9999%; bottom: -9999%; left: -10000; right: -10000; border: 1px solid">huge</div>');
        $rect = $tui->getWidgetRect($view->getById('big'));
        $this->assertNotNull($rect);
        $this->assertLessThanOrEqual(10, $rect->rows);
        $this->assertLessThanOrEqual(20, $rect->columns);
    }

    #[DataProvider('provideHeights')]
    public function testTheContentOfAnAbsoluteBoxIsDrawnPastAShareOfItsContainingBlockButNotPastALength(string $height, array $expected)
    {
        [, , $screen] = $this->mount('<div style="position: relative; height: 4"><div style="position: absolute; top: 0; left: 0; width: 10ch; '.$height.'"><p>one two three four five six</p></div></div>');
        $this->assertSame($expected, array_map(rtrim(...), \array_slice($screen(), 0, 3)));
    }

    public function testTheContentOfAnAbsoluteBoxWithMarginsIsDrawnOverItsBottomBorderAndPastItsHeight()
    {
        [, , $screen] = $this->mount('<div style="position: relative; height: 6"><div style="position: absolute; top: 0; left: 0; width: 14ch; height: 50%; border: 1px solid; margin: 0 0 1 2"><p style="margin: 0 0 2">one two three four</p></div></div><p>after</p>');
        $this->assertSame(['  ┌──────────┐', '  │one two   │', '  └three─four┘', '', '', '', 'after'], array_map(rtrim(...), \array_slice($screen(), 0, 7)), 'The box at its height, like CSS.');
    }

    public function testTheContentOfAnAbsoluteBoxWithMarginsInPercentagesIsDrawnPastItsHeight()
    {
        [, , $screen] = $this->mount('<div style="position: relative; height: 6"><div style="position: absolute; top: 0; left: 0; width: 14ch; height: 50%; border: 1px solid; margin: 0 0 10% 10%"><p style="margin: 0 0 2">one two three four</p></div></div><p>after</p>');
        $this->assertSame(['  ┌──────────┐', '  │one two   │', '  └three─four┘'], array_map(rtrim(...), \array_slice($screen(), 0, 3)));
    }

    public function testTheContentOfAnAbsoluteBoxPastItsHeightIsDrawnWithoutItsBackground()
    {
        $terminal = new VirtualTerminal(20, 10);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="position: relative; height: 4"><div style="position: absolute; top: 0; left: 0; width: 10ch; height: 25%; background: blue"><p>one two three four</p></div></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(20, 10);
        $buffer->write($terminal->consumeOutput());
        $rows = explode("\n", $buffer->getStyledScreen());
        $this->assertStringContainsString('44m', $rows[0], 'Its box in its background.');
        $this->assertStringContainsString('three', $rows[1]);
        $this->assertStringNotContainsString('44m', $rows[1], 'Its content past it without.');
    }

    public function testTheContentOfAnAbsoluteBoxOverItsBottomPaddingIsOnItsBackground()
    {
        $terminal = new VirtualTerminal(20, 10);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="position: relative; height: 4"><div style="position: absolute; top: 0; left: 0; width: 10ch; height: 50%; padding-bottom: 1; background: blue"><p>one two three four</p></div></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(20, 10);
        $buffer->write($terminal->consumeOutput());
        $rows = explode("\n", $buffer->getStyledScreen());
        $this->assertStringContainsString('three', $rows[1]);
        $this->assertStringNotContainsString('49m', $rows[1], 'Its padding still in its box.');
    }

    public function testAnAbsoluteBoxAtTheBottomIsPlacedByItsBoxItsContentGoingOnUnderIt()
    {
        [, , $screen] = $this->mount('<div style="position: relative; height: 6"><p>top</p><div style="position: absolute; bottom: 0; left: 0; width: 10ch; height: 50%; border: 1px solid"><p>one two three four five</p></div></div><p>after</p>');
        $this->assertSame(['top', '', '', '┌────────┐', '│one two │', '└three───┘', 'afour', ' five'], array_map(rtrim(...), \array_slice($screen(), 0, 8)));
    }

    public function testAFloatClearingTheOtherSideStartsANewRowOfTheWidthOfAnAbsoluteElement()
    {
        [, , $screen] = $this->mount('<div style="position: relative; height: 4"><div style="position: absolute; top: 0; left: 0; border: 1px solid"><div style="float: right">A</div><div style="float: left; clear: right">B</div></div></div>');
        $this->assertSame(['┌─┐', '│A│', '│B│', '└─┘'], array_map(rtrim(...), \array_slice($screen(), 0, 4)));
    }

    public function testAFloatClearingOneSideIsMeasuredBesideTheFloatsOfTheOther()
    {
        [, , $screen] = $this->mount('<div style="position: relative; height: 5"><div style="position: absolute; top: 0; left: 0; border: 1px solid"><div style="float: left">aa</div><div style="float: right; height: 2">R</div><div style="float: left; clear: left">bbbb</div></div></div>');
        $this->assertSame(['┌──────┐', '│aa   R│', '│bbbb  │', '└──────┘'], array_map(rtrim(...), \array_slice($screen(), 0, 4)));
    }

    public function testTextAfterAFloatIsMeasuredOnItsLineButNotABlock()
    {
        [, , $screen] = $this->mount('<div style="position: relative; height: 8"><div style="position: absolute; top: 0; left: 0; border: 1px solid"><span style="float: left">[i]</span>Some text</div><div style="position: absolute; top: 4; left: 0; border: 1px solid"><span style="float: left">[i]</span><p>Some text</p></div></div>');
        $this->assertSame(['┌─────────────┐', '│[i] Some text│', '└─────────────┘', '', '┌─────────┐', '│[i] Some │', '│text     │', '└─────────┘'], array_map(rtrim(...), \array_slice($screen(), 0, 8)));
    }

    public function testARelativeElementAfterAnAbsoluteSiblingOfAnAncestorIsDrawnOverIt()
    {
        [, , $screen] = $this->mount('<div style="position: relative"><div style="position: absolute; top: 0; left: 0; width: 10ch; height: 2; background: blue">ABS</div><div><p style="position: relative; left: 2">REL</p></div></div>');
        $this->assertSame('ABREL', rtrim($screen()[0]));
    }

    public function testLiftingARelativeElementPulledUnderAnotherByANegativeMarginChangesNothing()
    {
        $html = '<div><p>Intro</p><div style="position: relative; z-index: 10; top: 1; left: 1; border: 1px solid; width: max-content; margin-bottom: -1">T</div><div style="position: relative; border: 1px solid">code here</div></div>';
        [, , $flow] = $this->mount($html);
        [, , $lifted] = $this->mount('<div style="position: absolute; top: 0; right: 0; width: 1ch; height: 1"></div>'.$html);
        $this->assertSame(array_map(rtrim(...), $flow()), array_map(rtrim(...), $lifted()));
    }

    public function testARelativeElementAfterAnAbsoluteOneInAnEarlierSiblingIsDrawnOverIt()
    {
        [, , $screen] = $this->mount('<div style="position: relative"><div><span style="position: absolute; top: 0; left: 0; background: blue">xxxxxx</span></div><div><p style="position: relative">label</p></div></div>');
        $this->assertSame('labelx', rtrim($screen()[0]));
    }

    public function testARelativeElementInAnAbsoluteOneIsDrawnOverTheAbsoluteOnesBeforeItThere()
    {
        [, , $screen] = $this->mount('<div style="position: relative; height: 3"><span style="position: absolute; top: 0; left: 0; background: blue">xxxxxxxx</span><div style="position: absolute; top: 0; left: 2"><span style="position: absolute; top: 0; left: 0; background: red">yyyy</span><p style="position: relative">ab</p></div></div>');
        $this->assertSame('xxabyyxx', rtrim($screen()[0]));
    }

    public function testLiftingARelativeElementUnderTheNextBoxPulledUpByANegativeMarginChangesNothing()
    {
        $html = '<div><div style="position: relative; background: blue; padding-bottom: 1">A</div></div><div style="margin-top: -1; border-top: 1px solid">B</div>';
        [, , $flow] = $this->mount($html);
        [, , $lifted] = $this->mount('<div style="position: absolute; top: 0; right: 0; width: 1ch; height: 1"></div>'.$html);
        $lines = $lifted();
        $this->assertSame('────────────────────', rtrim($lines[1]));
        $this->assertSame(array_map(rtrim(...), $flow()), array_map(rtrim(...), $lines));
    }

    public function testLiftingRelativeElementsHoldingZeroWidthCharactersChangesNothing()
    {
        // Like the menu of the docs of Node.js, a zero-width space around each entry
        $html = '<div style="display: flex; gap: 1ch"><span style="position: relative">&#8203;a&#8203;</span><span style="position: relative">&#8203;b&#8203;</span><span style="position: relative">&#8203;c</span></div>';
        $firstLine = static function (string $html): string {
            $terminal = new VirtualTerminal(20, 10);
            $tui = new Tui(null, $terminal);
            (new HtmlBuilder())->setDefaultStyles(false)->build($html)->mount($tui);
            $tui->start();
            $tui->processRender();

            return rtrim(explode("\r\n", preg_replace('/\e\[[0-9;:?]*[a-zA-Z]|\e\][^\a\e]*(?:\a|\e\\\\)/', '', $terminal->getOutput()))[0]);
        };
        $this->assertSame("\u{200b}a\u{200b} \u{200b}b\u{200b} \u{200b}c", $firstLine($html));
        $this->assertSame($firstLine($html), $firstLine('<div style="position: absolute; top: 3; right: 0; width: 1ch; height: 1"></div>'.$html));
    }

    public function testAnAbsoluteAfterIsDrawnOverTheRelativeChildrenOfItsElement()
    {
        // Like the caret of a select of rust-lang.org: an ::after is drawn after the children of its element, over them
        $html = '<style>.select { position: relative; display: inline-block } .select::after { content: ""; position: absolute; top: 1; right: 1; width: 0; height: 0; border-top: 1px solid; border-left: 1px solid transparent; border-right: 1px solid transparent } .select select { position: relative; border: 1px solid; background: white; padding: 0 1ch }</style><div class="select"><select><option>English</option></select></div>';
        [, , $flow] = $this->mount($html);
        [, , $lifted] = $this->mount('<div style="position: absolute; top: 5; right: 0; width: 1ch; height: 1"></div>'.$html);
        $lines = $lifted();
        $this->assertSame('│ [ English ▾ ]▾│', rtrim($lines[1]));
        $this->assertSame(array_map(rtrim(...), $flow()), array_map(rtrim(...), $lines));
    }

    #[DataProvider('provideSharedFloats')]
    public function testAnAbsoluteElementInAFloatOfAShareIsInItsContainingBlock(string $float)
    {
        // Like the dates of the news and the events of python.org: the render of the float cached under a key of its own,
        // what it holds is placed again, not left where it was measured
        [$tui, $view, $screen] = $this->mount('<div style="float: left; '.$float.'"><p>News</p><p style="position: relative; padding-left: 4ch">one<span id="date" style="position: absolute; top: 0; left: 0">11</span></p></div>');
        $this->assertSame(['News', '11  one'], array_map(rtrim(...), \array_slice($screen(), 0, 2)));
        $this->assertSame([1, 0, 2, 1], self::rect($tui, $view, 'date'));
    }

    public static function provideSharedFloats(): iterable
    {
        yield 'a width' => ['width: 50%'];
        yield 'a width and a margin' => ['width: 50%; margin-right: 10%'];
    }

    public static function provideHeights(): iterable
    {
        yield 'a share' => ['height: 50%', ['one two', 'three four', 'five six']];
        yield 'a share less a length' => ['height: calc(50% - 1px)', ['one two', 'three four', 'five six']];
        yield 'a length' => ['height: 1em', ['one two']];
        yield 'a greatest length of an auto height' => ['height: auto; max-height: 1em', ['one two']];
        yield 'a length in line heights' => ['height: 1lh', ['one two']];
        yield 'a greatest length of a fit-content height' => ['height: fit-content; max-height: 1em', ['one two']];
    }

    private static function rect(Tui $tui, HtmlView $view, string $id): ?array
    {
        $rect = $tui->getWidgetRect($view->getById($id));

        return null === $rect ? null : [$rect->row, $rect->col, $rect->columns, $rect->rows];
    }

    private function mount(string $html): array
    {
        $terminal = new VirtualTerminal(20, 10);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(20, 10);

        return [$tui, $view, static function () use ($terminal, $buffer): array {
            $buffer->write($terminal->consumeOutput());

            return explode("\n", $buffer->getScreen());
        }];
    }
}
