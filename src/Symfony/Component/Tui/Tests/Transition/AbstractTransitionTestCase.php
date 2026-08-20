<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Transition;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Transition\TransitionInterface;

abstract class AbstractTransitionTestCase extends TestCase
{
    protected array $fromLines;
    protected array $toLines;

    /**
     * @var list<string>
     */
    protected array $fromCells;

    /**
     * @var list<string>
     */
    protected array $toCells;
    protected int $width = 10;
    protected int $height = 5;

    protected function setUp(): void
    {
        $this->fromLines = array_fill(0, $this->height, 'AAAAAAAAAA');
        $this->toLines = array_fill(0, $this->height, 'BBBBBBBBBB');

        // Uniform screens only tell which screen a cell came from. These carry coordinates
        // instead: the letter advances with both column and row, lowercase for "from" and
        // uppercase for "to", so a cell that moves by one column or one row changes character.
        $this->fromCells = [];
        $this->toCells = [];
        for ($y = 0; $y < $this->height; ++$y) {
            $row = '';
            for ($x = 0; $x < $this->width; ++$x) {
                $row .= \chr(\ord('a') + $y + $x);
            }
            $this->fromCells[] = $row;
            $this->toCells[] = strtoupper($row);
        }
    }

    /**
     * Asserts every cell was taken from its own coordinates in one screen or the other.
     *
     * Transitions that reveal in place must never move a cell: each (x, y) holds either the
     * "from" or the "to" character for that exact position. This fails both on a blend that
     * invents content and on one that is off by a column or a row.
     *
     * @param list<string> $lines
     */
    protected function assertCellsKeepTheirCoordinates(array $lines, string $message = ''): void
    {
        $this->assertCount($this->height, $lines, $message ?: 'The blend must return one line per row.');

        for ($y = 0; $y < $this->height; ++$y) {
            for ($x = 0; $x < $this->width; ++$x) {
                $actual = AnsiUtils::sliceByColumn($lines[$y], $x, 1);
                $expected = [$this->fromCells[$y][$x], $this->toCells[$y][$x]];

                $this->assertContains($actual, $expected, $message ?: \sprintf('Cell (%d, %d) holds "%s", which belongs to neither screen at that position (expected "%s" or "%s").', $x, $y, $actual, $expected[0], $expected[1]));
            }
        }
    }

    /**
     * Asserts the blend keeps every line at the requested width when both screens hold
     * double-width graphemes.
     *
     * The graphemes sit at different columns on every row, so sweeping the progress makes
     * the slicing boundaries land inside them: a slice that drops or duplicates a column
     * there shifts the whole region and wraps the terminal.
     */
    protected function assertWideGraphemesKeepTheLineWidth(TransitionInterface $transition): void
    {
        $width = 12;
        $height = 4;
        $from = [];
        $to = [];
        for ($y = 0; $y < $height; ++$y) {
            $from[] = str_repeat('a', $y)."\u{1F600}".str_repeat('b', 8 - $y)."\u{1F600}";
            $to[] = str_repeat('X', 2 + $y)."\u{1F600}".str_repeat('Y', 6 - $y)."\u{1F600}";
        }

        foreach ([...$from, ...$to] as $line) {
            $this->assertSame($width, AnsiUtils::visibleWidth($line));
        }

        for ($i = 1; $i < 20; ++$i) {
            $progress = $i / 20;
            foreach ($transition->blend($from, $to, $width, $height, $progress) as $lineNo => $line) {
                $this->assertSame($width, AnsiUtils::visibleWidth($line), \sprintf('Line %d does not measure %d columns at progress %.2f.', $lineNo, $width, $progress));
            }
        }
    }

    protected function assertLinesMatch(array $expected, array $actual, string $message = ''): void
    {
        $this->assertSame($expected, $actual, $message);
    }

    /**
     * Asserts that a specific character is at a given position in the resulting lines.
     */
    protected function assertCharAt(int $x, int $y, string $expected, array $lines, string $message = ''): void
    {
        $line = $lines[$y] ?? '';
        $char = AnsiUtils::sliceByColumn($line, $x, 1);

        $this->assertSame($expected, $char, $message ?: \sprintf("Character at (%d, %d) should be '%s', got '%s'.", $x, $y, $expected, $char));
    }
}
