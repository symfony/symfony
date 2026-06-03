<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Ansi;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Ansi\TextWrapper;

class TextWrapperTest extends TestCase
{
    /**
     * @param string[] $expected
     */
    #[DataProvider('wrapBasicProvider')]
    public function testWrapBasic(string $input, int $width, array $expected)
    {
        $this->assertSame($expected, TextWrapper::wrapTextWithAnsi($input, $width));
    }

    /**
     * @return iterable<string, array{string, int, string[]}>
     */
    public static function wrapBasicProvider(): iterable
    {
        yield 'short line fits' => ['Hello', 20, ['Hello']];
        yield 'empty string' => ['', 20, ['']];
        yield 'long line wraps at word boundary' => ['Hello World', 5, ['Hello', 'World']];
        yield 'newlines preserved' => ["Hello\nWorld", 20, ['Hello', 'World']];
        yield 'multiple spaces preserved' => ['Hello   World', 20, ['Hello   World']];
    }

    public function testWrapWithAnsiCodes()
    {
        $styled = "\x1b[31mHello World\x1b[0m";
        $lines = TextWrapper::wrapTextWithAnsi($styled, 5);

        $this->assertCount(2, $lines);
        // Each line should have <= 5 visible width
        $this->assertLessThanOrEqual(5, AnsiUtils::visibleWidth($lines[0]));
        $this->assertLessThanOrEqual(5, AnsiUtils::visibleWidth($lines[1]));
    }

    public function testWrapAnsiCodesPreservedAcrossLines()
    {
        // Bold text that wraps
        $styled = "\x1b[1mHello World\x1b[0m";
        $lines = TextWrapper::wrapTextWithAnsi($styled, 6);

        // Second line should also have the bold code
        $this->assertStringContainsString("\x1b[1m", $lines[0]);
    }

    public function testWrapVeryLongWord()
    {
        $lines = TextWrapper::wrapTextWithAnsi('Supercalifragilistic', 5);

        // Should break the word into multiple lines
        $this->assertGreaterThan(1, \count($lines));

        // Each line should be <= 5 visible chars
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(5, AnsiUtils::visibleWidth($line));
        }
    }

    public function testAllLinesRespectWidth()
    {
        $text = 'This is a longer text that should be wrapped properly across multiple lines without exceeding the width.';
        $width = 20;
        $lines = TextWrapper::wrapTextWithAnsi($text, $width);

        foreach ($lines as $i => $line) {
            $lineWidth = AnsiUtils::visibleWidth($line);
            $this->assertLessThanOrEqual(
                $width,
                $lineWidth,
                \sprintf('Line %d exceeds width: "%s" (width=%d)', $i, $line, $lineWidth),
            );
        }
    }

    // --- wrapLineIntoChunks tests ---

    /**
     * @param list<array{text: string, startIndex: int, endIndex: int}> $expected
     */
    #[DataProvider('chunksBasicProvider')]
    public function testChunksBasic(string $input, int $width, array $expected)
    {
        $chunks = TextWrapper::wrapLineIntoChunks($input, $width);

        $this->assertCount(\count($expected), $chunks);
        foreach ($expected as $i => $exp) {
            $this->assertSame($exp['text'], $chunks[$i]['text'], "Chunk $i text");
            $this->assertSame($exp['start_index'], $chunks[$i]['start_index'], "Chunk $i startIndex");
            $this->assertSame($exp['end_index'], $chunks[$i]['end_index'], "Chunk $i endIndex");
        }
    }

    /**
     * @return iterable<string, array{string, int, list<array{text: string, startIndex: int, endIndex: int}>}>
     */
    public static function chunksBasicProvider(): iterable
    {
        yield 'empty string' => ['', 20, [['text' => '', 'start_index' => 0, 'end_index' => 0]]];
        yield 'short line' => ['Hello', 20, [['text' => 'Hello', 'start_index' => 0, 'end_index' => 5]]];
    }

    public function testChunksWordWrap()
    {
        // "hello world" at width 6 wraps to two chunks
        $chunks = TextWrapper::wrapLineIntoChunks('hello world', 6);

        $this->assertCount(2, $chunks);

        // First chunk: "hello " (includes trailing space) at [0, 6)
        $this->assertSame(0, $chunks[0]['start_index']);
        $this->assertSame(6, $chunks[0]['end_index']);
        $this->assertStringContainsString('hello', $chunks[0]['text']);

        // Second chunk: "world" at [6, 11)
        $this->assertSame('world', $chunks[1]['text']);
        $this->assertSame(6, $chunks[1]['start_index']);
        $this->assertSame(11, $chunks[1]['end_index']);
    }

    public function testChunksMultipleWraps()
    {
        // "aa bb cc dd" at width 3 should produce 4 chunks
        $chunks = TextWrapper::wrapLineIntoChunks('aa bb cc dd', 3);

        $this->assertCount(4, $chunks);

        // Each chunk's startIndex should match the start of its word
        // (the space between words is consumed by wrapping)
        $this->assertSame(0, $chunks[0]['start_index']);
        $this->assertSame(3, $chunks[1]['start_index']);
        $this->assertSame(6, $chunks[2]['start_index']);
        $this->assertSame(9, $chunks[3]['start_index']);

        // Verify text content
        $this->assertStringContainsString('aa', $chunks[0]['text']);
        $this->assertStringContainsString('bb', $chunks[1]['text']);
        $this->assertStringContainsString('cc', $chunks[2]['text']);
        $this->assertSame('dd', $chunks[3]['text']);
    }

    public function testChunksLongWord()
    {
        // Long word that must be force-broken
        $chunks = TextWrapper::wrapLineIntoChunks('abcdefghij', 4);

        $this->assertGreaterThan(1, \count($chunks));

        // Chunks should cover the entire string
        $this->assertSame(0, $chunks[0]['start_index']);
        $this->assertSame(\strlen('abcdefghij'), $chunks[\count($chunks) - 1]['end_index']);

        // No gaps between chunks
        for ($i = 1; $i < \count($chunks); ++$i) {
            $this->assertSame($chunks[$i - 1]['end_index'], $chunks[$i]['start_index'],
                'Chunks should be contiguous for force-broken words');
        }
    }

    public function testChunksMultipleSpaces()
    {
        // "abc   def" at width 4; spaces cause wrapping
        $chunks = TextWrapper::wrapLineIntoChunks('abc   def', 4);

        // "def" should be in the last chunk starting at byte 6
        $lastChunk = $chunks[\count($chunks) - 1];
        $this->assertSame('def', $lastChunk['text']);
        $this->assertSame(6, $lastChunk['start_index']);
        $this->assertSame(9, $lastChunk['end_index']);
    }

    public function testChunksAllRespectWidth()
    {
        $text = 'This is a longer text that should be wrapped properly across multiple lines';
        $width = 15;
        $chunks = TextWrapper::wrapLineIntoChunks($text, $width);

        foreach ($chunks as $i => $chunk) {
            $visibleWidth = AnsiUtils::visibleWidth(rtrim($chunk['text']));
            $this->assertLessThanOrEqual(
                $width,
                $visibleWidth,
                \sprintf('Chunk %d exceeds width: "%s" (width=%d)', $i, $chunk['text'], $visibleWidth),
            );
        }

        // First chunk starts at 0, last chunk ends at string length
        $this->assertSame(0, $chunks[0]['start_index']);
        $this->assertSame(\strlen($text), $chunks[\count($chunks) - 1]['end_index']);
    }

    public function testChunksUtf8()
    {
        // "café world" at width 6: 'é' is 2 bytes
        $chunks = TextWrapper::wrapLineIntoChunks('café world', 6);

        $this->assertCount(2, $chunks);
        $this->assertStringContainsString('café', $chunks[0]['text']);
        // "café " is 6 bytes (c=1, a=1, f=1, é=2, space=1)
        $this->assertSame(6, $chunks[1]['start_index']);
        $this->assertSame('world', $chunks[1]['text']);
    }

    // --- Tab as word-wrap boundary tests ---

    public function testWrapAtTabBoundaryAsciiPath()
    {
        // "Hello\tWorld": tab is 3 columns, so visible width = 5+3+5 = 13
        // At width 10, should wrap at the tab boundary
        $lines = TextWrapper::wrapTextWithAnsi("Hello\tWorld", 10);

        $this->assertCount(2, $lines);
        $this->assertSame('Hello', $lines[0]);
        $this->assertSame('World', $lines[1]);
    }

    public function testWrapAtTabBoundaryWithAnsiCodes()
    {
        // ANSI-styled text with tab: takes the ANSI-aware code path
        $styled = "\x1b[31mHello\tWorld\x1b[0m";
        $lines = TextWrapper::wrapTextWithAnsi($styled, 10);

        $this->assertCount(2, $lines);
        $this->assertSame(5, AnsiUtils::visibleWidth($lines[0]));
        $this->assertSame(5, AnsiUtils::visibleWidth($lines[1]));
        // ANSI red code should be present on both lines
        $this->assertStringContainsString("\x1b[31m", $lines[0]);
        $this->assertStringContainsString("\x1b[31m", $lines[1]);
    }

    public function testChunksWrapAtTabBoundary()
    {
        // "Hello\tWorld" at width 9: "Hello\t" is 5+3=8 cols, "World" is 5 cols
        // Total 13 cols, should wrap at the tab boundary
        $chunks = TextWrapper::wrapLineIntoChunks("Hello\tWorld", 9);

        $this->assertCount(2, $chunks);
        $this->assertStringContainsString('Hello', $chunks[0]['text']);
        $this->assertSame('World', $chunks[1]['text']);
    }

    public function testTabTreatedAsWhitespaceNotPartOfWord()
    {
        // "ab\tcd" at width 5: tab is 3 cols, so "ab\t" = 5 cols, "cd" = 2 cols
        // Without the fix, "ab\tcd" would be a single 7-col token force-broken
        // With the fix, it wraps cleanly at the tab
        $lines = TextWrapper::wrapTextWithAnsi("ab\tcd", 5);

        $this->assertCount(2, $lines);
        $this->assertSame('ab', $lines[0]);
        $this->assertSame('cd', $lines[1]);
    }

    public function testMultipleTabsAsWhitespace()
    {
        // Consecutive tabs should be grouped as whitespace
        $lines = TextWrapper::wrapTextWithAnsi("Hello\t\tWorld", 10);

        $this->assertCount(2, $lines);
        $this->assertSame('Hello', $lines[0]);
        $this->assertSame('World', $lines[1]);
    }
}
