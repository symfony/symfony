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

/**
 * The content of a block in the flow past a height it sets, like CSS overflow: visible.
 */
final class BlockOverflowTest extends TestCase
{
    private const PAGE = '<style>p { margin: 0 } .b { height: 2; border: 1px solid; width: 20ch; box-sizing: content-box; %s }</style><div class="b"><p>one</p><p>two</p><p>three overflowing</p><p>four overflowing</p></div><p>after</p><p>second</p>';

    public function testTheContentGoesOnPastTheBoxUnderWhatFollows()
    {
        $this->assertSame([
            '┌────────────────────┐',
            '│one                 │',
            '│two                 │',
            '└three─overflowing───┘',
            'after overflowing',
            'second',
        ], $this->screen(\sprintf(self::PAGE, '')), 'Over its bottom border, then under the text after it, painted later, like CSS.');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideLayoutsDrawingPastTheirHeight(): iterable
    {
        yield 'a flex column' => ['display: flex; flex-direction: column'];
        yield 'a flex column of items at its start' => ['display: flex; flex-direction: column; justify-content: space-between'];
        yield 'a grid' => ['display: grid'];
        yield 'a grid of rows sharing its height, as tall as their content' => ['display: grid; grid-template-rows: repeat(4, 1fr)'];
    }

    #[DataProvider('provideLayoutsDrawingPastTheirHeight')]
    public function testTheContentOfALayoutGoesOnPastTheBox(string $declarations)
    {
        $this->assertSame($this->screen(\sprintf(self::PAGE, '')), $this->screen(\sprintf(self::PAGE, $declarations)), 'Like a block.');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideItems(): iterable
    {
        yield 'an item of a flex row' => ['display: flex', ''];
        yield 'an item of a flex column' => ['display: flex; flex-direction: column', ''];
        yield 'an item of a grid' => ['display: grid', ''];
        yield 'an inline-block' => ['', 'display: inline-block'];
    }

    #[DataProvider('provideItems')]
    public function testTheContentOfAnItemGoesOnPastIt(string $container, string $declarations)
    {
        $page = str_replace(['<div class="b">', '</div><p>after'], ['<div style="'.$container.'"><div class="b">', '</div></div><p>after'], self::PAGE);
        $this->assertSame($this->screen(\sprintf(self::PAGE, '')), $this->screen(\sprintf($page, $declarations)), 'Like a block.');
    }

    public function testTheContentOfAnItemGoesOnUnderTheItemsAfterIt()
    {
        $this->assertSame(['one', 'nextoverflowing', 'after'], $this->screen('<style>p { margin: 0 }</style><div style="display: flex; flex-direction: column"><div style="height: 1"><p>one</p><p>two overflowing</p></div><p>next</p></div><p>after</p>'));
    }

    public function testTheContentOfAnItemGoesOnPastTheRowItIsTallerThan()
    {
        // The row of one row, the item of two, its content of three
        $this->assertSame(['one   x', 'two', 'three'], $this->screen('<style>p { margin: 0 }</style><div style="display: flex; height: 1; column-gap: 1ch"><div style="height: 2"><p>one</p><p>two</p><p>three</p></div><div>x</div></div>'));
    }

    public function testTheContentOfAnItemGoesOnPastItInTheFramesAfter()
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>p { margin: 0 } .red { color: red }</style><div style="display: flex; column-gap: 1ch"><div style="height: 1"><p>one</p><p>two</p></div><p id="next">next</p></div>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        // The item drawn from its render cache, its row laid out again
        $view->getById('next')->addStyleClass('red');
        $tui->requestRender();
        $tui->processRender();
        $tui->stop();
        $screen = new ScreenBuffer(40, 10);
        $screen->write($terminal->getOutput());

        $this->assertSame(['one next', 'two'], \array_slice(array_map('rtrim', explode("\n", $screen->getScreen())), 0, 2));
    }

    public function testAGrowingItemOfAFlexColumnIsCut()
    {
        // CSS grows it past its height, which the layout keeps as its most
        $this->assertSame(['one', 'x', 'after'], $this->screen('<style>p { margin: 0 }</style><div style="display: flex; flex-direction: column; height: 4"><div style="height: 1; flex: 1"><p>one</p><p>two</p></div><p>x</p></div><p>after</p>'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideClippingBoxes(): iterable
    {
        yield 'overflow: hidden' => ['overflow: hidden'];
        yield 'overflow: clip' => ['overflow: clip'];
        yield 'paint containment' => ['contain: paint'];
        yield 'a height in pixels, rounded to rows' => ['height: 32px'];
        yield 'a height of a custom property in ems, rounded to rows' => ['--height: 2em; height: var(--height)'];
        // CSS draws the content of these above their top, which is not drawn
        yield 'a flex column of items at its end' => ['display: flex; flex-direction: column; justify-content: flex-end'];
        yield 'a flex column of items at its center' => ['display: flex; flex-direction: column; justify-content: center'];
        yield 'a grid of rows at its end' => ['display: grid; align-content: end'];
    }

    #[DataProvider('provideClippingBoxes')]
    public function testTheContentIsCutAtABoxThatClips(string $declarations)
    {
        $this->assertSame([
            '┌────────────────────┐',
            '│one                 │',
            '│two                 │',
            '└────────────────────┘',
            'after',
            'second',
        ], $this->screen(\sprintf(self::PAGE, $declarations)));
    }

    public function testAnItemOfASetHeightIsNotStretched()
    {
        // Like CSS, whose align-self: stretch stretches an item of height: auto only
        $this->assertSame(['a b', 'a ─', 'a'], $this->screen('<div style="display: flex; column-gap: 1ch"><div>a<br>a<br>a</div><div style="height: 1; border-bottom: 1px solid; box-sizing: content-box">b</div></div>'));
        $this->assertSame(['a b', 'a ─', 'a'], $this->screen('<div style="display: grid; grid-template-columns: 1ch 1ch; column-gap: 1ch"><div>a<br>a<br>a</div><div style="height: 1; border-bottom: 1px solid; box-sizing: content-box">b</div></div>'));
    }

    public function testAHeightInLinesIsInRows()
    {
        // A line is a row: 2lh is exact, not rounded like pixels, its content drawn past it
        $this->assertSame($this->screen(\sprintf(self::PAGE, '')), $this->screen(\sprintf(self::PAGE, 'height: 2lh')));
    }

    public function testAShareOfARoundedHeightIsRounded()
    {
        // Like height: 100% in a bar of 3rem, whose content a browser fits in its pixels
        $this->assertSame(['one', 'two', 'after'], $this->screen('<style>p { margin: 0 }</style><div style="height: 2em"><div style="height: 100%"><p>one</p><p>two</p><p>three overflowing</p></div></div><p>after</p>'));
    }

    public function testABoxItsContentFitsIsNotMeasuredAgain()
    {
        // An empty bar of a set height, like a sticky one: measuring it would resolve the styles of the page again
        $this->assertSame(['┌────────────────────┐', '│                    │', '│                    │', '└────────────────────┘', 'after'], \array_slice($this->screen('<style>p { margin: 0 } .b { height: 2; border: 1px solid; width: 20ch; box-sizing: content-box }</style><div class="b"></div><p>after</p>'), 0, 5));
    }

    /**
     * @return list<string>
     */
    private function screen(string $html): array
    {
        $terminal = new VirtualTerminal(40, 10);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $tui->stop();
        $screen = new ScreenBuffer(40, 10);
        $screen->write($terminal->getOutput());

        return array_values(array_filter(array_map('rtrim', explode("\n", $screen->getScreen())), static fn (string $line): bool => '' !== $line));
    }
}
