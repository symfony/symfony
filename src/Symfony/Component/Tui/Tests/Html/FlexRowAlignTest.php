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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

final class FlexRowAlignTest extends TestCase
{
    public function testAnEmptyFlexChildHasNoContentHeight()
    {
        $terminal = new VirtualTerminal(20, 6);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="display: flex; height: 3"><div style="display: flex"></div><div>text</div></div>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertStringContainsString('text', $terminal->getOutput());
        $tui->stop();
    }

    public function testTransparentInlineBlockBordersAlignWithPaintedSiblings()
    {
        $terminal = new VirtualTerminal(20, 6);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>.row { display: flex } .row p { display: inline-block; border: 1px solid } #clear { border-color: transparent }</style><div class="row"><p>A</p><p id="clear">B</p></div>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(20, 6);
        $buffer->write($terminal->getOutput());
        $tui->stop();

        $this->assertSame('│A│ B', rtrim(explode("\n", $buffer->getScreen())[1]));
    }

    public function testAnImageAlternativeKeepsTheMinimumWidthOfWrappedText()
    {
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="display: flex"><img id="image" alt="one two three"></div>');
        $renderer = new Renderer();
        $this->assertSame(6, $renderer->measureMinContentWidth($view->getById('image'), 8, 10));
    }

    public function testAnImageAlternativeKeepsItsFirstLinesInAFixedRow()
    {
        $terminal = new VirtualTerminal(20, 6);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="display: flex; align-items: center; height: 2"><div style="display: flex; align-items: center; width: 3ch; height: 2"><img alt="Image alternative" width="24"></div><p>X</p></div>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(20, 6);
        $buffer->write($terminal->getOutput());
        $this->assertStringContainsString('[Im', $buffer->getScreen());
        $tui->stop();
    }

    public function testRoundedImageRowsDoNotHideTheFirstRowOfAnIcon()
    {
        $terminal = new VirtualTerminal(20, 6);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="display: flex; align-items: center; height: 1"><svg width="8" height="32" viewBox="0 0 8 32"><rect width="8" height="16" fill="#ff0000"/><rect y="16" width="8" height="16" fill="#0000ff"/></svg><p>X</p></div>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertStringContainsString('255;0;0', $terminal->getOutput());
        $tui->stop();
    }

    public function testCenteredOverflowIsPaintedAboveAndBelowTheRow()
    {
        $terminal = new VirtualTerminal(20, 10);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="padding-top: 3"><div style="display: flex; align-items: center; height: 1"><div>a<br>b<br>c<br>d<br>e</div><div>X</div></div></div>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(20, 10);
        $buffer->write($terminal->getOutput());
        $this->assertSame(['', 'a', 'b', 'cX', 'd', 'e'], explode("\n", $buffer->getScreen()));
        $tui->stop();
    }

    public function testWrappedTextOverflowsBothEdgesOfACenteredRow()
    {
        $terminal = new VirtualTerminal(20, 10);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="padding-top: 3"><div style="display: flex; align-items: center; height: 1"><div style="width: 1ch">a b c d e</div><div>X</div></div></div>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(20, 10);
        $buffer->write($terminal->getOutput());
        $this->assertSame(['', 'a', 'b', 'cX', 'd', 'e'], explode("\n", $buffer->getScreen()));
        $tui->stop();
    }

    public function testVisibleFlexOverflowKeepsTheFilterOfItsAncestor()
    {
        $terminal = new VirtualTerminal(20, 10);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="padding-top: 3; filter: grayscale(1)"><div style="display: flex; align-items: center; height: 1"><div style="color: red">a<br>b<br>c<br>d<br>e</div><div>X</div></div></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(20, 10);
        $buffer->write($terminal->getOutput());
        $this->assertStringContainsString('a', $buffer->getScreen());
        $this->assertStringNotContainsString("\e[31m", $buffer->getStyledScreen());
        $tui->stop();
    }

    public function testVisibleFlexOverflowTakesThePointerAndClippedOverflowDoesNot()
    {
        $terminal = new VirtualTerminal(20, 10);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>p { margin: 0 } .clipped { overflow: hidden }</style><p>previous<br>previous<br>previous</p><div id="row" style="display: flex; align-items: center; height: 1"><a id="tall" href="/next">a<br>b<br>c<br>d<br>e</a><div>X</div></div><p id="after">after</p>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $this->assertSame($view->getById('tall'), $view->widgetAt(1, 0));
        $this->assertSame($view->getById('after'), $view->widgetAt(4, 0));
        $this->assertSame($view->getById('tall'), $view->widgetAt(5, 0));
        $view->getById('row')->addStyleClass('clipped');
        $tui->requestRender();
        $tui->processRender();
        $this->assertNotSame($view->getById('tall'), $view->widgetAt(1, 0));
        $this->assertNotSame($view->getById('tall'), $view->widgetAt(5, 0));
        $tui->stop();
    }

    #[DataProvider('clippingBoxes')]
    public function testFlexOverflowStopsAtAClippingAncestor(string $outer, string $rowStyle, array $expected)
    {
        $terminal = new VirtualTerminal(20, 10);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="'.$outer.'"><div style="padding-top: 3"><div style="display: flex; align-items: center; height: 1; '.$rowStyle.'"><div>a<br>b<br>c<br>d<br>e</div><div>X</div></div></div></div>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(20, 10);
        $buffer->write($terminal->getOutput());
        $this->assertSame($expected, explode("\n", $buffer->getScreen()));
        $tui->stop();
    }

    public static function clippingBoxes(): iterable
    {
        yield ['overflow: hidden; height: 4', '', ['', 'a', 'b', 'cX']];
        yield ['', 'overflow: hidden', ['', '', '', 'cX']];
        yield ['overflow: auto; height: 4; scrollbar-width: none', '', ['', 'a', 'b', 'cX']];
    }

    public function testOverflowKeepsTheFlowPaintOrderAndDropsStaleCachedLines()
    {
        $terminal = new VirtualTerminal(20, 10);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>p { margin: 0 }</style><p>previous<br>previous<br>previous</p><div style="display: flex; align-items: center; height: 1"><p id="tall">a<br>b<br>c<br>d<br>e</p><div>X</div></div><p>after</p>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(20, 10);
        $buffer->write($terminal->consumeOutput());
        $this->assertSame(['previous', 'arevious', 'brevious', 'cX', 'after', 'e'], explode("\n", $buffer->getScreen()));
        $tui->requestRender();
        $tui->processRender();
        $buffer->write($terminal->consumeOutput());
        $this->assertSame(['previous', 'arevious', 'brevious', 'cX', 'after', 'e'], explode("\n", $buffer->getScreen()));
        $view->getById('tall')->setText('z');
        $tui->requestRender();
        $tui->processRender();
        $buffer->write($terminal->consumeOutput());
        $this->assertSame(['previous', 'previous', 'previous', 'zX', 'after'], explode("\n", $buffer->getScreen()));
        $this->assertEmpty($tui->getWidgetRect($view->getRoot())->fragments);
        $this->assertNull($view->widgetAt(5, 0));
        $tui->stop();
    }

    #[DataProvider('provideAlignments')]
    public function testAnItemTallerThanARowOfASetHeightOverflowsItAsAligned(string $align, string $row)
    {
        $terminal = new VirtualTerminal(20, 6);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="display: flex; align-items: '.$align.'; height: 3em; border: 1px solid"><div id="tall">a<br>b<br>c<br>d<br>e</div><div>x</div></div><p>after</p>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(20, 6);
        $buffer->write($terminal->consumeOutput());

        $this->assertSame($row, rtrim(explode("\n", $buffer->getScreen())[1]));
        $tui->stop();
    }

    public static function provideAlignments(): iterable
    {
        yield 'centered, over both sides' => ['center', '│cx                │'];
        yield 'at the start' => ['flex-start', '│ax                │'];
        yield 'at the end, over the top' => ['flex-end', '│ex                │'];
    }
}
