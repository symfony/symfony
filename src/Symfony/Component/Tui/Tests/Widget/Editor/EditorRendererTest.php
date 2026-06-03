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
