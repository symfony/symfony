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

final class FlowMarginsTest extends TestCase
{
    public function testClearanceIsStableWhenTheLayoutIsRepeatedWithoutCaches()
    {
        $terminal = new VirtualTerminal(30, 20);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>.f { float: left; width: 5ch; height: 8 } p { margin: 0 } #a { margin-bottom: 3 } #c::before { content: ""; display: block; clear: both } #c p { margin-top: 3 }</style><div class="f">F</div><p id="a">A</p><div id="c"><p>B</p></div>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(30, 20);
        $buffer->write($terminal->consumeOutput());
        $first = $buffer->getScreen();
        foreach ($view->querySelectorAll('*') as $widget) {
            $widget->invalidate();
        }
        $tui->requestRender();
        $tui->processRender();
        $buffer->write($terminal->consumeOutput());
        $this->assertSame($first, $buffer->getScreen());
        $tui->stop();
    }

    #[DataProvider('emptyBlocks')]
    public function testAnEmptyBlockJoinsItsNeighboursMarginGroup(string $empty, int $gap)
    {
        $terminal = new VirtualTerminal(20, 20);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>p { margin: 0 } #a { margin-bottom: 3lh } #empty { margin: 1lh 0 } #b { margin-top: 3lh }</style><p id="a">A</p>'.$empty.'<p id="b">B</p>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(20, 20);
        $buffer->write($terminal->getOutput());
        $tui->stop();
        $this->assertSame('A'.str_repeat("\n", $gap + 1).'B', $buffer->getScreen());
    }

    public function testAnEmptyMarginGroupBesideAFloat()
    {
        $terminal = new VirtualTerminal(20, 12);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>p { margin: 0 } #f { float: left; width: 3ch; height: 8 } #a { margin-bottom: 3 } #empty { margin: 1 0 } #b { margin-top: 3 }</style><div id="f">F</div><p id="a">A</p><div id="empty"></div><p id="b">B</p>');
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(20, 12);
        $buffer->write($terminal->getOutput());
        $lines = explode("\n", $buffer->getScreen());
        $this->assertSame(4, array_find_key($lines, static fn (string $line) => str_contains($line, 'B')));
        $this->assertSame('F   A', $lines[0]);
        $tui->stop();
    }

    #[DataProvider('formattingContexts')]
    public function testTheMarginsOfAFormattingContextOfItsOwnDoNotCollapseWithThoseOfItsChildren(string $html, int $row)
    {
        $lines = $this->screen('<style>p { margin: 0 }</style>'.$html, 20, 10);
        $this->assertSame($row, array_find_key($lines, static fn (string $line) => str_contains($line, 'B')));
    }

    public static function formattingContexts(): iterable
    {
        yield 'a block, collapsed' => ['<p>A</p><div style="margin-top: 1"><p style="margin-top: 2">B</p></div>', 3];
        yield 'overflow: hidden' => ['<p>A</p><div style="margin-top: 1; overflow: hidden"><p style="margin-top: 2">B</p></div>', 4];
        yield 'display: flow-root' => ['<p>A</p><div style="margin-top: 1; display: flow-root"><p style="margin-top: 2">B</p></div>', 4];
        yield 'display: flow-root, at the bottom' => ['<div style="display: flow-root; margin-bottom: 1"><p style="margin-bottom: 2">A</p></div><p>B</p>', 4];
        yield 'an inline-block' => ['<div style="display: inline-block; margin-top: 1"><p style="margin-top: 2">B</p></div>', 3];
        yield 'a float' => ['<div style="float: left; margin-top: 1"><p style="margin-top: 2">B</p></div>', 3];
        yield 'an absolute box' => ['<div style="position: absolute; top: 0; margin-top: 1"><p style="margin-top: 2">B</p></div>', 3];
        yield 'a flex item' => ['<div style="display: flex"><div style="margin-top: 1"><p style="margin-top: 2">B</p></div></div>', 3];
        yield 'a grid item' => ['<div style="display: grid"><div style="margin-top: 1"><p style="margin-top: 2">B</p></div></div>', 3];
    }

    public function testAFloatFirstOrLastInABlockDoesNotCollapseItsMarginsAway()
    {
        $lines = array_map('rtrim', $this->screen('<style>p { margin: 0 }</style><p>A</p><div style="margin-top: 1"><div style="float: left; margin-top: 3">F</div><p>text</p></div><p>C</p>', 20, 10));
        $this->assertSame(['A', '', '  text', '  C', '', 'F'], \array_slice($lines, 0, 6), 'The text under the margin of the block, beside the margin of the float.');
        $lines = array_map('rtrim', $this->screen('<style>p { margin: 0 }</style><p style="margin-bottom: 2">A</p><div><div style="float: left; margin-top: 3">F</div><p>text</p></div>', 20, 10));
        $this->assertSame(['A', '', '', '  text'], \array_slice($lines, 0, 4), 'Under the margin of the paragraph before, not that of the float.');
        $lines = array_map('rtrim', $this->screen('<style>p { margin: 0 }</style><div style="margin-bottom: 1"><p>A</p><div style="float: left; height: 2; margin-bottom: 3">F</div></div><p>C</p>', 20, 10));
        $this->assertSame('A', $lines[0]);
    }

    #[DataProvider('floatsBeforeTheFirstBlock')]
    public function testAFloatBeforeTheFirstBlockIsUnderTheMarginsItCollapsesWith(string $html, array $expected)
    {
        $this->assertSame($expected, array_map('rtrim', \array_slice($this->screen('<style>p { margin: 0 }</style>'.$html, 20, 10), 0, \count($expected))));
    }

    public static function floatsBeforeTheFirstBlock(): iterable
    {
        yield 'collapsed with the block' => ['<p>A</p><div style="margin-top: 1"><span style="float: right">F</span><p style="margin-top: 2">B</p></div><p>C</p>', ['A', '', '', 'B                  F', 'C']];
        yield 'with a block of none' => ['<p>A</p><div><span style="float: right">F</span><p style="margin-top: 2">B</p></div>', ['A', '', '', 'B                  F']];
        yield 'in a formatting context of its own' => ['<div style="display: flow-root"><span style="float: right">F</span><p style="margin-top: 2">B</p></div>', ['                   F', '', 'B']];
        yield 'with a border' => ['<div style="border-top: 1px solid"><span style="float: right">F</span><p style="margin-top: 2">B</p></div>', ['────────────────────', '                   F', '', 'B']];
        yield 'that clears' => ['<p>A</p><div style="margin-top: 1"><span style="float: right">F</span><p style="margin-top: 2; clear: both">B</p></div>', ['A', '', '                   F', '', 'B']];
    }

    public function testTheMarginOfAChildCollapsedThroughItsParentIsOutsideTheBoxOfItsParent()
    {
        $lines = array_map('rtrim', $this->screen('<style>p { margin: 0 }</style><p>A</p><div style="border-left: 1px solid; padding-left: 1ch"><p style="margin: 1 0">x</p></div><p>B</p>', 20, 10));
        $this->assertSame(['A', '', '│ x', '', 'B'], \array_slice($lines, 0, 5), 'Its border beside the content only, its margins above and below it.');
        $lines = array_map('rtrim', $this->screen('<style>p { margin: 0 }</style><p>A</p><div style="border-left: 1px solid; margin-top: 1"><p style="margin-top: 3">x</p></div>', 20, 10));
        $this->assertSame(['A', '', '', '', '│x'], \array_slice($lines, 0, 5), 'The larger margin, outside.');
    }

    public static function emptyBlocks(): iterable
    {
        yield ['<div id="empty"></div>', 3];
        yield ['<div id="empty"></div><div style="margin: 2lh 0"></div>', 3];
        yield ['<div id="empty"><div style="margin: 2lh 0"></div></div>', 3];
        yield ['<div style="margin: 5lh 0"></div>', 5];
    }

    /**
     * @return list<string>
     */
    private function screen(string $html, int $columns, int $rows): array
    {
        $terminal = new VirtualTerminal($columns, $rows);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer($columns, $rows);
        $buffer->write($terminal->consumeOutput());
        $tui->stop();

        return explode("\n", $buffer->getScreen());
    }
}
