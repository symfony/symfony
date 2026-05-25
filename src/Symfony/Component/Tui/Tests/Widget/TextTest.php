<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Widget;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Widget\TextWidget;

class TextTest extends TestCase
{
    public function testRenderSimpleText()
    {
        $text = new TextWidget('Hello');
        $lines = $this->renderWidget($text, 20, 24);

        $this->assertCount(1, $lines);
        $this->assertStringContainsString('Hello', $lines[0]);
    }

    public function testRenderWithPadding()
    {
        $style = Style::padding([1, 2]);
        $text = new TextWidget('Hello');
        $text->setStyle($style);
        $lines = $this->renderWidget($text, 20, 24);

        // Should have 1 top padding + 1 content + 1 bottom padding = 3 lines
        $this->assertCount(3, $lines);

        // All lines should be full width
        foreach ($lines as $line) {
            $this->assertSame(20, AnsiUtils::visibleWidth($line));
        }

        // Middle line should contain text with padding
        $this->assertStringContainsString('Hello', $lines[1]);
    }

    public function testRenderEmptyText()
    {
        $text = new TextWidget('');
        $lines = $text->render(new RenderContext(20, 24));

        $this->assertSame([], $lines);
    }

    public function testRenderWhitespaceOnlyText()
    {
        $text = new TextWidget('   ');
        $lines = $text->render(new RenderContext(20, 24));

        $this->assertSame([], $lines);
    }

    public function testRenderWithBackground()
    {
        $style = new Style(background: 'red');
        $text = new TextWidget('Hello');
        $text->setStyle($style);
        $lines = $this->renderWidget($text, 20, 24);

        $this->assertStringContainsString("\x1b[41m", $lines[0]);
        $this->assertSame(20, AnsiUtils::visibleWidth($lines[0]));
    }

    public function testRenderLineWidthWithBackground()
    {
        $text = new TextWidget('Hello');
        $text->setStyle(new Style(background: 'blue'));
        $width = 30;
        $lines = $this->renderWidget($text, $width, 24);

        // Lines are padded to full width when background is set
        foreach ($lines as $line) {
            $this->assertSame($width, AnsiUtils::visibleWidth($line));
        }
    }

    public function testRenderLongTextWraps()
    {
        $longText = 'This is a longer text that should wrap across multiple lines.';
        $text = new TextWidget($longText);
        $lines = $this->renderWidget($text, 20, 24);

        $this->assertGreaterThan(1, \count($lines));

        // Each line should fit within the width
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(20, AnsiUtils::visibleWidth($line));
        }
    }

    public function testRenderTruncatedText()
    {
        $longText = 'This is a very long text that should be truncated with ellipsis';
        $text = new TextWidget($longText, truncate: true);
        $lines = $this->renderWidget($text, 20, 24);

        // Should be exactly 1 line when truncated
        $this->assertCount(1, $lines);

        // Line should be exactly 20 wide
        $this->assertSame(20, AnsiUtils::visibleWidth($lines[0]));

        // Should contain ellipsis (...)
        $this->assertStringContainsString('...', $lines[0]);
    }

    public function testRenderTruncatedMultilineText()
    {
        $multilineText = "First line that is very long and will be truncated\nSecond line also very long and will be truncated\nThird line too";
        $text = new TextWidget($multilineText, truncate: true);
        $lines = $this->renderWidget($text, 30, 24);

        // Should have 3 lines (one per input line)
        $this->assertCount(3, $lines);

        // Each line should fit within 30
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(30, AnsiUtils::visibleWidth($line));
        }

        // First two lines should have ellipsis (they're long)
        $this->assertStringContainsString('...', $lines[0]);
        $this->assertStringContainsString('...', $lines[1]);
    }

    public function testRenderTruncatedShortText()
    {
        $shortText = 'Short';
        $text = new TextWidget($shortText, truncate: true);
        $lines = $this->renderWidget($text, 20, 24);

        // Should be exactly 1 line
        $this->assertCount(1, $lines);

        // Should contain the text without ellipsis
        $this->assertStringContainsString('Short', $lines[0]);
        $this->assertStringNotContainsString('…', $lines[0]);
    }

    public function testRenderTruncatedWithPadding()
    {
        $longText = 'This text will be truncated after padding is applied';
        $style = Style::padding([1, 2]);
        $text = new TextWidget($longText, truncate: true);
        $text->setStyle($style);
        $lines = $this->renderWidget($text, 30, 24);

        // Should have 1 top padding + 1 content + 1 bottom padding = 3 lines
        $this->assertCount(3, $lines);

        // Content line should have padding spaces at start
        $this->assertStringStartsWith('  ', $lines[1]);

        // All lines should be exactly 30 wide
        foreach ($lines as $line) {
            $this->assertSame(30, AnsiUtils::visibleWidth($line));
        }
    }

    public function testRenderTruncatedWithAnsiCodes()
    {
        $styledText = "\x1b[32mGreen text that is very long and should be truncated\x1b[0m";
        $text = new TextWidget($styledText, truncate: true);
        $lines = $this->renderWidget($text, 20, 24);

        // Should be exactly 1 line
        $this->assertCount(1, $lines);

        // Should contain ANSI codes
        $this->assertStringContainsString("\x1b[32m", $lines[0]);

        // Visible width should be exactly 20
        $this->assertSame(20, AnsiUtils::visibleWidth($lines[0]));
    }

    public function testRenderTruncatedWithBackground()
    {
        $longText = 'This text has a background and will be truncated';
        $style = new Style(background: 'red');
        $text = new TextWidget($longText, truncate: true);
        $text->setStyle($style);
        $lines = $this->renderWidget($text, 25, 24);

        // Should contain background ANSI code
        $this->assertStringContainsString("\x1b[41m", $lines[0]);

        // Should be exactly 25 visible width
        $this->assertSame(25, AnsiUtils::visibleWidth($lines[0]));
    }

    public function testTruncateVsWrapBehavior()
    {
        $longText = 'This is a text that would normally wrap to multiple lines but with truncate it stays on one line';

        // Without truncate (wrapping)
        $wrappedText = new TextWidget($longText, truncate: false);
        $wrappedLines = $this->renderWidget($wrappedText, 30, 24);

        // With truncate
        $truncatedText = new TextWidget($longText, truncate: true);
        $truncatedLines = $this->renderWidget($truncatedText, 30, 24);

        // Wrapped should have multiple lines
        $this->assertGreaterThan(1, \count($wrappedLines));

        // Truncated should have exactly 1 line
        $this->assertCount(1, $truncatedLines);
    }

    /**
     * Render a widget through the Renderer pipeline to get full chrome applied.
     *
     * @return string[]
     */
    private function renderWidget(TextWidget $widget, int $columns, int $rows): array
    {
        $renderer = new Renderer();

        return $renderer->renderWidget($widget, new RenderContext($columns, $rows));
    }
}
