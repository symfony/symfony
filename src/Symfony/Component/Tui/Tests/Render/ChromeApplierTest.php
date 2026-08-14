<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Render;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\ArrayLineBuffer;
use Symfony\Component\Tui\Render\ChromeApplier;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Render\WidgetRendererInterface;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\GradientDirection;
use Symfony\Component\Tui\Style\Padding;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\TextAlign;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class ChromeApplierTest extends TestCase
{
    // ---------------------------------------------------------------
    // computeInnerDimensions
    // ---------------------------------------------------------------

    /**
     * @param array{int, int} $expected
     */
    #[DataProvider('innerDimensionsProvider')]
    public function testComputeInnerDimensions(int $columns, int $rows, Style $style, array $expected)
    {
        $applier = $this->createApplier();

        $this->assertSame($expected, $applier->computeInnerDimensions($columns, $rows, $style));
    }

    /**
     * @return iterable<string, array{int, int, Style, array{int, int}}>
     */
    public static function innerDimensionsProvider(): iterable
    {
        yield 'no chrome' => [40, 10, new Style(), [40, 10]];
        yield 'padding only' => [40, 10, new Style(padding: new Padding(1, 2, 1, 2)), [36, 8]];
        yield 'border only' => [40, 10, new Style(border: Border::all(1, 'none')), [38, 8]];
        yield 'border and padding' => [40, 10, new Style(padding: Padding::all(1), border: Border::all(1, 'none')), [36, 6]];
        yield 'asymmetric padding' => [40, 10, new Style(padding: new Padding(2, 3, 1, 5)), [32, 7]];
        yield 'clamps to 1' => [4, 4, new Style(padding: Padding::all(3)), [1, 1]];
    }

    // ---------------------------------------------------------------
    // computeChromeOffset
    // ---------------------------------------------------------------

    /**
     * @param array{int, int} $expected
     */
    #[DataProvider('chromeOffsetProvider')]
    public function testComputeChromeOffset(Style $style, array $expected)
    {
        $applier = $this->createApplier();

        $this->assertSame($expected, $applier->computeChromeOffset($style));
    }

    /**
     * @return iterable<string, array{Style, array{int, int}}>
     */
    public static function chromeOffsetProvider(): iterable
    {
        yield 'no chrome' => [new Style(), [0, 0]];
        yield 'padding only' => [new Style(padding: new Padding(2, 0, 0, 3)), [2, 3]];
        yield 'border only' => [new Style(border: Border::all(1, 'none')), [1, 1]];
        yield 'border and padding' => [new Style(padding: new Padding(1, 0, 0, 2), border: Border::all(1, 'none')), [2, 3]];
    }

    // ---------------------------------------------------------------
    // computeInnerContext
    // ---------------------------------------------------------------

    public function testComputeInnerContextReducesDimensions()
    {
        $applier = $this->createApplier();
        $context = new RenderContext(40, 10);
        $style = new Style(padding: Padding::all(1), border: Border::all(1, 'none'));

        $inner = $applier->computeInnerContext($context, $style);

        $this->assertSame(36, $inner->getColumns());
        $this->assertSame(6, $inner->getRows());
    }

    public function testComputeInnerContextStripsLayoutProperties()
    {
        $applier = $this->createApplier();
        $style = new Style(
            padding: Padding::all(1),
            border: Border::all(1, 'none'),
            textAlign: TextAlign::Center,
            bold: true,
        );
        $context = new RenderContext(40, 10, $style);

        $inner = $applier->computeInnerContext($context, $style);

        // Layout properties stripped from the context style
        $innerStyle = $inner->getStyle();
        $this->assertNull($innerStyle->getPadding());
        $this->assertNull($innerStyle->getBorder());
        $this->assertNull($innerStyle->getTextAlign());
        // Visual properties preserved
        $this->assertTrue($innerStyle->getBold());
    }

    // ---------------------------------------------------------------
    // apply: passthrough
    // ---------------------------------------------------------------

    public function testApplyPassesThroughPlainStyleWithNoChromeOrAlign()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        $lines = new ArrayLineBuffer(['Hello', 'World']);

        $result = $applier->apply($lines, 20, new Style(), $widget);

        $this->assertSame($lines, $result);
    }

    public function testApplyEmptyLinesWithNoChromeReturnsEmpty()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');

        $result = $applier->apply(new ArrayLineBuffer([]), 20, new Style(), $widget)->toArray();

        $this->assertSame([], $result);
    }

    // ---------------------------------------------------------------
    // apply: padding
    // ---------------------------------------------------------------

    public function testApplyWithVerticalPadding()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        $style = new Style(padding: new Padding(1, 0, 1, 0));

        $result = $applier->apply(new ArrayLineBuffer(['Content']), 20, $style, $widget)->toArray();

        // 1 top padding + 1 content + 1 bottom padding = 3 lines
        $this->assertCount(3, $result);
        // Top and bottom padding lines should be spaces
        $this->assertSame(20, AnsiUtils::visibleWidth($result[0]));
        $this->assertSame(20, AnsiUtils::visibleWidth($result[2]));
        // Content line should contain the text
        $this->assertStringContainsString('Content', $result[1]);
    }

    public function testApplyWithHorizontalPadding()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        $style = new Style(padding: new Padding(0, 3, 0, 5));

        $result = $applier->apply(new ArrayLineBuffer(['Hi']), 20, $style, $widget)->toArray();

        $this->assertCount(1, $result);
        // The content line should be padded to full width
        $this->assertSame(20, AnsiUtils::visibleWidth($result[0]));
        // Left padding: 5 spaces before content
        $plain = AnsiUtils::stripAnsiCodes($result[0]);
        $this->assertStringStartsWith('     Hi', $plain);
    }

    public function testApplyEmptyLinesWithVerticalPaddingProducesOutput()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        $style = new Style(padding: new Padding(2, 0, 1, 0));

        $result = $applier->apply(new ArrayLineBuffer([]), 20, $style, $widget)->toArray();

        // 2 top padding + 1 bottom padding = 3 lines (even with no content)
        $this->assertCount(3, $result);
    }

    // ---------------------------------------------------------------
    // apply: border
    // ---------------------------------------------------------------

    public function testApplyWithBorder()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        $style = new Style(border: Border::all(1, 'none'));

        $result = $applier->apply(new ArrayLineBuffer(['Hello']), 20, $style, $widget)->toArray();

        // 1 border-top + 1 content + 1 border-bottom = 3 lines
        $this->assertCount(3, $result);
        // All lines should respect width
        foreach ($result as $line) {
            $this->assertSame(20, AnsiUtils::visibleWidth($line));
        }
    }

    public function testApplyEmptyLinesWithBorderProducesBorderBox()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        $style = new Style(border: Border::all(1, 'none'));

        $result = $applier->apply(new ArrayLineBuffer([]), 20, $style, $widget)->toArray();

        // 1 border-top + 1 border-bottom = 2 lines
        $this->assertCount(2, $result);
    }

    #[DataProvider('borderWiderThanBoxProvider')]
    public function testApplyClampsBorderToTheAvailableWidth(int $width, Style $style)
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');

        $result = $applier->apply(new ArrayLineBuffer(['Hello']), $width, $style, $widget)->toArray();

        foreach ($result as $line) {
            $this->assertSame($width, AnsiUtils::visibleWidth($line));
        }
    }

    /**
     * @return iterable<string, array{int, Style}>
     */
    public static function borderWiderThanBoxProvider(): iterable
    {
        yield 'border leaves no room for content' => [2, new Style(border: Border::all(1, 'none'))];
        yield 'border wider than the box' => [1, new Style(border: Border::all(1, 'none'))];
        yield 'thick border wider than the box' => [3, new Style(border: Border::all(2, 'none'))];
        yield 'border and padding wider than the box' => [2, new Style(padding: Padding::all(1), border: Border::all(1, 'none'))];
    }

    // ---------------------------------------------------------------
    // apply: background
    // ---------------------------------------------------------------

    public function testApplyWithBackground()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        $style = new Style(background: 'red');

        $result = $applier->apply(new ArrayLineBuffer(['Hi']), 20, $style, $widget)->toArray();

        $this->assertCount(1, $result);
        // Red background ANSI code should be present
        $this->assertStringContainsString("\x1b[41m", $result[0]);
        $this->assertSame(20, AnsiUtils::visibleWidth($result[0]));
    }

    // ---------------------------------------------------------------
    // apply: text alignment
    // ---------------------------------------------------------------

    public function testApplyWithCenterAlignment()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        $style = new Style(textAlign: TextAlign::Center);

        $result = $applier->apply(new ArrayLineBuffer(['Hi']), 20, $style, $widget)->toArray();

        // "Hi" is 2 chars wide, centered in 20 = 9 spaces + "Hi" + 9 spaces
        $plain = AnsiUtils::stripAnsiCodes($result[0]);
        $this->assertSame(20, AnsiUtils::visibleWidth($result[0]));
        $leading = \strlen($plain) - \strlen(ltrim($plain));
        $this->assertSame(9, $leading);
    }

    public function testApplyWithRightAlignment()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        $style = new Style(textAlign: TextAlign::Right);

        $result = $applier->apply(new ArrayLineBuffer(['Hi']), 20, $style, $widget)->toArray();

        // "Hi" is 2 chars wide, right-aligned in 20 = 18 spaces + "Hi"
        $plain = AnsiUtils::stripAnsiCodes($result[0]);
        $this->assertSame(20, AnsiUtils::visibleWidth($result[0]));
        $leading = \strlen($plain) - \strlen(ltrim($plain));
        $this->assertSame(18, $leading);
    }

    public function testAlignmentIsUniformAcrossMultipleLines()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        $style = new Style(textAlign: TextAlign::Center);

        // Two lines of different length: both should shift by the same offset
        // (based on the widest line, not per-line)
        $result = $applier->apply(new ArrayLineBuffer(['Long line', 'Hi']), 30, $style, $widget)->toArray();

        $plain0 = AnsiUtils::stripAnsiCodes($result[0]);
        $plain1 = AnsiUtils::stripAnsiCodes($result[1]);
        $leading0 = \strlen($plain0) - \strlen(ltrim($plain0));
        $leading1 = \strlen($plain1) - \strlen(ltrim($plain1));
        // Both lines should have the same leading offset
        $this->assertSame($leading0, $leading1);
    }

    // ---------------------------------------------------------------
    // apply: content truncation
    // ---------------------------------------------------------------

    public function testApplyTruncatesContentToInnerWidth()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        // Left padding 5, right padding 5 → inner width = 10
        $style = new Style(padding: new Padding(0, 5, 0, 5));
        $longLine = str_repeat('X', 30);

        $result = $applier->apply(new ArrayLineBuffer([$longLine]), 20, $style, $widget)->toArray();

        $this->assertCount(1, $result);
        $this->assertSame(20, AnsiUtils::visibleWidth($result[0]));
    }

    // ---------------------------------------------------------------
    // apply: border + padding combined
    // ---------------------------------------------------------------

    public function testApplyBorderAndPaddingCombined()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        $style = new Style(padding: Padding::all(1), border: Border::all(1, 'none'));

        $result = $applier->apply(new ArrayLineBuffer(['Text']), 20, $style, $widget)->toArray();

        // 1 border-top + 1 padding-top + 1 content + 1 padding-bottom + 1 border-bottom = 5
        $this->assertCount(5, $result);
        foreach ($result as $line) {
            $this->assertSame(20, AnsiUtils::visibleWidth($line));
        }
        // Content should be on line 2 (index 2)
        $this->assertStringContainsString('Text', $result[2]);
    }

    // ---------------------------------------------------------------
    // apply: gradient
    // ---------------------------------------------------------------

    public function testGradientAppliedColumnByColumnOnPaddingLine()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        $style = (new Style())
            ->withLinearGradient(['#000000', '#ffffff'], GradientDirection::LeftToRight)
            ->withPadding(new Padding(1, 0, 0, 0));

        $lines = $applier->apply(new ArrayLineBuffer([]), 4, $style, $widget)->toArray();

        // First line is top-padding: 4 spaces each with different gradient bg code
        $this->assertCount(1, $lines);

        $black = Color::from('#000000')->toBackgroundCode();
        $white = Color::from('#ffffff')->toBackgroundCode();

        $this->assertStringContainsString($black, $lines[0]);
        $this->assertStringContainsString($white, $lines[0]);
    }

    public function testGradientContentLineHasPerColumnCodes()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        $style = (new Style())
            ->withLinearGradient(['#000000', '#ffffff'], GradientDirection::LeftToRight);

        $lines = $applier->apply(new ArrayLineBuffer(['ab']), 2, $style, $widget)->toArray();

        $this->assertCount(1, $lines);
        $black = Color::from('#000000')->toBackgroundCode();
        $white = Color::from('#ffffff')->toBackgroundCode();

        $this->assertStringContainsString($black, $lines[0]);
        $this->assertStringContainsString($white, $lines[0]);
    }

    public function testGradientVerticalDifferentRowsHaveDifferentCodes()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        $style = (new Style())
            ->withLinearGradient(['#000000', '#ffffff'], GradientDirection::TopToBottom);

        // Both rows hold the same character, so any difference between them can only
        // come from the gradient.
        $lines = $applier->apply(new ArrayLineBuffer(['a', 'a']), 1, $style, $widget)->toArray();

        $this->assertCount(2, $lines);
        $this->assertSame(Color::from('#000000')->toBackgroundCode(), self::backgroundAtColumn($lines[0], 0), 'row 0 is the first stop');
        $this->assertSame(Color::from('#ffffff')->toBackgroundCode(), self::backgroundAtColumn($lines[1], 0), 'row 1 is the last stop');
    }

    public function testGradientVerticalContentUsesOffsetWhenBorderPresent()
    {
        // Top+bottom borders only (no left/right sides, to isolate content bg from │ segments).
        // fullHeight = 3: 1(top) + 1(content) + 1(bottom).
        // TopToBottom gradient [black→white] over fullHeight=3:
        //   row 0 (top border): offset=0   → black
        //   row 1 (content):    offset=0.5 → 50% gray  ← 'a' character must use this, not pure black
        //   row 2 (bot border): offset=1   → white
        $applier = $this->createApplier();
        $widget = new TextWidget('a');
        $style = (new Style())
            ->withLinearGradient(['#000000', '#ffffff'], GradientDirection::TopToBottom)
            ->withBorder(Border::xy(0, 1)); // top=1, right=0, bottom=1, left=0

        // lines[0]=top border, lines[1]=content row (no │ side segs), lines[2]=bottom border
        $lines = $applier->apply(new ArrayLineBuffer(['a']), 1, $style, $widget)->toArray();

        $gray = Color::from('#000000')->mix(Color::from('#ffffff'), 50)->toBackgroundCode();

        $this->assertStringContainsString($gray, $lines[1], 'content char should use offset=0.5 (row 1 of fullHeight=3), not pure black');
    }

    public function testGradientContentStartsAtGradientOriginWhenBorderPresent()
    {
        // width=5: 1(left border) + 3(inner) + 1(right border)
        // Gradient spans innerWidth=3 only:
        //   content[0]: offset=0   → pure black  ← first content char (same as left border)
        //   content[1]: offset=0.5 → mix 50%
        //   content[2]: offset=1   → pure white  ← last content char (same as right border)
        // The 25%-interpolated color (from old totalWidth=5 offset) must NOT appear.
        $applier = $this->createApplier();
        $widget = new TextWidget('abc');
        $style = (new Style())
            ->withLinearGradient(['#000000', '#ffffff'], GradientDirection::LeftToRight)
            ->withBorder(Border::all(1));

        // lines[0]=top border, lines[1]=content row, lines[2]=bottom border
        $lines = $applier->apply(new ArrayLineBuffer(['abc']), 5, $style, $widget)->toArray();

        $black = Color::from('#000000')->toBackgroundCode();
        $gray = Color::from('#000000')->mix(Color::from('#ffffff'), 50)->toBackgroundCode();
        $white = Color::from('#ffffff')->toBackgroundCode();
        $quarter = Color::from('#000000')->mix(Color::from('#ffffff'), 25)->toBackgroundCode();

        // The 5 columns are: left border, 3 content cells, right border. The strip
        // spans the 3 inner columns, and the borders echo its endpoints.
        $this->assertSame($black, self::backgroundAtColumn($lines[1], 0), 'left border');
        $this->assertSame($black, self::backgroundAtColumn($lines[1], 1), 'first content cell is offset=0');
        $this->assertSame($gray, self::backgroundAtColumn($lines[1], 2), 'middle content cell is offset=0.5');
        $this->assertSame($white, self::backgroundAtColumn($lines[1], 3), 'last content cell is offset=1');
        $this->assertSame($white, self::backgroundAtColumn($lines[1], 4), 'right border');

        // Spanning totalWidth instead of innerWidth would put 25% gray on a cell.
        $this->assertStringNotContainsString($quarter, $lines[1], 'the strip must span innerWidth, not totalWidth');
    }

    public function testGradientRightBorderHasEndpointColor()
    {
        // width=29: 1(left border) + 27(inner) + 1(right border)
        $applier = $this->createApplier();
        $widget = new TextWidget(str_repeat('a', 27));
        // The child paints its own background, so no content cell carries a gradient
        // code: the endpoint color can then only come from the border itself.
        $style = (new Style())
            ->withLinearGradient(['#000000', '#ffffff'], GradientDirection::LeftToRight)
            ->withBorder(Border::all(1));

        $lines = $applier->apply(new ArrayLineBuffer(["\x1b[48;2;255;0;0m".str_repeat('a', 27)."\x1b[49m"]), 29, $style, $widget)->toArray();

        $white = Color::from('#ffffff')->toBackgroundCode();
        $black = Color::from('#000000')->toBackgroundCode();
        $childBg = "\x1b[48;2;255;0;0m";

        $this->assertSame($black, self::backgroundAtColumn($lines[1], 0), 'left border takes the first stop');
        $this->assertSame($childBg, self::backgroundAtColumn($lines[1], 14), 'content keeps its own background');
        $this->assertSame($white, self::backgroundAtColumn($lines[1], 28), 'right border takes the last stop');
    }

    public function testGradientDoesNotOverrideChildBgWithZeroBlueComponent()
    {
        // ESC[48;2;255;0;0m sets red background (R=255, G=0, B=0). The blue component
        // "0" must NOT be misread as SGR 0 (full reset) inside parseBgState, which
        // would let the parent gradient overwrite the child's red background.
        $applier = $this->createApplier();
        $widget = new TextWidget('X');
        $style = (new Style())
            ->withLinearGradient(['#0000ff', '#ffffff'], GradientDirection::LeftToRight);

        // Content with red background active: gradient must not be injected before 'X'
        $redBg = "\x1b[48;2;255;0;0m";
        $lines = $applier->apply(new ArrayLineBuffer([$redBg.'X']), 2, $style, $widget)->toArray();

        // 'X' must be directly after the red bg code: no parent gradient injected between them
        $this->assertStringContainsString($redBg.'X', $lines[0],
            'parseBgState must return true for red bg (48;2;255;0;0), not mistake blue=0 for SGR 0');
    }

    /**
     * A foreground (38) or underline (58) color never changes the background state,
     * but its payload has to be skipped: a zero channel would otherwise be read as
     * SGR 0 and let the parent gradient paint over the child's background.
     */
    #[DataProvider('zeroComponentForegroundSequenceProvider')]
    public function testGradientDoesNotOverrideChildBgOnForegroundWithZeroComponent(string $foregroundSequence)
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('X');
        $style = (new Style())
            ->withLinearGradient(['#0000ff', '#ffffff'], GradientDirection::LeftToRight);

        $redBg = "\x1b[48;2;255;0;0m";
        $lines = $applier->apply(new ArrayLineBuffer([$redBg.$foregroundSequence.'X']), 2, $style, $widget)->toArray();

        $this->assertStringContainsString($redBg.$foregroundSequence.'X', $lines[0],
            'the child background must survive a foreground color holding a zero component');
    }

    public static function zeroComponentForegroundSequenceProvider(): iterable
    {
        yield 'rgb foreground, zero red' => ["\x1b[38;2;0;255;255m"];
        yield 'rgb foreground, zero green' => ["\x1b[38;2;255;0;255m"];
        yield 'rgb foreground, zero blue' => ["\x1b[38;2;255;255;0m"];
        yield 'palette foreground, index 0' => ["\x1b[38;5;0m"];
        yield 'rgb underline, zero red' => ["\x1b[58;2;0;255;0m"];
        yield 'palette underline, index 0' => ["\x1b[58;5;0m"];
        // 49 as an RGB channel is the bg-default code; it must not be read as one
        yield 'rgb foreground, channel 49' => ["\x1b[38;2;49;49;49m"];
    }

    public function testGradientStillYieldsToBackgroundResetAfterAForegroundColor()
    {
        // The payload skipping must not swallow a genuine SGR 49 that follows.
        $applier = $this->createApplier();
        $widget = new TextWidget('X');
        $style = (new Style())
            ->withLinearGradient(['#0000ff', '#ffffff'], GradientDirection::LeftToRight);

        $lines = $applier->apply(new ArrayLineBuffer(["\x1b[48;2;255;0;0m\x1b[38;2;0;255;0m\x1b[49mX"]), 2, $style, $widget)->toArray();

        $blue = Color::from('#0000ff')->toBackgroundCode();
        $this->assertStringContainsString($blue.'X', $lines[0],
            'once the child resets its background, the gradient must paint again');
    }

    public function testGradientLineKeepsTheTrailingResetsOfTheChild()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('AB');
        $style = (new Style())
            ->withLinearGradient(['#ff0000', '#0000ff'], GradientDirection::LeftToRight);

        // 'AB' fills the two columns exactly, so the resets of the child sit past
        // the last visible cell and used to be dropped along with the rest.
        $lines = $applier->apply(new ArrayLineBuffer(["\x1b[1m\x1b[38;2;0;255;0mAB\x1b[22m\x1b[39m"]), 2, $style, $widget)->toArray();

        $this->assertStringContainsString("\x1b[22m", $lines[0], 'the bold reset of the child must survive');
        $this->assertStringContainsString("\x1b[39m", $lines[0], 'the foreground reset of the child must survive');
        $this->assertStringEndsWith("\x1b[22m\x1b[39m\x1b[49m", $lines[0]);
    }

    public function testGradientLineDoesNotLeakChildStyleOntoTheNextRow()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('ABCD');
        $style = (new Style())
            ->withLinearGradient(['#ff0000', '#0000ff'], GradientDirection::LeftToRight);

        // Both rows fill the four columns exactly; only the first one is styled.
        $lines = $applier->apply(new ArrayLineBuffer(["\x1b[38;2;0;255;0m\x1b[1mABCD\x1b[22m\x1b[39m", 'efgh']), 4, $style, $widget)->toArray();

        $this->assertStringEndsWith("\x1b[22m\x1b[39m\x1b[49m", $lines[0],
            'row 0 must close the bold and the foreground it opened');
        $this->assertStringNotContainsString("\x1b[1m", $lines[1],
            'row 1 carries no style of its own and must not re-open one');
    }

    public function testGradientLineStatesARepeatedBackgroundOnlyOnce()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('aaaa');
        $style = (new Style())
            ->withLinearGradient(['#000000', '#ffffff'], GradientDirection::TopToBottom);

        // A vertical gradient gives every cell of a row the same color.
        $lines = $applier->apply(new ArrayLineBuffer(['aaaa']), 4, $style, $widget)->toArray();

        $this->assertSame(1, substr_count($lines[0], "\x1b[48;2;"), 'a row of one color states it once');
    }

    public function testGradientLineRestatesTheBackgroundAfterAChildSequence()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('abcd');
        $style = (new Style())
            ->withLinearGradient(['#000000', '#ffffff'], GradientDirection::TopToBottom);

        // The child sets then drops its own background in the middle of the row: the
        // gradient has to be restated afterwards even though its color has not changed.
        $lines = $applier->apply(new ArrayLineBuffer(["ab\x1b[48;2;255;0;0mc\x1b[49md"]), 4, $style, $widget)->toArray();

        $gradientCode = $style->getGradient()->resolve(4, 1)[0][0];
        $this->assertSame(2, substr_count($lines[0], $gradientCode),
            'the gradient is stated once before the child, then once after it');
        $this->assertStringContainsString("\x1b[48;2;255;0;0mc", $lines[0], "the child's own background survives");
    }

    // ---------------------------------------------------------------
    // helpers
    // ---------------------------------------------------------------

    /**
     * Return the background code in effect at a given visible column of a line.
     *
     * Asserting on the state of a column rather than on the presence of a code
     * somewhere in the line keeps the test honest: a color that repeats is not
     * restated, and the same code may legitimately appear at another column.
     */
    private static function backgroundAtColumn(string $line, int $column): string
    {
        $background = '';
        $col = 0;

        foreach (preg_split('/(\x1b\[[0-9;]*[a-zA-Z])/', $line, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) as $part) {
            if (str_starts_with($part, "\x1b[")) {
                if (preg_match('/^\x1b\[(?:4[0-7]|10[0-7]|48;[25];[0-9;]+)m$/', $part)) {
                    $background = $part;
                } elseif ("\x1b[49m" === $part || "\x1b[0m" === $part) {
                    $background = '';
                }

                continue;
            }

            foreach (preg_split('//u', $part, -1, \PREG_SPLIT_NO_EMPTY) as $ignored) {
                if ($col === $column) {
                    return $background;
                }
                ++$col;
            }
        }

        return $background;
    }

    private function createApplier(): ChromeApplier
    {
        $renderer = $this->createStub(WidgetRendererInterface::class);
        $renderer->method('resolveStyle')->willReturn(new Style());

        return new ChromeApplier($renderer);
    }
}
