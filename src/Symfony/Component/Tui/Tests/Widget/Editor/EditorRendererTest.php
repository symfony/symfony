<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Widget\Editor;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Style\CursorShape;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Widget\Editor\EditorRenderer;

class EditorRendererTest extends TestCase
{
    private EditorRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new EditorRenderer();
    }

    public function testRenderEmptyDocument()
    {
        $lines = $this->renderSimple([''], 0, 0, 40, 10);

        // Top border + 1 content line + bottom border
        $this->assertCount(3, $lines);
    }

    public function testRenderMultipleLines()
    {
        $lines = $this->renderSimple(['Line 1', 'Line 2', 'Line 3'], 0, 0, 40, 10);

        // Top border + 3 content lines + bottom border
        $this->assertCount(5, $lines);
    }

    public function testRenderLinesDoNotExceedWidth()
    {
        $width = 30;
        $lines = $this->renderSimple(['Hello World', 'Second line'], 0, 5, $width, 10);

        foreach ($lines as $i => $line) {
            $lineWidth = AnsiUtils::visibleWidth($line);
            $this->assertLessThanOrEqual(
                $width,
                $lineWidth,
                \sprintf('Line %d exceeds width: %d > %d', $i, $lineWidth, $width),
            );
        }
    }

    public function testRenderScrollIndicatorAbove()
    {
        $lines = $this->renderWithViewport(
            ['Line 0', 'Line 1', 'Line 2'],
            ['scroll_offset' => 1, 'visible_line_count' => 2, 'lines_above' => 1, 'lines_below' => 0],
            1, 0, 40, 10,
        );

        $topBorder = AnsiUtils::stripAnsiCodes($lines[0]);
        $this->assertStringContainsString('↑', $topBorder);
        $this->assertStringContainsString('1 more', $topBorder);
    }

    public function testRenderScrollIndicatorBelow()
    {
        $lines = $this->renderWithViewport(
            ['Line 0', 'Line 1', 'Line 2'],
            ['scroll_offset' => 0, 'visible_line_count' => 2, 'lines_above' => 0, 'lines_below' => 1],
            0, 0, 40, 10,
        );

        $bottomBorder = AnsiUtils::stripAnsiCodes($lines[\count($lines) - 1]);
        $this->assertStringContainsString('↓', $bottomBorder);
        $this->assertStringContainsString('1 more', $bottomBorder);
    }

    public function testRenderPadsInFillMode()
    {
        $maxDisplayRows = 10;
        $lines = $this->renderSimple(['Line 1', 'Line 2'], 0, 0, 40, $maxDisplayRows, true);

        // Top border + maxDisplayRows content rows + bottom border
        $this->assertCount($maxDisplayRows + 2, $lines);
    }

    public function testRenderWrappedLineDoesNotExceedWidth()
    {
        $width = 20;
        $longLine = str_repeat('x', 50);
        $lines = $this->renderSimple([$longLine], 0, 0, $width, 10);

        foreach ($lines as $i => $line) {
            $lineWidth = AnsiUtils::visibleWidth($line);
            $this->assertLessThanOrEqual(
                $width,
                $lineWidth,
                \sprintf('Line %d exceeds width: %d > %d', $i, $lineWidth, $width),
            );
        }
    }

    public function testRenderCursorAtEndProducesValidUtf8()
    {
        $lines = $this->renderSimple(['café'], 0, \strlen('café'), 40, 10, false, true);

        foreach ($lines as $line) {
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'), 'Line should be valid UTF-8');
        }
    }

    public function testRenderEmojiProducesValidUtf8()
    {
        $lines = $this->renderSimple(['📝 Hello'], 0, 0, 40, 10, false, true);

        foreach ($lines as $line) {
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'), 'Line should be valid UTF-8');
        }
    }

    /**
     * A grapheme cannot be split, so the wrapper hands back a chunk wider
     * than the width whenever one character does not fit in the whole box.
     */
    public function testACharacterWiderThanTheBoxDoesNotOverflowTheLine()
    {
        $family = "\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}";

        foreach ([['日本'], ['x'.$family.'y'], [$family]] as $docLines) {
            foreach ([1, 2, 3, 4] as $columns) {
                foreach ($this->renderSimple($docLines, 0, 0, $columns, 10) as $line) {
                    $this->assertLessThanOrEqual($columns, AnsiUtils::visibleWidth($line), \sprintf('Every rendered row fits in %d columns.', $columns));
                }
            }
        }
    }

    /**
     * The scroll indicator is thirteen columns of its own before any frame is
     * drawn around it, and a pane can be narrower than that.
     */
    public function testTheScrollIndicatorIsCutToTheWidth()
    {
        $docLines = ['one', 'two', 'three', 'four', 'five', 'six'];
        $viewport = [
            'scroll_offset' => 2,
            'visible_line_count' => 2,
            'lines_above' => 2,
            'lines_below' => 2,
        ];

        foreach ([40, 14, 13, 12, 8, 5, 1] as $columns) {
            foreach ($this->renderWithViewport($docLines, $viewport, 2, 0, $columns, 10) as $line) {
                $this->assertLessThanOrEqual($columns, AnsiUtils::visibleWidth($line), \sprintf('Every row fits in %d columns.', $columns));
            }
        }
    }

    public function testTheScrollIndicatorStillFillsTheFrameWhenItFits()
    {
        $docLines = ['one', 'two', 'three', 'four'];
        $viewport = [
            'scroll_offset' => 1,
            'visible_line_count' => 1,
            'lines_above' => 1,
            'lines_below' => 2,
        ];

        $lines = $this->renderWithViewport($docLines, $viewport, 1, 0, 20, 10);

        $this->assertSame('─── ↑ 1 more ───────', AnsiUtils::stripAnsiCodes($lines[0]));
        $this->assertSame('─── ↓ 2 more ───────', AnsiUtils::stripAnsiCodes($lines[\count($lines) - 1]));
    }

    /**
     * The viewport measures in logical lines and keeps at least one so the
     * cursor line is drawn; a single line can wrap to more rows than the
     * editor has.
     */
    public function testAWrappedLineDoesNotRenderMoreRowsThanTheEditorHas()
    {
        $docLines = [str_repeat('word ', 40), 'second line', 'third line'];

        foreach ([40, 20, 10, 6] as $columns) {
            foreach ([1, 3, 5] as $maxDisplayRows) {
                $viewport = [
                    'scroll_offset' => 0,
                    'visible_line_count' => 1,
                    'lines_above' => 0,
                    'lines_below' => 2,
                ];

                $lines = $this->renderWithViewport($docLines, $viewport, 0, 0, $columns, $maxDisplayRows);

                // Two frame rows on top of the content.
                $this->assertLessThanOrEqual($maxDisplayRows + 2, \count($lines), \sprintf('%d columns, %d rows allowed.', $columns, $maxDisplayRows));
            }
        }
    }

    public function testTheCursorStaysOnScreenWhenItsLineIsCutDown()
    {
        $docLines = [str_repeat('word ', 40)];
        $viewport = [
            'scroll_offset' => 0,
            'visible_line_count' => 1,
            'lines_above' => 0,
            'lines_below' => 0,
        ];

        // The cursor sits at the very end, which wraps far past the third row.
        $lines = $this->renderWithViewport($docLines, $viewport, 0, \strlen($docLines[0]) - 1, 10, 3, false, true);

        $this->assertCount(5, $lines);
        $this->assertStringContainsString(AnsiUtils::CURSOR_MARKER_PREFIX, implode('', $lines), 'The window follows the cursor instead of showing the top of the line.');
    }

    /**
     * @param string[] $docLines
     *
     * @return string[]
     */
    private function renderSimple(array $docLines, int $cursorLine, int $cursorCol, int $columns, int $maxDisplayRows, bool $verticallyExpanded = false, bool $focused = false): array
    {
        $viewport = [
            'scroll_offset' => 0,
            'visible_line_count' => \count($docLines),
            'lines_above' => 0,
            'lines_below' => 0,
        ];

        return $this->renderWithViewport($docLines, $viewport, $cursorLine, $cursorCol, $columns, $maxDisplayRows, $verticallyExpanded, $focused);
    }

    /**
     * @param string[]                                                                          $docLines
     * @param array{scrollOffset: int, visibleLineCount: int, linesAbove: int, linesBelow: int} $viewport
     *
     * @return string[]
     */
    private function renderWithViewport(array $docLines, array $viewport, int $cursorLine, int $cursorCol, int $columns, int $maxDisplayRows, bool $verticallyExpanded = false, bool $focused = false): array
    {
        return $this->renderer->render(
            $docLines,
            $viewport,
            $cursorLine,
            $cursorCol,
            $columns,
            $maxDisplayRows,
            $verticallyExpanded,
            $focused,
            CursorShape::Block,
            new Style(),
        );
    }
}
