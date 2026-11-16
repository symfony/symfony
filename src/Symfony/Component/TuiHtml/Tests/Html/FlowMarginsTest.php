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

    /**
     * Two lines first, A on the third (row 2), its box ending at row 3: B is at row 3 plus the margins collapsed between
     * them, the largest positive one plus the most negative one, like CSS 2.1 §8.3.1.
     */
    #[DataProvider('adjoiningMargins')]
    public function testAdjoiningMarginsCollapseToTheLargestPositiveOnePlusTheMostNegativeOne(string $html, int $row, string $float = '')
    {
        // B on the right, A showing beside it when B is pulled over it
        $lines = $this->screen('<style>p { margin: 0 } .b { text-align: right } .c { text-align: center } .f { float: left; width: 1ch } .r { float: right; width: 1ch }</style>'.$float.'<p>X</p><p>Y</p>'.preg_replace('/<p([^>]*)>B<\/p>/', '<p$1 class="b">B</p>', $html), 20, 12);
        $this->assertSame(2, array_find_key($lines, static fn (string $line) => str_contains($line, 'A')), 'A where it was.');
        $this->assertSame($row, array_find_key($lines, static fn (string $line) => str_contains($line, 'B')));
    }

    public static function adjoiningMargins(): iterable
    {
        foreach (self::adjoiningMarginCases() as $name => $case) {
            yield $name => $case;
            // Laid out around floats: one beside the first line, one beside all of them, on either side
            yield $name.', after a float' => [...$case, '<div class="f">F</div>'];
            yield $name.', beside a float' => [...$case, '<div class="f" style="height: 12em">F</div>'];
            yield $name.', after a right float' => [...$case, '<div class="r">F</div>'];
            yield $name.', beside a right float' => [...$case, '<div class="r" style="height: 12em">F</div>'];
        }
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    private static function adjoiningMarginCases(): iterable
    {
        // Siblings
        yield 'positive, the larger' => ['<p style="margin-bottom: 3em">A</p><p style="margin-top: 1em">B</p>', 6];
        yield 'negative as large as the positive' => ['<p style="margin-bottom: 1em">A</p><p style="margin-top: -1em">B</p>', 3];
        yield 'negative larger than the positive' => ['<p style="margin-bottom: 1em">A</p><p style="margin-top: -2em">B</p>', 2];
        yield 'negative smaller than the positive' => ['<p style="margin-bottom: 3em">A</p><p style="margin-top: -1em">B</p>', 5];
        yield 'negative first, positive after' => ['<p style="margin-bottom: -1em">A</p><p style="margin-top: 3em">B</p>', 5];
        yield 'negative first, as large' => ['<p style="margin-bottom: -2em">A</p><p style="margin-top: 2em">B</p>', 3];
        yield 'negative alone' => ['<p>A</p><p style="margin-top: -1em">B</p>', 2];
        yield 'all negative, the most negative' => ['<p style="margin-bottom: -1em">A</p><p style="margin-top: -2em">B</p>', 1];
        yield 'all negative, the most negative first' => ['<p style="margin-bottom: -2em">A</p><p style="margin-top: -1em">B</p>', 1];
        // Parent and first child
        yield 'parent positive, first child as negative' => ['<p>A</p><div style="margin-top: 1em"><p style="margin-top: -1em">B</p></div>', 3];
        yield 'parent negative, first child positive' => ['<p>A</p><div style="margin-top: -1em"><p style="margin-top: 3em">B</p></div>', 5];
        yield 'sibling, parent and first child' => ['<p style="margin-bottom: 1em">A</p><div style="margin-top: 2em"><p style="margin-top: -3em">B</p></div>', 2];
        yield 'two levels of first children' => ['<p style="margin-bottom: 2em">A</p><div><div style="margin-top: -1em"><p style="margin-top: 1em">B</p></div></div>', 4];
        yield 'parent and first child all negative' => ['<p>A</p><div style="margin-top: -1em"><p style="margin-top: -2em">B</p></div>', 1];
        // Parent and last child
        yield 'last child negative, parent positive' => ['<div style="margin-bottom: 2em"><p style="margin-bottom: -1em">A</p></div><p>B</p>', 4];
        yield 'last child positive, parent negative' => ['<div style="margin-bottom: -1em"><p style="margin-bottom: 3em">A</p></div><p>B</p>', 5];
        yield 'last child, parent and sibling' => ['<div style="margin-bottom: -1em"><p style="margin-bottom: 1em">A</p></div><p style="margin-top: 2em">B</p>', 4];
        yield 'last child and parent all negative' => ['<div style="margin-bottom: -1em"><p style="margin-bottom: -2em">A</p></div><p>B</p>', 1];
        // Through an empty block
        yield 'through an empty block, negative' => ['<p style="margin-bottom: 1em">A</p><div style="margin: -2em 0 0"></div><p>B</p>', 2];
        yield 'through an empty block, positive and negative' => ['<p style="margin-bottom: 2em">A</p><div style="margin: -1em 0"></div><p style="margin-top: 1em">B</p>', 4];
        yield 'through an empty block, negative then positive' => ['<p>A</p><div style="margin: -2em 0 1em"></div><p>B</p>', 2];
        yield 'a block pulled up, the next one after it' => ['<p>A</p><p style="margin-top: -3em">P</p><p>B</p>', 1];
        // A block of one line pulled a row, all of it over the rows before it: no empty block, its pull not carried on
        yield 'two blocks pulled up in turn' => ['<p>A</p><div class="c" style="margin-top: -1em">P</div><div class="c" style="margin-top: -1em">Q</div><p>B</p>', 3];
        // Its bottom margin, in the rows over those before it, collapsing with the next one
        yield 'a block pulled up, its bottom margin collapsing with the next one' => ['<p>A</p><p class="c" style="margin: -3em 0 1em">P</p><p style="margin-top: 1em">B</p>', 2];
        yield 'through an empty block, all negative' => ['<p style="margin-bottom: -1em">A</p><div style="margin: 0 0 -2em"></div><p>B</p>', 1];
        // Blockers: the margins of the parent and of its child apart
        yield 'a border between parent and child' => ['<p style="margin-bottom: 1em">A</p><div style="margin-top: -1em; border-top: 1px solid"><p style="margin-top: 2em">B</p></div>', 6];
        yield 'padding between parent and child' => ['<p style="margin-bottom: 1em">A</p><div style="margin-top: -1em; padding-top: 1em"><p style="margin-top: 2em">B</p></div>', 6];
        yield 'a formatting context of its own' => ['<p style="margin-bottom: 1em">A</p><div style="margin-top: -1em; overflow: hidden"><p style="margin-top: 2em">B</p></div>', 5];
        yield 'display: flow-root, child negative' => ['<p>A</p><div style="margin-top: 1em; display: flow-root"><p style="margin-top: -1em">B</p></div>', 3];
        yield 'a border between parent and last child' => ['<div style="margin-bottom: 2em; border-bottom: 1px solid"><p style="margin-bottom: -1em">A</p></div><p>B</p>', 5];
        yield 'a child pulled over the border of its parent' => ['<p>A</p><div style="border-top: 1px solid"><p style="margin-top: -1em">B</p></div>', 3];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('pulledIntoTheTopBorder')]
    public function testTheFirstRowOfABoxIsDrawnOverItsTopBorderOnlyWhenItsFirstChildDrawsIt(string $html, array $expected)
    {
        $lines = array_map('rtrim', $this->screen('<style>p { margin: 0 } .pull { margin-top: -1lh }</style>'.$html, 20, 10));
        $this->assertSame($expected, \array_slice($lines, 0, \count($expected)));
    }

    public static function pulledIntoTheTopBorder(): iterable
    {
        yield 'its corners kept' => ['<div style="border: 1px solid"><p class="pull">blk</p></div>', ['┌blk───────────────┐', '└──────────────────┘']];
        yield 'on a background' => ['<div style="border: 1px solid; background: blue"><p class="pull">blk</p></div>', ['┌blk───────────────┐', '└──────────────────┘']];
        yield 'under a box over its top border' => ['<div style="border: 1px solid">top</div><div style="border: 1px solid; margin-top: -1px"><p class="pull">blk</p></div>', ['┌──────────────────┐', '│top               │', '├blk───────────────┤', '└──────────────────┘']];
        // Its child pulled over the line of the ::before instead, see testAFirstChildPulledUpIsDrawnOverTheBeforeOfItsParent()
        yield 'not a ::before, which draws that row' => ['<style>.d::before { content: "BEFORE"; display: block }</style><div class="d" style="border: 1px solid"><p class="pull">blk</p></div>', ['┌──────────────────┐', '│blkORE            │']];
        yield 'not the caption of a table, drawn out of its box' => ['<table style="border: 1px solid; border-spacing: 0"><caption style="margin-top: -1px; border: 1px solid">a b</caption><tr><td style="padding: 0">x</td></tr></table>', ['┌───┐', '│a b│', '└───┘', '┌───┐', '│x  │', '└───┘']];
        yield 'not scrolled' => ['<div style="border: 1px solid; height: 3lh; overflow: auto"><p class="pull">p1</p><p>p2</p><p>p3</p><p>p4</p><p>p5</p></div>', ['┌──────────────────┐']];
        yield 'in a flex column' => ['<div style="display: flex; flex-direction: column; border: 1px solid"><p class="pull">One</p><p>Two</p></div>', ['┌One───────────────┐', '│Two               │', '└──────────────────┘']];
        yield 'a table cell' => ['<table style="border-spacing: 0"><tr><td style="border: 1px solid; padding: 0"><p class="pull">One</p></td></tr></table>', ['┌One┐', '└───┘']];
        // Every item of a row draws its first row, not only the one pulled: the row not drawn over its border
        yield 'not in a flex row' => ['<div style="display: flex; border: 1px solid"><p class="pull" style="border: 1px solid">One</p><p>Two</p></div>', ['┌──────────────────┐', '│┌───┐Two          │', '││One│             │']];
        // Nor in a grid, whose first row is of the cells of a row
        yield 'not in a grid' => ['<div style="display: grid; border: 1px solid"><p class="pull">One</p></div>', ['┌──────────────────┐', '│One               │']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('pulledIntoTheBottomBorder')]
    public function testTheLastRowOfABoxIsDrawnOverItsBottomBorderOnlyWhenItsLastChildDrawsIt(string $html, array $expected)
    {
        $lines = array_map('rtrim', $this->screen('<style>p { margin: 0 } .pull { margin-bottom: -1lh }</style>'.$html, 20, 10));
        $this->assertSame($expected, \array_slice($lines, 0, \count($expected)));
    }

    public static function pulledIntoTheBottomBorder(): iterable
    {
        yield 'a block, its corners kept' => ['<div style="border: 1px solid"><p class="pull">blk</p></div>', ['┌──────────────────┐', '└blk───────────────┘']];
        yield 'a tab of a row' => ['<div style="display: flex; border-bottom: 1px solid"><p class="pull" style="border: 1px solid">One</p><p>Two</p></div>', ['┌───┐Two', '│One│', '└───┴───────────────']];
        // The ::after drawn over its last row instead, see testAFirstChildPulledUpIsDrawnOverTheBeforeOfItsParent()
        yield 'not an ::after, which draws that row' => ['<style>.d::after { content: "AFTER"; display: block }</style><div class="d" style="border: 1px solid"><p class="pull">blk</p></div>', ['┌──────────────────┐', '│AFTER             │', '└──────────────────┘']];
        yield 'not scrolled' => ['<div style="border: 1px solid; height: 3lh; overflow: auto"><p>p1</p><p>p2</p><p>p3</p><p>p4</p><p class="pull">p5</p></div>', ['┌──────────────────┐', '│p1               ┃│', '└──────────────────┘']];
        yield 'not in columns' => ['<div style="border: 1px solid; columns: 2"><p>p1</p><p>p2</p><p>p3</p><p class="pull">p4</p></div>', ['┌──────────────────┐', '│p1        p3      │', '│p2        p4      │', '└──────────────────┘']];
        // Nor a float last, which is not pulled
        yield 'not a float' => ['<div style="border: 1px solid"><p>A</p><span style="float: right; margin-bottom: -1lh">F</span></div><p>N</p>', ['┌──────────────────┐', '│A                 │', '└─────────────────F┘', 'N']];
        yield 'not the caption of a table, drawn out of its box' => ['<table style="border: 1px solid; border-spacing: 0"><tr><td style="padding: 0">x</td></tr><caption style="caption-side: bottom; margin-bottom: -1px; border: 1px solid">cap</caption></table>', ['┌─────┐', '│x    │', '└─────┘', '┌─────┐', '│ cap │', '└─────┘']];
    }

    public function testTheBottomBorderALastRowIsDrawnOverKeepsItsCornersAndColors()
    {
        $cells = $this->cells('<div style="border: 1px solid; background: blue; margin: 0 4ch 0 0"><p style="margin: 0 0 -1lh">blk</p></div>', 3);
        $this->assertSame(['└', '┘'], [$cells[1][0]['char'], $cells[1][15]['char']], 'Its corners kept.');
        $this->assertSame(range(0, 15), array_keys(array_filter($cells[1], static fn (array $cell): bool => self::hasSgr($cell['style'], '44'))), 'On its background, not on its right margin.');
        // A bottom border hidden in the color of the background: its corners in the color of the sides shown above them,
        // like CSS splits a corner between its sides
        $cells = $this->cells('<div style="border: 1px solid red; border-bottom-color: white; background: white"><p>one</p><p style="margin: 0 0 -1lh">blk</p></div>', 4);
        $this->assertSame([['│', true], ['│', true]], [[$cells[2][0]['char'], self::hasSgr($cells[2][0]['style'], '31')], [$cells[2][19]['char'], self::hasSgr($cells[2][19]['style'], '31')]]);
    }

    public function testTheTopBorderAFirstRowIsDrawnOverKeepsTheColorsOfTheBox()
    {
        $terminal = new VirtualTerminal(20, 4);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build('<div style="border: 1px solid; background: blue; margin: 0 4ch 0 0"><p style="margin: -1lh 0 0">blk</p></div>')->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(20, 4);
        $buffer->write($terminal->consumeOutput());
        $blue = static fn (array $row): array => array_keys(array_filter($row, static fn (array $cell): bool => self::hasSgr($cell['style'], '44')));
        $this->assertSame(range(0, 15), $blue($buffer->getCells()[0]), 'Not on its right margin.');
        $this->assertSame(range(0, 15), $blue($buffer->getCells()[1]));
        $tui->stop();
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('pulledOverABefore')]
    public function testAFirstChildPulledUpIsDrawnOverTheBeforeOfItsParent(string $html, array $expected)
    {
        $lines = array_map('rtrim', $this->screen('<style>p { margin: 0 } .d::before { content: "BEF"; display: block } .c { text-align: right }</style>'.$html, 20, 10));
        $this->assertSame($expected, \array_slice($lines, 0, \count($expected)));
    }

    public static function pulledOverABefore(): iterable
    {
        // A float first, which is not pulled over it: the block after it at its place
        yield 'not a float' => ['<p>A</p><div class="d"><span style="float: right; margin-top: -1lh">F</span><p>text here</p></div>', ['A', 'BEF', 'text here          F']];
        // Its ::after over the last rows of its last child, the same way
        yield 'an ::after over its last child' => ['<style>.a::after { content: "AFT"; display: block }</style><div class="a"><p>A</p><p style="margin-bottom: -1lh">B</p></div><p>N</p>', ['A', 'AFT', 'N']];
        // A ::before that clears floats, pulled over all the same
        yield 'a ::before that clears' => ['<style>.k::before { content: "BEF"; display: block; clear: both }</style><div style="float: left; width: 2ch; height: 2">F</div><div class="k"><p style="margin-top: -1lh">C</p></div>', ['F', '', 'CEF']];
        // Over its line, like over a sibling, the next one after it; pulled further, not out of its parent
        foreach ([1, 2] as $rows) {
            $html = '<p>A</p><p>B</p><div class="d"><p class="c" style="margin-top: -'.$rows.'lh">C</p><p>D</p></div>';
            yield "by $rows" => [$html, ['A', 'B', 'BEF                C', 'D']];
            yield "by $rows, beside a float" => ['<div style="float: left; width: 1ch; height: 6">F</div>'.$html, ['F A', '  B', '  BEF              C', '  D']];
        }
    }

    #[DataProvider('floatSides')]
    public function testAFloatAfterABlockPulledUpOverAllItsRows(string $side)
    {
        $lines = array_map('rtrim', $this->screen('<p>A0</p><p style="margin: 0 0 -1lh">A1</p>B<div style="float: '.$side.'">R</div><p>C</p>', 20, 10));
        $this->assertSame(1, array_find_key($lines, static fn (string $line) => str_starts_with($line, 'B')), 'B over A1.');
        $this->assertSame(2, array_find_key($lines, static fn (string $line) => str_contains($line, 'C')));
        $this->assertStringContainsString('R', $lines[2], 'The float beside C.');
    }

    public static function floatSides(): iterable
    {
        yield ['left'];
        yield ['right'];
    }

    /**
     * @param array<int, string> $rows The id of the element the pointer finds at the second column of each row, its
     *                                 text drawn on that row but for its border rows, uppercased
     */
    #[DataProvider('pulledIntoABorder')]
    public function testTheRectOfABlockPulledIntoTheBorderOfItsParentIsWhereItIsDrawn(string $html, array $rows)
    {
        $terminal = new VirtualTerminal(20, 10);
        $tui = new Tui(null, $terminal);
        $view = (new HtmlBuilder())->setDefaultStyles(false)->build('<style>p { margin: 0 }</style>'.$html);
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(20, 10);
        $buffer->write($terminal->consumeOutput());
        $lines = explode("\n", $buffer->getScreen());
        foreach ($rows as $row => $id) {
            if (strtolower($id) === $id) {
                $this->assertStringContainsString(strtoupper($id), $lines[$row], 'Drawn on that row.');
            }
            $id = strtolower($id);
            $this->assertSame($id, $view->widgetAt($row, 2)?->getId(), \sprintf('The pointer on row %d.', $row));
        }
        $tui->stop();
    }

    public static function pulledIntoABorder(): iterable
    {
        yield 'its first child over its top border' => ['<p id="a">A</p><div style="border: 1px solid"><p id="b" style="margin-top: -1em">B</p><p id="c">C</p></div>', [0 => 'a', 1 => 'b', 2 => 'c']];
        yield 'its last child over its bottom border' => ['<div style="border-bottom: 1px solid"><p id="a">A</p><p id="b" style="margin-bottom: -1em">B</p></div><p id="c">C</p>', [0 => 'a', 1 => 'b', 2 => 'c']];
        // Its content cut to no row: nothing drawn over its top border, what it holds not on that row
        yield 'its first child cut away' => ['<div id="d" style="border: 1px solid; max-height: 0"><p id="b" style="margin-top: -1em">B</p></div><p id="c">C</p>', [0 => 'D', 2 => 'c']];
        yield 'a tab over the bottom border of the row' => ['<div style="display: flex; border-bottom: 1px solid"><p id="b" style="margin-bottom: -1em; border: 1px solid; border-bottom-color: transparent">B</p></div><p id="c">C</p>', [0 => 'B', 1 => 'b', 2 => 'B', 3 => 'c']];
    }

    public static function emptyBlocks(): iterable
    {
        yield ['<div id="empty"></div>', 3];
        yield ['<div id="empty"></div><div style="margin: 2lh 0"></div>', 3];
        yield ['<div id="empty"><div style="margin: 2lh 0"></div></div>', 3];
        yield ['<div style="margin: 5lh 0"></div>', 5];
    }

    /**
     * Whether a cell's style has an SGR code, like 44 for a blue background: its codes compared one by one, past the
     * parameters of extended colors, like the 5 and 44 of 38;5;44.
     */
    private static function hasSgr(string $style, string $code): bool
    {
        preg_match_all('/\e\[([0-9;:]*)m/', $style, $sequences);
        $parameters = explode(';', implode(';', $sequences[1]));
        for ($i = 0, $count = \count($parameters); $i < $count; ++$i) {
            if ($code === $parameters[$i]) {
                return true;
            }
            if (\in_array($parameters[$i], ['38', '48', '58'], true)) {
                $i += '5' === ($parameters[$i + 1] ?? null) ? 2 : 4;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<int, array{char: string, style: string}>>
     */
    private function cells(string $html, int $rows): array
    {
        $terminal = new VirtualTerminal(20, $rows);
        $tui = new Tui(null, $terminal);
        (new HtmlBuilder())->setDefaultStyles(false)->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $buffer = new ScreenBuffer(20, $rows);
        $buffer->write($terminal->consumeOutput());
        $tui->stop();

        return $buffer->getCells();
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
