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
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Widget\RichTextWidget;

/**
 * What the renderer keeps from frame to frame so that large pages draw fast follows what changes.
 */
final class LargePageTest extends TestCase
{
    public function testTheMarginOfANestedBoxFollowsTheBackgroundOfAnAncestor()
    {
        [$tui, $terminal, $view] = $this->render('<style>.outer { background: #ff0000 } .outer:hover { background: #0000ff }</style><div class="outer"><div><div style="margin-left: 2ch; padding-left: 1ch">x</div></div></div>');
        $this->assertStringContainsString('48;2;255;0;0', $terminal->consumeOutput());
        $view->querySelector('.outer')->setState('hover');
        $tui->requestRender();
        $tui->processRender();
        $output = $terminal->consumeOutput();
        $this->assertStringContainsString('48;2;0;0;255', $output);
        $this->assertStringNotContainsString('48;2;255;0;0', $output);
    }

    public function testTheItemsOfAListAreNumberedAgainOnEachCascade()
    {
        $view = (new HtmlBuilder())->build('<ol reversed><li>a</li><li value="10">b</li><li>c</li></ol><ol start="5"><li>d</li><li>e</li></ol>')->computeStyles();
        $markers = static fn () => array_map(static fn (RichTextWidget $item) => $item->getMarker(), $view->querySelectorAll('li'));
        $this->assertSame(['3. ', '10. ', '9. ', '5. ', '6. '], $markers());
        // Another cascade, a state changed: the lists are counted again, the same
        $view->querySelector('li')->setState('hover');
        $view->computeStyles();
        $this->assertSame(['3. ', '10. ', '9. ', '5. ', '6. '], $markers());
    }

    public function testTheColumnsOfATableFollowACellWhoseTextChanged()
    {
        [$tui, $terminal, $view] = $this->render('<table><tr><td id="a">a</td><td>b</td></tr><tr><td>c</td><td>d</td></tr></table>', 40);
        $terminal->consumeOutput();
        $view->getById('a')->all()[0]->setText('a wider cell');
        $tui->requestRender();
        $tui->processRender();
        $buffer = new ScreenBuffer(40, 5);
        $buffer->write($terminal->consumeOutput());
        $this->assertMatchesRegularExpression('/^a wider cell +b *$/m', $buffer->getScreen());
        $this->assertMatchesRegularExpression('/^c {12,}d *$/m', $buffer->getScreen());
    }

    public function testAnAbsoluteBoxThatFittedItsHeightIsDrawnPastItOnceItsTextGrows()
    {
        [$tui, $terminal, $view] = $this->render('<div style="position: relative; height: 4"><div style="position: absolute; top: 0; left: 0; width: 10ch; height: 50%"><p id="p" style="margin: 0">short</p></div></div><p style="margin: 0">after</p>', 20, 8);
        $buffer = new ScreenBuffer(20, 8);
        $buffer->write($terminal->consumeOutput());
        $this->assertStringNotContainsString('three', $buffer->getScreen());
        $view->getById('p')->setText('one two three four five six');
        $tui->requestRender();
        $tui->processRender();
        $buffer->write($terminal->consumeOutput());
        $this->assertMatchesRegularExpression('/^five six/m', $buffer->getScreen());
    }

    public function testTheRowsOfASubgridFollowTheTextOfAnotherItemOfItsGrid()
    {
        [$tui, $terminal, $view] = $this->render('<style>.g { display: grid; grid-template-columns: 1fr 1fr; column-gap: 2ch } .g > div { display: grid; grid-row: span 2; grid-template-rows: subgrid } h4, p { margin: 0 }</style><div class="g"><div><h4>a</h4><p>text a</p></div><div><h4 id="b">a title that wraps</h4><p id="c">text b</p></div></div>', 20);
        $terminal->consumeOutput();
        // As tall as before, its rows shared another way
        $view->getById('b')->setText('b');
        $view->getById('c')->setText('a text that wraps');
        $tui->requestRender();
        $tui->processRender();
        // The text of the first item right below the titles, one row tall now
        $buffer = new ScreenBuffer(20, 5);
        $buffer->write($terminal->consumeOutput());
        $this->assertSame(['a          b', 'text a     a text', '           that', '           wraps'], \array_slice(array_map(rtrim(...), explode("\n", $buffer->getScreen())), 0, 4));
    }

    public function testTheRowsOfANarrowBoxArePaintedEachInTheColorsOfItsCells()
    {
        [, $terminal] = $this->render('<div style="width: 4ch; height: 6; background: linear-gradient(to bottom, #ff0000, #0000ff)"></div><div style="width: 4ch; height: 6; background: radial-gradient(circle closest-side, #ffff00 90%, #000080)"></div>', 20, 12);
        $buffer = new ScreenBuffer(20, 12);
        $buffer->write($terminal->getOutput());
        $rows = explode("\n", $buffer->getStyledScreen());
        // Painted again for a row of other colors, not taken from a row painted before: six rows, six colors
        $this->assertCount(6, array_unique(\array_slice($rows, 0, 6)));
        // The rows past the circle the same, those across it in its colors
        $this->assertSame($rows[6], $rows[11]);
        $this->assertNotSame($rows[6], $rows[8]);
    }

    public function testTheRowsOfAHorizontalGradientArePaintedOnlyWhereItsImageIs()
    {
        [, $terminal] = $this->render('<div style="height: 6; background: linear-gradient(to right, #ff0000, #0000ff) no-repeat; background-size: 100% 2">x</div><p>after</p>', 20, 10);
        $buffer = new ScreenBuffer(20, 10);
        $buffer->write($terminal->getOutput());
        $rows = explode("\n", $buffer->getStyledScreen());
        // The two rows of the image are the same, the text aside; below it, the color of the box only, not the gradient
        $this->assertStringContainsString('48;2;235;0;20m', $rows[0]);
        $this->assertSame(substr($rows[0], strpos($rows[0], 'x') + 1), substr($rows[1], strpos($rows[0], 'x') + 1));
        foreach ([2, 3, 4, 5] as $row) {
            $this->assertStringNotContainsString('48;2;235;0;20m', $rows[$row]);
        }
    }

    public function testTheRowsOfLayersArePaintedByTheRowsOfEachLayer()
    {
        [, $terminal] = $this->render('<div style="height: 6; background: linear-gradient(to right, #ff000080, #0000ff80) no-repeat 0 0 / 100% 2, linear-gradient(to right, #00ff00, #ffff00)">x</div><p>after</p>', 20, 10);
        $buffer = new ScreenBuffer(20, 10);
        $buffer->write($terminal->getOutput());
        $rows = explode("\n", $buffer->getStyledScreen());
        // The second row under the translucent layer, then the rows of the bottom layer alone
        $this->assertNotSame($rows[1], $rows[2]);
        $this->assertSame($rows[2], $rows[3]);
        $this->assertSame($rows[2], $rows[5]);
    }

    #[DataProvider('provideStateChanges')]
    public function testAStateChangedDrawsLikeAPageDrawnWithIt(string $html, string $selector, string $state = 'hover')
    {
        [$tui, $terminal, $view] = $this->render($html, 40, 12);
        $view->querySelector($selector)->setState($state);
        $tui->requestRender();
        $tui->processRender();
        $changed = new ScreenBuffer(40, 12);
        $changed->write($terminal->getOutput());
        $terminal = new VirtualTerminal(40, 12);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->build($html);
        $view->querySelector($selector)->setState($state);
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $fresh = new ScreenBuffer(40, 12);
        $fresh->write($terminal->getOutput());
        $this->assertSame($fresh->getStyledScreen(), $changed->getStyledScreen());
    }

    public static function provideStateChanges(): iterable
    {
        yield 'The children of the element inherit its new color' => ['<style>div:hover { color: red } p { margin: 0 }</style><div><p>a</p><section><p>b</p></section></div><p>c</p>', 'div'];
        yield 'A parent styled by :has()' => ['<style>div:has(p:hover) { background: blue } p { margin: 0 }</style><div><p>a</p><p>b</p></div><p>c</p>', 'p'];
        yield 'The following siblings of the element' => ['<style>.a:hover ~ p { color: red } .a:hover + p { font-weight: bold } p { margin: 0 }</style><p class="a">a</p><p>b</p><p>c</p>', '.a'];
        yield 'The counters of the elements after it' => ['<style>body { counter-reset: n } p { margin: 0; counter-increment: n } p:hover { counter-increment: n 10 } p::before { content: counter(n) ". " }</style><p>a</p><p>b</p><div><p>c</p></div>', 'p'];
        yield 'The numbers of the items after it' => ['<style>li:hover { display: none }</style><ol><li>a</li><li>b</li><li>c</li></ol>', 'li'];
        yield 'An element hidden, and what it holds' => ['<style>div:hover p { display: none } p { margin: 0 }</style><div><p>a</p></div><p>b</p>', 'div'];
        yield 'Its focus, shown by its form' => ['<style>form:focus-within { border: 1px solid } </style><form><input aria-label="x"></form>', 'input', 'focus'];
        yield 'A cell of a table' => ['<style>td:hover { background: blue; padding: 0 2ch }</style><table><tr><td>a</td><td>b</td></tr><tr><td>c</td><td>d</td></tr></table>', 'td'];
        yield 'A text beside a float, its parent now a grid' => ['<style>.f { float: left; width: 8ch; height: 4 } div:hover { display: grid; grid-template-columns: 6ch 1fr } p { margin: 0 }</style><div class="f">F</div><div><p>one two three four five six seven</p><p>b</p></div>', 'div:not(.f)'];
        yield 'A text beside a float, its parent now a row' => ['<style>.f { float: left; width: 8ch; height: 4 } div:hover { display: flex } p { margin: 0 }</style><div class="f">F</div><div><p>one two three four five six seven</p><p>b</p></div>', 'div:not(.f)'];
        yield 'A text beside a float, its parent now a table' => ['<style>.f { float: left; width: 8ch; height: 4 } div:hover { display: table } p { margin: 0 }</style><div class="f">F</div><div><p>one two three four five six seven</p></div>', 'div:not(.f)'];
        yield 'A text beside a float, its parent now out of the flow' => ['<style>.f { float: left; width: 8ch; height: 4 } section { position: relative } div:hover { position: absolute; top: 0; left: 0 } p { margin: 0 }</style><section><div class="f">F</div><div><p>one two three four five six seven</p></div></section>', 'div:not(.f)'];
        yield 'A text beside a float, its parent now in columns' => ['<style>.f { float: left; width: 8ch; height: 4 } div:hover { columns: 2 } p { margin: 0 }</style><div class="f">F</div><div><p>one two three four five six seven</p></div>', 'div:not(.f)'];
        yield 'A text beside a float before a select restyled as it is drawn' => ['<style>.f { float: left; width: 8ch; height: 4 } p { margin: 0 } p:hover { color: red }</style><div class="f">F</div><p>one two three four five six seven</p><select><option>a</option><option>bb</option></select><p>after</p>', 'p'];
        yield 'Nothing styled by the state' => ['<style>p { margin: 0 }</style><p>a</p><p>b</p>', 'p'];
    }

    private function render(string $html, int $columns = 20, int $rows = 5): array
    {
        $terminal = new VirtualTerminal($columns, $rows);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->build($html);
        $view->mount($tui);
        $tui->start();
        $tui->processRender();

        return [$tui, $terminal, $view];
    }
}
