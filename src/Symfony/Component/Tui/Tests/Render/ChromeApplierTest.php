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
use Symfony\Component\Tui\Style\Padding;
use Symfony\Component\Tui\Style\Shadow;
use Symfony\Component\Tui\Style\ShadowOrientation;
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
        yield 'shadow only offset=1' => [40, 10, (new Style())->withShadow(new Shadow(offset: 1)), [39, 9]];
        yield 'shadow only offset=2' => [40, 10, (new Style())->withShadow(new Shadow(offset: 2)), [38, 8]];
        yield 'shadow and border' => [40, 10, (new Style())->withShadow(new Shadow(offset: 1))->withBorder([1]), [37, 7]];
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
        yield 'bottom-right shadow shifts nothing' => [(new Style())->withShadow(new Shadow(offset: 2)), [0, 0]];
        yield 'top-left shadow shifts both' => [(new Style())->withShadow(new Shadow(orientation: ShadowOrientation::TopLeft, offset: 2)), [2, 2]];
        yield 'top-right shadow shifts down' => [(new Style())->withShadow(new Shadow(orientation: ShadowOrientation::TopRight, offset: 1)), [1, 0]];
        yield 'bottom-left shadow shifts right' => [(new Style())->withShadow(new Shadow(orientation: ShadowOrientation::BottomLeft, offset: 1)), [0, 1]];
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
    // apply: shadow
    // ---------------------------------------------------------------

    public function testApplyShadowClampedInNarrowBox()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        $style = (new Style())->withShadow(new Shadow(offset: 3));

        $result = $applier->apply(new ArrayLineBuffer(['AB']), 2, $style, $widget)->toArray();

        // The shadow shrinks to 1 column so the box keeps 1
        $this->assertCount(2, $result);
        foreach ($result as $line) {
            $this->assertSame(2, AnsiUtils::visibleWidth($line));
        }
    }

    public function testApplyShadowUsesItsColorOnce()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        $style = (new Style())->withShadow(new Shadow(color: Color::named('red'), offset: 2));

        $result = $applier->apply(new ArrayLineBuffer(['AAAA', 'BBBB', 'CCCC']), 20, $style, $widget)->toArray();

        $red = Color::named('red')->toForegroundCode();
        // A full side run is painted with a single color prefix
        $this->assertSame(1, substr_count($result[2], $red));
        $this->assertStringContainsString($red, $result[3]);
        $this->assertStringContainsString($red, $result[4]);
        // The skipped first row carries no shadow color
        $this->assertStringNotContainsString($red, $result[0]);
    }

    public function testApplyShadowBottomRightAddsRowsAndColumns()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        // offset=1: 1 extra row (bottom), 1 extra col (right)
        $style = (new Style())->withShadow(new Shadow(offset: 1));

        $result = $applier->apply(new ArrayLineBuffer(['Hello']), 20, $style, $widget)->toArray();

        // 1 content row + 1 shadow row = 2 lines
        $this->assertCount(2, $result);
        // All lines must be exactly $width wide (19 content + 1 shadow col)
        foreach ($result as $line) {
            $this->assertSame(20, AnsiUtils::visibleWidth($line));
        }
    }

    public function testApplyShadowBottomRightVerticalStaircase()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        // offset=2: side shadow builds up over 2 rows
        $style = (new Style())->withShadow(new Shadow(offset: 2));
        $lines = new ArrayLineBuffer(['AAAA', 'BBBB', 'CCCC']);

        $result = $applier->apply($lines, 20, $style, $widget)->toArray();

        // 3 content rows + 2 shadow rows = 5
        $this->assertCount(5, $result);

        // Row 0 (distance=0 from top/skipped edge): trailing spaces only, no shadow chars
        $plain0 = AnsiUtils::stripAnsiCodes($result[0]);
        $this->assertSame(' ', mb_substr($plain0, -1, 1)); // trailing space
        $this->assertSame(' ', mb_substr($plain0, -2, 1)); // second-to-last also space

        // Row 1 (distance=1): 1 shadow col + 1 trailing space
        $plain1 = AnsiUtils::stripAnsiCodes($result[1]);
        $this->assertSame(' ', mb_substr($plain1, -1, 1)); // trailing space
        $this->assertNotSame(' ', mb_substr($plain1, -2, 1)); // shadow char before trailing space

        // Row 2 (distance=2 ≥ offset): 2 shadow cols, no trailing space
        $plain2 = AnsiUtils::stripAnsiCodes($result[2]);
        $this->assertNotSame(' ', mb_substr($plain2, -1, 1)); // no trailing space
        $this->assertNotSame(' ', mb_substr($plain2, -2, 1)); // shadow char at -2 too

        // All lines same total width
        foreach ($result as $line) {
            $this->assertSame(20, AnsiUtils::visibleWidth($line));
        }
    }

    public function testApplyShadowBottomRightHorizontalStaircase()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        // offset=2: 2 shadow rows below
        $style = (new Style())->withShadow(new Shadow(offset: 2));

        $result = $applier->apply(new ArrayLineBuffer(['AAAA']), 20, $style, $widget)->toArray();

        // 1 content + 2 shadow rows = 3
        $this->assertCount(3, $result);

        // Shadow row rowD=1 (index 1): starts with 1 space (gap=1), then shadow body
        $plain1 = AnsiUtils::stripAnsiCodes($result[1]);
        $this->assertSame(' ', $plain1[0]);

        // Shadow row rowD=2 (index 2): starts with 2 spaces (gap=2), then shorter body
        $plain2 = AnsiUtils::stripAnsiCodes($result[2]);
        $this->assertSame(' ', $plain2[0]);
        $this->assertSame(' ', $plain2[1]);

        // All lines same total width
        foreach ($result as $line) {
            $this->assertSame(20, AnsiUtils::visibleWidth($line));
        }
    }

    public function testApplyShadowTopRightAddsRowAboveAndColumnRight()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        $style = (new Style())->withShadow(new Shadow(orientation: ShadowOrientation::TopRight, offset: 1));

        $result = $applier->apply(new ArrayLineBuffer(['Hello']), 20, $style, $widget)->toArray();

        // 1 shadow row (top) + 1 content = 2 lines
        $this->assertCount(2, $result);
        foreach ($result as $line) {
            $this->assertSame(20, AnsiUtils::visibleWidth($line));
        }
    }

    public function testApplyShadowBottomLeftAddsRowBelowAndColumnLeft()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        $style = (new Style())->withShadow(new Shadow(orientation: ShadowOrientation::BottomLeft, offset: 1));

        $result = $applier->apply(new ArrayLineBuffer(['Hello']), 20, $style, $widget)->toArray();

        // 1 content + 1 shadow row = 2 lines
        $this->assertCount(2, $result);
        foreach ($result as $line) {
            $this->assertSame(20, AnsiUtils::visibleWidth($line));
        }

        // Content line: shadow column prepended (left side)
        $plain0 = AnsiUtils::stripAnsiCodes($result[0]);
        // First row from top edge = skipped (no shadow chars), only leading space
        $this->assertSame(' ', $plain0[0]);
    }

    public function testApplyShadowTopLeftAddsRowAboveAndColumnLeft()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        $style = (new Style())->withShadow(new Shadow(orientation: ShadowOrientation::TopLeft, offset: 1));

        $result = $applier->apply(new ArrayLineBuffer(['Hello']), 20, $style, $widget)->toArray();

        // 1 shadow row (top) + 1 content = 2 lines
        $this->assertCount(2, $result);
        foreach ($result as $line) {
            $this->assertSame(20, AnsiUtils::visibleWidth($line));
        }
    }

    public function testApplyShadowWithSpreadFalseUsesUniformChar()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        // density=3 (▓), spread=false → all shadow cells use ▓
        $style = (new Style())->withShadow(new Shadow(density: 3, spread: false, offset: 2));

        $result = $applier->apply(new ArrayLineBuffer(['AAAA', 'BBBB', 'CCCC']), 20, $style, $widget)->toArray();

        // Row 1: 1 shadow col + 1 trailing space (staircase)
        $plain1 = AnsiUtils::stripAnsiCodes($result[1]);
        $this->assertSame('▓', mb_substr($plain1, -2, 1));
        $this->assertSame(' ', mb_substr($plain1, -1, 1));

        // Row 2: 2 shadow cols, no trailing space
        $plain2 = AnsiUtils::stripAnsiCodes($result[2]);
        $this->assertSame('▓▓', mb_substr($plain2, -2));
    }

    public function testApplyShadowCombinedWithBorder()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        $style = (new Style())
            ->withBorder([1])
            ->withShadow(new Shadow(offset: 1));

        $result = $applier->apply(new ArrayLineBuffer(['Hello']), 20, $style, $widget)->toArray();

        // 1 border-top + 1 content + 1 border-bottom + 1 shadow row = 4 lines
        $this->assertCount(4, $result);
        foreach ($result as $line) {
            $this->assertSame(20, AnsiUtils::visibleWidth($line));
        }
    }

    // ---------------------------------------------------------------
    // helpers
    // ---------------------------------------------------------------

    private function createApplier(): ChromeApplier
    {
        $renderer = $this->createStub(WidgetRendererInterface::class);
        $renderer->method('resolveStyle')->willReturn(new Style());

        return new ChromeApplier($renderer);
    }
}
