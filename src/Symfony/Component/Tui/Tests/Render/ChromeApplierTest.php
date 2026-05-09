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
use Symfony\Component\Tui\Render\ChromeApplier;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Render\WidgetRendererInterface;
use Symfony\Component\Tui\Style\Border;
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
        $lines = ['Hello', 'World'];

        $result = $applier->apply($lines, 20, new Style(), $widget);

        $this->assertSame($lines, $result);
    }

    public function testApplyEmptyLinesWithNoChromeReturnsEmpty()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');

        $result = $applier->apply([], 20, new Style(), $widget);

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

        $result = $applier->apply(['Content'], 20, $style, $widget);

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

        $result = $applier->apply(['Hi'], 20, $style, $widget);

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

        $result = $applier->apply([], 20, $style, $widget);

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

        $result = $applier->apply(['Hello'], 20, $style, $widget);

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

        $result = $applier->apply([], 20, $style, $widget);

        // 1 border-top + 1 border-bottom = 2 lines
        $this->assertCount(2, $result);
    }

    // ---------------------------------------------------------------
    // apply: background
    // ---------------------------------------------------------------

    public function testApplyWithBackground()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        $style = new Style(background: 'red');

        $result = $applier->apply(['Hi'], 20, $style, $widget);

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

        $result = $applier->apply(['Hi'], 20, $style, $widget);

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

        $result = $applier->apply(['Hi'], 20, $style, $widget);

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
        $result = $applier->apply(['Long line', 'Hi'], 30, $style, $widget);

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

        $result = $applier->apply([$longLine], 20, $style, $widget);

        $this->assertCount(1, $result);
        $this->assertSame(20, AnsiUtils::visibleWidth($result[0]));
    }

    // ---------------------------------------------------------------
    // apply: caching
    // ---------------------------------------------------------------

    public function testApplyReturnsCachedResult()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        $style = new Style(padding: Padding::all(1));

        $result1 = $applier->apply(['Hello'], 20, $style, $widget);
        $result2 = $applier->apply(['Hello'], 20, $style, $widget);

        $this->assertSame($result1, $result2);
    }

    // ---------------------------------------------------------------
    // apply: border + padding combined
    // ---------------------------------------------------------------

    public function testApplyBorderAndPaddingCombined()
    {
        $applier = $this->createApplier();
        $widget = new TextWidget('test');
        $style = new Style(padding: Padding::all(1), border: Border::all(1, 'none'));

        $result = $applier->apply(['Text'], 20, $style, $widget);

        // 1 border-top + 1 padding-top + 1 content + 1 padding-bottom + 1 border-bottom = 5
        $this->assertCount(5, $result);
        foreach ($result as $line) {
            $this->assertSame(20, AnsiUtils::visibleWidth($line));
        }
        // Content should be on line 2 (index 2)
        $this->assertStringContainsString('Text', $result[2]);
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
