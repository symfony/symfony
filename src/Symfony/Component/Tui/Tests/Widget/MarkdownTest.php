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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\MarkdownWidget;

#[IgnoreDeprecations('League\\\\CommonMark\\\\Util\\\\ArrayCollection')]
class MarkdownTest extends TestCase
{
    public function testRenderEmpty()
    {
        $md = $this->createMarkdown('');
        $lines = $md->render(new RenderContext(40, 24));

        $this->assertSame([], $lines);
    }

    /**
     * @param list<string> $expectedSubstrings substrings to find in the joined output
     */
    #[DataProvider('markdownElementProvider')]
    public function testRenderElement(string $markdown, int $width, array $expectedSubstrings)
    {
        $md = $this->createMarkdown($markdown);
        $lines = $md->render(new RenderContext($width, 24));

        $content = implode("\n", $lines);
        foreach ($expectedSubstrings as $expected) {
            $this->assertStringContainsString($expected, $content);
        }
    }

    /**
     * @return iterable<string, array{string, int, list<string>}>
     */
    public static function markdownElementProvider(): iterable
    {
        yield 'plain text' => ['Hello World', 40, ['Hello World']];
        yield 'heading' => ['# Heading', 40, ['# Heading']];
        yield 'bold (ANSI code)' => ['This is **bold** text', 60, ["\x1b[1m", 'bold']];
        yield 'italic (ANSI code)' => ['This is *italic* text', 60, ["\x1b[3m"]];
        yield 'inline code' => ['Use `code` here', 60, ['code']];
        yield 'code block' => ["```php\necho 'hello';\n```", 40, ['echo']];
        yield 'blockquote' => ['> This is a quote', 40, ['This is a quote']];
        yield 'unordered list' => ["- Item 1\n- Item 2", 40, ['Item 1', 'Item 2']];
        yield 'horizontal rule' => ['---', 40, ['─']];
        yield 'table' => ["| A | B |\n| - | - |\n| 1 | 2 |", 40, ['A', '1', '┌']];
        yield 'link' => ['[Click here](https://example.com)', 60, ['Click here', 'example.com']];
    }

    public function testRenderWithPadding()
    {
        $md = $this->createMarkdown('Hello');
        $md->setStyle(Style::padding([1, 2]));
        $lines = $this->renderThroughRenderer($md, 40, 24);

        // Should have top + content + bottom = 3 lines
        $this->assertCount(3, $lines);
    }

    public function testAllLinesRespectWidth()
    {
        $text = "# Heading\n\nThis is a paragraph with **bold** and *italic* text.\n\n- List item 1\n- List item 2\n\n| A | B |\n| - | - |\n| 1 | 2 |\n\n> Blockquote";
        $md = $this->createMarkdown($text)->setStyle(Style::padding([0, 1]));
        $width = 50;
        $lines = $this->renderThroughRenderer($md, $width, 24);

        foreach ($lines as $i => $line) {
            $lineWidth = AnsiUtils::visibleWidth($line);
            $this->assertLessThanOrEqual(
                $width,
                $lineWidth,
                \sprintf('Line %d exceeds width: %d > %d', $i, $lineWidth, $width),
            );
        }
    }

    public function testSetTextSanitizesInvalidUtf8()
    {
        // "\x80" is an invalid UTF-8 continuation byte on its own
        $md = $this->createMarkdown("Hello \x80World");
        $this->assertSame('Hello World', $md->getText());

        // Also via setText()
        $md->setText("Foo \xC0\xC1 Bar");
        $this->assertSame('Foo  Bar', $md->getText());
    }

    public function testSetTextPreservesValidUtf8()
    {
        $text = 'Hello 😀 World; café';
        $md = $this->createMarkdown($text);
        $this->assertSame($text, $md->getText());
    }

    public function testGetTextIsStableAcrossRenders()
    {
        $md = $this->createMarkdown("Hello \x80World");
        $textBefore = $md->getText();
        $md->render(new RenderContext(40, 24));
        $this->assertSame($textBefore, $md->getText());
    }

    public function testCacheInvalidation()
    {
        $md = $this->createMarkdown('Hello');
        $lines1 = $md->render(new RenderContext(40, 24));

        $md->setText('World');
        $lines2 = $md->render(new RenderContext(40, 24));

        $this->assertStringContainsString('Hello', $lines1[0]);
        $this->assertStringContainsString('World', $lines2[0]);
    }

    public function testStripHtmlTags()
    {
        $md = $this->createMarkdown('Hello <b>world</b> test');
        $lines = $md->render(new RenderContext(60, 24));

        $content = implode('', $lines);
        $this->assertStringNotContainsString('<b>', $content);
        $this->assertStringNotContainsString('</b>', $content);
        $this->assertStringContainsString('world', $content);
    }

    public function testLongLinesAreWrapped()
    {
        // Test that very long lines are properly wrapped to fit within width
        $longText = str_repeat('word ', 100);
        $md = $this->createMarkdown($longText)->setStyle(Style::padding([0, 1]));

        $width = 80;
        $lines = $this->renderThroughRenderer($md, $width, 24);

        foreach ($lines as $i => $line) {
            $lineWidth = AnsiUtils::visibleWidth($line);
            $this->assertLessThanOrEqual(
                $width,
                $lineWidth,
                \sprintf('Line %d exceeds width: %d > %d (long text wrapping)', $i, $lineWidth, $width),
            );
        }
    }

    public function testCodeBlockWithLongLines()
    {
        // Test that code blocks with very long lines don't exceed width
        $longCode = str_repeat('x', 200);
        $text = "```\n{$longCode}\n```";
        $md = $this->createMarkdown($text)->setStyle(Style::padding([0, 1]));

        $width = 100;
        $lines = $this->renderThroughRenderer($md, $width, 24);

        foreach ($lines as $i => $line) {
            $lineWidth = AnsiUtils::visibleWidth($line);
            $this->assertLessThanOrEqual(
                $width,
                $lineWidth,
                \sprintf('Line %d exceeds width: %d > %d (code block with long line)', $i, $lineWidth, $width),
            );
        }
    }

    /**
     * Create a MarkdownWidget attached to a Tui context so stylesheet
     * sub-element resolution (::bold, ::italic, etc.) works.
     */
    private function createMarkdown(string $text): MarkdownWidget
    {
        $terminal = new VirtualTerminal(80, 24);
        $tui = new Tui(terminal: $terminal);
        $md = new MarkdownWidget($text);
        $tui->add($md);

        return $md;
    }

    /**
     * Render a widget through the Renderer pipeline to get full chrome applied.
     *
     * @return string[]
     */
    private function renderThroughRenderer(MarkdownWidget $widget, int $columns, int $rows): array
    {
        $renderer = new Renderer();

        return $renderer->renderWidget($widget, new RenderContext($columns, $rows));
    }
}
