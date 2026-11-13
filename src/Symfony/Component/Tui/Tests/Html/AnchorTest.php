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

final class AnchorTest extends TestCase
{
    public function testElementsArePlacedNextToTheirAnchor()
    {
        [$tui, $rect] = $this->mount('<style>#a { anchor-name: --a; margin-left: 10; max-width: 8ch } .below { position: fixed; position-anchor: --a; position-area: bottom span-right }
            .above { position: absolute; position-anchor: --a; position-area: top center } .side { position: fixed; position-anchor: --a; top: anchor(top); left: anchor(right) }</style>
            <p>first line</p><p id="a">anchor</p><p id="below" class="below">below</p><p id="above" class="above">up</p><p id="side" class="side">beside</p>');
        // The box of the anchor, past its margin and at its width: row 1, columns 10 to 17
        $this->assertSame([2, 10], \array_slice($rect('below'), 0, 2), 'bottom span-right: under it, from its left.');
        $this->assertSame([0, 13], \array_slice($rect('above'), 0, 2), 'top center.');
        $this->assertSame([1, 18], \array_slice($rect('side'), 0, 2), 'anchor() in the insets: right of it.');
        $tui->stop();
    }

    public function testAnchorCenter()
    {
        [$tui, $rect] = $this->mount('<style>#a { anchor-name: --a; margin-left: 10; max-width: 8ch } .tip { position: fixed; position-anchor: --a }
            .below { top: anchor(bottom); justify-self: anchor-center } .side { left: anchor(right); align-self: anchor-center } .both { justify-self: anchor-center; align-self: anchor-center }</style>
            <p>first line</p><p id="a">anchor</p><p id="below" class="tip below">tip</p><p id="side" class="tip side">s</p><p id="both" class="tip both">ab</p>');
        $this->assertSame([2, 12], \array_slice($rect('below'), 0, 2), 'Under it, centered across it.');
        $this->assertSame([1, 18], \array_slice($rect('side'), 0, 2), 'Right of it, on its row.');
        $this->assertSame([1, 13], \array_slice($rect('both'), 0, 2), 'Over its middle.');
        $tui->stop();
    }

    public function testAnchorInTheInsetShorthands()
    {
        [$tui, $rect] = $this->mount('<style>#a { anchor-name: --a; margin-left: 10; max-width: 8ch } .tip { position: fixed; position-anchor: --a }
            .inset { inset: anchor(bottom) auto auto anchor(left) } .logical { inset-block: anchor(top) auto; inset-inline: anchor(right) auto }</style>
            <p>first line</p><p id="a">anchor</p><p id="inset" class="tip inset">under</p><p id="logical" class="tip logical">beside</p>');
        $this->assertSame([2, 10], \array_slice($rect('inset'), 0, 2), 'inset: under it, from its left.');
        $this->assertSame([1, 18], \array_slice($rect('logical'), 0, 2), 'inset-block and inset-inline: right of it.');
        $tui->stop();
    }

    public function testAnAnchorSizedHeightDoesNotClipVisibleOverflow()
    {
        [$tui, $rect] = $this->mount('<style>#a { anchor-name: --a; height: 1 } #tip { position: absolute; position-anchor: --a; top: 2; left: 0; height: anchor-size(height) }</style><div id="a">anchor</div><div id="tip"><div id="first">first</div><div id="last">last</div></div>', 8);
        $this->assertSame(1, $rect('tip')[3]);
        $this->assertSame(3, $rect('last')[0]);
        $buffer = new ScreenBuffer(40, 8);
        $buffer->write($tui->getTerminal()->getOutput());
        $this->assertStringContainsString('last', $buffer->getScreen());
        $tui->stop();
    }

    public function testAnchorSize()
    {
        [$tui, $rect] = $this->mount('<style>#a { anchor-name: --a; max-width: 8ch } #b { anchor-name: --b; max-width: 20ch }
            .same { position: fixed; position-anchor: --a; position-area: bottom span-right; width: anchor-size(width) }
            .wider { position: fixed; position-anchor: --a; top: 5; left: 0; width: calc(anchor-size(width) * 2 + 1ch) }
            .named { position: fixed; position-anchor: --a; top: 6; left: 0; width: anchor-size(--b inline); min-height: calc(anchor-size(height) + 2) }
            .least { position: fixed; position-anchor: --a; top: 9; left: 0; min-width: anchor-size(width) }
            .fallback { position: fixed; position-anchor: --none; top: 10; left: 0; width: anchor-size(width, 3ch) }</style>
            <p id="a">anchor</p><p id="b">second anchor</p><p id="same" class="same">same width as the anchor</p><p id="wider" class="wider">x</p>
            <p id="named" class="named">y</p><p id="least" class="least">a long text</p><p id="fallback" class="fallback">no anchor</p>', 14);

        $this->assertSame(8, $rect('same')[2], 'The width of the anchor.');
        $this->assertSame(17, $rect('wider')[2], 'In a math function.');
        $this->assertSame([20, 3], \array_slice($rect('named'), 2), 'Of the anchor named, the height of the default one.');
        $this->assertSame(11, $rect('least')[2], 'A least width: the content is wider.');
        $this->assertSame(3, $rect('fallback')[2], 'Without the anchor, the fallback.');
        $tui->stop();
    }

    public function testItFlipsWhenItWouldLeaveTheScreen()
    {
        [$tui, $rect] = $this->mount('<style>#a { anchor-name: --a } .tip { position: fixed; position-anchor: --a; top: anchor(bottom); position-try-fallbacks: flip-block }</style>
            <p>1</p><p>2</p><p>3</p><p>4</p><p>5</p><p id="a">at the bottom</p><div id="tip" class="tip">tip</div>', 6);

        $this->assertSame(4, $rect('tip')[0], 'Above it: below would leave the screen.');
        $tui->stop();
    }

    public function testPositionTryRulesAreTriedInOrder()
    {
        [$tui, $rect] = $this->mount('<style>#a { anchor-name: --a; margin-left: 30 }
            @position-try --nowhere { position-area: right }
            @position-try --left-of { inset: auto; top: anchor(top); right: anchor(left) }
            @position-try --above { position-area: top span-right }
            .named { position: fixed; position-anchor: --a; position-area: bottom span-right; position-try-fallbacks: --missing, --nowhere, --left-of, --above }
            .area { position: fixed; position-anchor: --a; top: anchor(bottom); left: anchor(left); position-try-fallbacks: top center }
            .none { position: fixed; position-anchor: --a; position-area: bottom; position-try: --above-but-missing }</style>
            <p>1</p><p>2</p><p>3</p><p>4</p><p>5</p><p id="a">anchor</p><div id="named" class="named">a menu</div><div id="area" class="area">tip</div><div id="none" class="none">kept</div>', 6);

        // The anchor is on the last row, 5, columns 30 to 39, of a 40x6 screen: below it does not fit
        $this->assertSame([5, 24], \array_slice($rect('named'), 0, 2), 'Right of it does not fit either: left of it, its right edge at the left of the anchor.');
        $this->assertSame([4, 33], \array_slice($rect('area'), 0, 2), 'A position-area value: above it, centered on its box, columns 30 to 39.');
        $this->assertSame(5, $rect('none')[0], 'No try that fits: its own place, kept on the screen.');
        $tui->stop();
    }

    public function testATryOfItsOwnSize()
    {
        // Too wide to fit left of the anchor at its own width; the try is narrower, as wide as the anchor
        [$tui, $rect] = $this->mount('<style>#a { anchor-name: --a; margin-left: 12; width: 10ch }
            @position-try --narrow-left { position-area: left; width: anchor-size(width) }
            @position-try --short-left { position-area: left; width: 8ch; height: 1 }
            .wide { position: fixed; position-anchor: --a; position-area: right; width: 30ch; position-try-fallbacks: --narrow-left }
            .tall { position: fixed; position-anchor: --a; position-area: right; width: 30ch; position-try-fallbacks: --short-left }</style>
            <p id="a">anchor</p><div id="wide" class="wide">a menu that wraps at its width</div><div id="tall" class="tall">one two three four</div>');

        $this->assertSame([0, 2, 10], \array_slice($rect('wide'), 0, 3), 'Left of the anchor, as wide as it, from column 2 to 12.');
        $this->assertSame([0, 4, 8, 1], $rect('tall'), 'Its width and height in cells.');
        $tui->stop();
    }

    public function testTheInvokerOfAPopoverIsItsAnchor()
    {
        $html = '<style>[popover] { position-area: bottom span-right; margin: 0 }</style><p>header</p><div style="padding-left: 8"><button id="b" popovertarget="m">Tools</button></div><p>text</p><div popover id="m"><p>One</p></div>';
        $tui = new Tui(terminal: new VirtualTerminal(40, 12));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();

        $view->getById('b')->activate();
        $tui->processRender();
        $button = $tui->getWidgetRect($view->getById('b'));
        $menu = $tui->getWidgetRect($view->getById('m'));
        $this->assertSame([2, 8], [$menu?->row, $menu?->col], 'Under its button, without naming it.');

        $view->hidePopover($view->getById('m'));
        $tui->setImplicitAnchor($view->getById('m'), null);
        $view->showPopover($view->getById('m'));
        $tui->processRender();
        $this->assertSame([0, 40], [$tui->getWidgetRect($view->getById('m'))?->col, $tui->getWidgetRect($view->getById('m'))?->columns], 'Without an anchor, position-area does nothing: its insets (0, margin 0) fill the screen.');
        $tui->stop();
        $this->assertSame(1, $button?->row);
    }

    /**
     * @return array{Tui, \Closure(string): array{int, int, int, int}}
     */
    private function mount(string $html, int $rows = 12): array
    {
        $tui = new Tui(terminal: new VirtualTerminal(40, $rows));
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();

        return [$tui, static function (string $id) use ($tui, $view): array {
            $rect = $tui->getWidgetRect($view->getById($id));

            return null === $rect ? [-1, -1, -1, -1] : [$rect->row, $rect->col, $rect->columns, $rect->rows];
        }];
    }
}
