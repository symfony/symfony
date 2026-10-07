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
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
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
        yield 'heading' => ['# Heading', 40, ['Heading']];
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

    public function testRenderUnattachedUsesDefaultElementStyles()
    {
        $md = new MarkdownWidget("# Hello\n\nsome **bold** and *italic* text");
        $lines = $md->render(new RenderContext(60, 24));
        $content = implode("\n", $lines);

        $this->assertStringContainsString("\x1b[1m", $content);
        $this->assertStringContainsString("\x1b[3m", $content);
        $this->assertStringContainsString('Hello', $content);
        $this->assertStringNotContainsString('# Hello', AnsiUtils::stripAnsiCodes($content));
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

        // "crème café" in ISO-8859-1
        $md->setText("cr\xE8me caf\xE9");
        $this->assertSame('crme caf', $md->getText());
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

    public function testRenderRawHtmlTags()
    {
        $md = $this->createMarkdown('Hello <b>world</b> and <system-reminder> test');
        $lines = $md->render(new RenderContext(60, 24));

        $content = implode('', $lines);
        $this->assertStringContainsString('<b>', $content);
        $this->assertStringContainsString('</b>', $content);
        $this->assertStringContainsString('<system-reminder>', $content);
        $this->assertStringContainsString('world', $content);
    }

    public function testRenderRawHtmlBlocksWithoutDroppingSurroundingText()
    {
        $md = $this->createMarkdown("before\n<div>inside</div>\nafter");
        $lines = $md->render(new RenderContext(60, 24));

        $content = implode("\n", $lines);
        $this->assertStringContainsString('before', $content);
        $this->assertStringContainsString('<div>inside</div>', $content);
        $this->assertStringContainsString('after', $content);
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

    public function testRenderTaskListCheckboxes()
    {
        $md = $this->createMarkdown("- [x] done\n- [ ] todo that wraps onto a second line\n1. [x] first");
        $lines = array_map(AnsiUtils::stripAnsiCodes(...), $md->render(new RenderContext(24, 24)));

        $this->assertSame([
            '• [x] done',
            '• [ ] todo that wraps',
            '      onto a second line',
            '',
            '1. [x] first',
        ], array_map(rtrim(...), $lines));
    }

    public function testRenderSingleTildesAsText()
    {
        $md = $this->createMarkdown('range 5~10 and 20~30 ok, ~~gone~~');
        $line = $md->render(new RenderContext(60, 24))[0];

        $this->assertSame('range 5~10 and 20~30 ok, gone', AnsiUtils::stripAnsiCodes($line));
        $this->assertSame(1, substr_count($line, "\x1b[9m"), 'Only the double tilde strikes text through');
        $this->assertStringContainsString("\x1b[9mgone", $line);
    }

    #[DataProvider('blockStyleAfterInlineStyleProvider')]
    public function testBlockStyleIsRestoredAfterAnInlineStyle(string $markdown, string $char, string $blockStyle)
    {
        $lines = (new MarkdownWidget($markdown))->render(new RenderContext(40, 24));
        $screen = new ScreenBuffer(40, 3);
        $screen->write($lines[0]);

        $cells = $screen->getCells()[0];
        $column = array_search($char, array_column($cells, 'char'), true);

        $this->assertSame($blockStyle, $cells[$column]['style']);
    }

    public static function blockStyleAfterInlineStyleProvider(): iterable
    {
        yield 'heading color after inline code' => ['# Use `foo` now', 'w', "\x1b[1;36m"];
        yield 'heading bold after strong' => ['# **A** b', 'b', "\x1b[1;36m"];
        yield 'quote italic after emphasis' => ['> a *b* c', 'c', "\x1b[3m"];
    }

    public function testWrappedTableCellStyleDoesNotLeakIntoTheRestOfTheRow()
    {
        $lines = (new MarkdownWidget("| a | b |\n|---|---|\n| **bold text that wraps a lot here** | x |"))->render(new RenderContext(30, 24));
        $screen = new ScreenBuffer(30, \count($lines));
        $screen->write(implode("\r\n", $lines));
        $cells = $screen->getCells()[3];

        $this->assertSame('│ bold text that wraps a │ x │', implode('', array_column($cells, 'char')));
        $this->assertSame("\x1b[1m", $cells[2]['style'], 'The wrapped text is bold');
        $this->assertSame('', $cells[24]['style'], 'The padding is not bold');
        $this->assertSame('', $cells[25]['style'], 'The border is not bold');
        $this->assertSame('', $cells[27]['style'], 'The next cell is not bold');
    }

    public function testWrappedTableCellBackgroundDoesNotLeakIntoTheRestOfTheRow()
    {
        $md = new MarkdownWidget("| a | b |\n|---|---|\n| `code text that wraps a lot here` | x |");
        $tui = new Tui(new StyleSheet([MarkdownWidget::class.'::code' => new Style()->withBackground('blue')]), new VirtualTerminal(30, 24));
        $tui->add($md);
        $lines = $md->render(new RenderContext(30, 24));
        $screen = new ScreenBuffer(30, \count($lines));
        $screen->write(implode("\r\n", $lines));
        $cells = $screen->getCells()[3];

        $this->assertSame('│ code text that wraps a │ x │', implode('', array_column($cells, 'char')));
        $this->assertSame("\x1b[44m", $cells[2]['style'], 'The wrapped code has a background');
        $this->assertSame('', $cells[24]['style'], 'The padding has no background');
        $this->assertSame('', $cells[27]['style'], 'The next cell has no background');
        $this->assertSame('', $screen->getCells()[4][0]['style'], 'The next line has no background');
    }

    public function testWrappedTableCellInABlockquoteKeepsTheQuoteStyleForTheRestOfTheRow()
    {
        $lines = (new MarkdownWidget("> | a | b |\n> |---|---|\n> | **bold text that wraps a lot here** | x |"))->render(new RenderContext(30, 24));
        $screen = new ScreenBuffer(30, \count($lines));
        $screen->write(implode("\r\n", $lines));
        $cells = $screen->getCells()[3];

        $this->assertSame('│ │ bold text that wraps │ x │', implode('', array_column($cells, 'char')));
        $this->assertSame("\x1b[1;3m", $cells[4]['style'], 'The wrapped text is bold and in the quote style');
        $this->assertSame("\x1b[3m", $cells[25]['style'], 'The border is in the quote style');
        $this->assertSame("\x1b[3m", $cells[27]['style'], 'The next cell is in the quote style');
    }

    /**
     * While the text is streamed, the closing fence of a code block arrives one character at a time.
     */
    #[DataProvider('partialClosingFenceProvider')]
    public function testPartialClosingFenceIsNotRenderedAsCode(string $markdown, string $complete)
    {
        $render = static fn (string $text): array => array_map(rtrim(...), array_map(AnsiUtils::stripAnsiCodes(...), (new MarkdownWidget($text))->render(new RenderContext(20, 24))));

        $this->assertSame($render($complete), $render($markdown));
    }

    public static function partialClosingFenceProvider(): iterable
    {
        yield 'one backtick' => ["```\necho 1;\n`", "```\necho 1;\n```"];
        yield 'two backticks' => ["```php\necho 1;\n``", "```php\necho 1;\n```"];
        yield 'tildes' => ["~~~~\nx\n~~~", "~~~~\nx\n~~~~"];
        yield 'in a list item' => ["- a\n\n  ```\n  x\n  ``", "- a\n\n  ```\n  x\n  ```"];
        yield 'in a blockquote' => ["> ```\n> x\n> ``", "> ```\n> x\n> ```"];
    }

    #[DataProvider('codeEndingWithFenceCharactersProvider')]
    public function testCodeEndingWithFenceCharactersIsKept(string $markdown, string $lastCodeLine)
    {
        $lines = (new MarkdownWidget($markdown))->render(new RenderContext(20, 24));

        $this->assertContains($lastCodeLine, array_map(rtrim(...), array_map(AnsiUtils::stripAnsiCodes(...), $lines)));
    }

    public static function codeEndingWithFenceCharactersProvider(): iterable
    {
        yield 'closed, followed by a paragraph' => ["```\nx\n``\n```\n\nafter", '  ``'];
        yield 'closed, last block' => ["```\nx\n``\n```", '  ``'];
        yield 'closed by a longer fence' => ["```\nx\n``\n````", '  ``'];
        yield 'shorter fence in a longer one' => ["````md\n```php\necho 1;\n```\n````", '  ```'];
        yield 'closed tildes' => ["~~~\nx\n~~\n~~~", '  ~~'];
        yield 'closed in a list item' => ["- a\n\n  ```\n  x\n  ``\n  ```", '    ``'];
        yield 'followed by a link reference definition' => ["```\nx\n``\n```\n\n[a]: https://example.com", '  ``'];
        yield 'unclosed, line complete' => ["```\nx\n``\n", '  ``'];
    }

    #[DataProvider('listSpacingProvider')]
    public function testLooseListItemsAreSeparatedByABlankLine(string $markdown, array $expected)
    {
        $lines = (new MarkdownWidget($markdown))->render(new RenderContext(20, 24));

        $this->assertSame($expected, array_map(rtrim(...), array_map(AnsiUtils::stripAnsiCodes(...), $lines)));
    }

    public static function listSpacingProvider(): iterable
    {
        yield 'tight list' => ["- a\n- b", ['• a', '• b']];
        yield 'loose list' => ["- a\n\n- b\n- c", ['• a', '', '• b', '', '• c']];
        yield 'loose ordered list' => ["1. a\n\n2. b", ['1. a', '', '2. b']];
        yield 'paragraphs in a loose list item' => ["1. a\n\n   b\n\n   c\n2. d", ['1. a', '', '   b', '', '   c', '', '2. d']];
        yield 'code block in a loose list item' => ["- a\n\n  ```\n  x\n  ```", ['• a', '', '  ──────────────────', '    x', '  ──────────────────']];
        yield 'nested list in a loose list item' => ["- a\n\n  - b\n  - c", ['• a', '', '  • b', '  • c']];
        yield 'paragraphs in a tight list item' => ["- a\n  b", ['• a', '  b']];
        yield 'nested list in a tight list item' => ["- a\n  - b", ['• a', '  • b']];
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
     * A two-digit marker is four columns wide, so the item's text has four
     * fewer columns to wrap into and its continuation lines line up under it.
     */
    public function testOrderedListWithTwoDigitMarkers()
    {
        $markdown = '';
        for ($i = 1; $i <= 11; ++$i) {
            $markdown .= $i.". item text that is fairly long here\n";
        }

        $md = $this->createMarkdown($markdown);
        $lines = array_map(AnsiUtils::stripAnsiCodes(...), $md->render(new RenderContext(20, 60)));

        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(20, AnsiUtils::visibleWidth($line));
        }

        $tenth = array_search('10. item text that', $lines, true);
        $this->assertNotFalse($tenth, 'The tenth item starts a line of its own: '.implode(' / ', $lines));
        $this->assertStringStartsWith('    ', $lines[$tenth + 1], 'Continuation lines are indented under the text, not under the marker.');
    }

    /**
     * The borders are drawn from the computed column widths, so those widths
     * have to add up to the space the table was given.
     */
    public function testTableWithOneVeryWideColumnStaysInsideTheWidth()
    {
        $wide = str_repeat('W', 60);
        $markdown = "| $wide | b | c |\n|---|---|---|\n| ".str_repeat('X', 60)." | y | z |\n";

        $md = $this->createMarkdown($markdown);

        foreach ([14, 16, 20, 30] as $columns) {
            $lines = $md->render(new RenderContext($columns, 60));
            $this->assertStringStartsWith('┌', AnsiUtils::stripAnsiCodes($lines[0]));
            foreach ($lines as $line) {
                $this->assertSame($columns, AnsiUtils::visibleWidth($line), \sprintf('Every table row fills exactly %d columns.', $columns));
            }
        }
    }

    /**
     * Render a widget through the Renderer pipeline to get full chrome applied.
     *
     * @return string[]
     */
    private function renderThroughRenderer(MarkdownWidget $widget, int $columns, int $rows): array
    {
        $renderer = new Renderer();

        return $renderer->renderWidgetLines($widget, new RenderContext($columns, $rows))->toArray();
    }
}
