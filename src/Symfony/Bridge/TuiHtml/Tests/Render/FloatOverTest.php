<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Render;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Render\CellBuffer;
use Symfony\Bridge\TuiHtml\Render\LayoutEngine;
use Symfony\Component\Tui\Ansi\AnsiCodeTracker;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Terminal\ScreenBuffer;

/**
 * A float drawn over a row of what flows under it, its blank cells showing it, like LayoutEngine::layoutFloats() does,
 * checked against random rows and floats: the cells as a composition of the two draws them, each sequence that is no
 * cell once, the markers of a cell at their columns.
 */
final class FloatOverTest extends TestCase
{
    /** Sequences that are no cells besides those that mark a cell, see marker() */
    private const OTHERS = ["\x1b]5113;r1\x07", "\x1b[2 q"];
    /** Those a space draws, a background, a line across it, its colors swapped, and the others it does not draw */
    private const STYLES = ["\x1b[0m", "\x1b[44m", "\x1b[49m", "\x1b[48:2::255:0:0m", "\x1b[7m", "\x1b[27m", "\x1b[4m", "\x1b[21m", "\x1b[4:3m", "\x1b[4:0m", "\x1b[24m", "\x1b[9m", "\x1b[29m", "\x1b[53m", "\x1b[55m", "\x1b[1m", "\x1b[2m", "\x1b[3m", "\x1b[22m", "\x1b[23m", "\x1b[5m", "\x1b[8m", "\x1b[28m", "\x1b[31m", "\x1b[38;5;44m", "\x1b[38:2::1:2:3m", "\x1b[38:2:1:2m"];
    /**
     * A pattern of the sequences that mark a cell: the cursor, text drawn larger, an image of kitty or iTerm2, or a
     * command of kitty that draws no image, like one deleting them.
     */
    private const MARKED = '\x1b_pi:[cs];\d+\x07|\x1b_Ga=[Ttpd],[iN]=\d+(?:,c=\d+,r=\d+)?(?:;AAAA)?\x1b\\\\|\x1b\]1337;File=inline=1;name=\d+(?:;width=\d+;height=\d+)?:AAAA\x07';

    private static \Random\Randomizer $random;
    private const LINKS = ["\x1b]8;;https://example.com\x07", "\x1b]8;;\x07"];

    public function testAFloatIsDrawnOverARowLikeACompositionOfTheirCells()
    {
        $cases = (int) (getenv('TUI_FLOAT_FUZZ') ?: 2000);
        self::$random = new \Random\Randomizer(new \Random\Engine\Mt19937(68146));
        for ($case = 0; $case < $cases; ++$case) {
            $columns = self::$random->getInt(12, 30);
            $row = self::randomLine($columns, true);
            $width = self::$random->getInt(1, 8);
            $float = self::randomLine($width, false);
            $x = self::$random->getInt(-3, $columns + 1);
            $opaque = 0 === self::$random->getInt(0, 4);
            $this->check($row, $float, $x, $width, $columns, $opaque, \sprintf('case %d: %s', $case, json_encode([$row, $float, $x, $columns, $opaque])));
        }
    }

    public function testOnlyWhatDrawsOnASpaceHidesWhatIsUnderIt()
    {
        // Italic, bold, dim, blink or hidden spaces draw nothing
        foreach (["\x1b[3m", "\x1b[1m", "\x1b[2m", "\x1b[5m", "\x1b[8m", "\x1b[38:2:1:2m"] as $style) {
            $this->assertSame('│abcdefgh│', AnsiUtils::stripAnsiCodes(self::over('│abcdefgh│', $style.'          '."\x1b[0m", 0, 10, false)), json_encode($style));
        }
        // A line across them or their colors swapped do
        foreach (["\x1b[4m", "\x1b[21m", "\x1b[4:3m", "\x1b[9m", "\x1b[53m", "\x1b[7m", "\x1b[44m"] as $style) {
            $this->assertSame('│   defgh│', AnsiUtils::stripAnsiCodes(self::over('│abcdefgh│', $style.'   '."\x1b[0m", 1, 10, false)), json_encode($style));
        }
    }

    public function testAnImageIsKeptAtItsColumn()
    {
        $image = "\x1b_Ga=T,i=1;AAAA\x1b\\";
        // Under a cell of the float that draws nothing, at its column
        $drawn = self::over('ab'.$image.'cdefghij', '   ', 1, 10, false);
        $this->assertSame(2, AnsiUtils::visibleWidth(substr($drawn, 0, (int) strpos($drawn, $image))));
        // In the float: over its columns, what is under it not printed into the image, opaque or not
        $this->assertSame('ab   fghij', AnsiUtils::stripAnsiCodes(self::over('abcdefghij', $image.'   ', 2, 10, false)));
        $this->assertSame('ab   fghij', AnsiUtils::stripAnsiCodes(self::over('abcdefghij', "\x1b]1337;File=inline=1:AAAA\x07".'   ', 2, 10, false)));
        $this->assertSame('ab   fghij', AnsiUtils::stripAnsiCodes(self::over('abcdefghij', "\x1b_Ga=p,i=1\x1b\\".'   ', 2, 10, false)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideImagesOfTheEncoders(): iterable
    {
        // As KittyGraphicsProtocol::encode() and ITerm2Protocol::encode() of the Console component write them: no id, no
        // rows, in chunks when large
        yield 'kitty' => ["\x1b_Ga=T,f=100,c=4;AAAA\x1b\\"];
        yield 'kitty in chunks' => ["\x1b_Ga=T,f=100,c=4,m=1;AAAA\x1b\\\x1b_Gm=1;AAAA\x1b\\\x1b_Gm=0;AAAA\x1b\\"];
        yield 'iTerm2' => ["\x1b]1337;File=inline=1;width=4;preserveAspectRatio=1:AAAA\x07"];
    }

    #[DataProvider('provideImagesOfTheEncoders')]
    public function testAnImageStartingUnderACellAFloatDrawsIsNotDrawn(string $image)
    {
        $this->assertSame("a\x1b[0mXYZ\x1b[0m\x1b]8;;\x07efghij", self::over('ab'.$image.'cdefghij', 'XYZ', 1, 10, false));
        $this->assertSame("a\x1b[0m   \x1b[0m\x1b]8;;\x07efghij", self::over('ab'.$image.'cdefghij', '   ', 1, 10, true));
        // Starting under one that shows what is under it: at its column
        $drawn = self::over('ab'.$image.'cdefghij', 'X  ', 1, 10, false);
        $this->assertSame(2, AnsiUtils::visibleWidth(substr($drawn, 0, (int) strpos($drawn, $image))));
    }

    public function testTheImagesOfTheEncodersAreNotDrawnUnderACellAFloatDraws()
    {
        $kitty = 'Symfony\Component\Console\Terminal\Image\KittyGraphicsProtocol';
        $iterm = 'Symfony\Component\Console\Terminal\Image\ITerm2Protocol';
        if (!class_exists($kitty) || !class_exists($iterm)) {
            $this->markTestSkipped('The Console component is not installed.');
        }
        $png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        foreach ([(new $kitty())->encode($png, 4), (new $kitty())->encode(str_repeat($png, 400), 4), (new $iterm())->encode($png, 4)] as $image) {
            $this->assertNotSame('', $image);
            $this->assertSame("a\x1b[0mXYZ\x1b[0m\x1b]8;;\x07efghij", self::over('ab'.$image.'cdefghij', 'XYZ', 1, 10, false));
        }
    }

    public function testAnImageOfAnIdNotDrawnIsTransmittedStill()
    {
        $drawn = self::over("ab\x1b_Ga=T,i=7,f=100,m=1;AA\x1b\\\x1b_Gm=0;AA\x1b\\cdefghij", 'XYZ', 1, 10, false);
        $this->assertSame("a\x1b[0mXYZ\x1b[0m\x1b]8;;\x07\x1b_Ga=t,i=7,f=100,m=1;AA\x1b\\\x1b_Gm=0;AA\x1b\\efghij", $drawn);
        $drawn = self::over("ab\x1b_Gf=100,I=3,a=T;AA\x1b\\cdefghij", 'XYZ', 1, 10, false);
        $this->assertSame("a\x1b[0mXYZ\x1b[0m\x1b]8;;\x07\x1b_Gf=100,I=3,a=t;AA\x1b\\efghij", $drawn);
        // Put only: not drawn, nothing to transmit
        $this->assertSame("a\x1b[0mXYZ\x1b[0m\x1b]8;;\x07efghij", self::over("ab\x1b_Ga=p,i=7\x1b\\cdefghij", 'XYZ', 1, 10, false));
    }

    public function testTheTabsOfAFloatShowWhatIsUnderThem()
    {
        $drawn = self::over("abc\x1b_pi:c\x07defghij", "X\tY", 2, 10, false);
        $this->assertSame('abXdefYhij', AnsiUtils::stripAnsiCodes($drawn));
        $this->assertSame(3, AnsiUtils::visibleWidth(substr($drawn, 0, (int) strpos($drawn, "\x1b_pi:c"))));
    }

    public function testAWideCharacterOfAFloatCutAtItsEdgeShowsWhatIsUnderIt()
    {
        $over = new \ReflectionMethod(LayoutEngine::class, 'floatOver');
        $drawn = $over->invoke(null, "ab\x1b_pi:c\x07cdefghij", "X \u{65e5}", 0, 3, 10, false);
        $this->assertSame('Xbcdefghij', AnsiUtils::stripAnsiCodes($drawn));
        $this->assertSame(2, AnsiUtils::visibleWidth(substr($drawn, 0, (int) strpos($drawn, "\x1b_pi:c"))));
        $this->assertSame('XYcdefghij', AnsiUtils::stripAnsiCodes($over->invoke(null, 'abcdefghij', "XY\u{65e5}", 0, 3, 10, false)));
    }

    public function testACommandOfTheTerminalInAFloatHidesNothing()
    {
        foreach (["\x1b_Ga=d,d=a\x1b\\", "\x1b_Ga=q,i=1;AAAA\x1b\\", "\x1b_Ga=t,i=1;AAAA\x1b\\"] as $command) {
            $this->assertSame('abcdefghij', AnsiUtils::stripAnsiCodes(self::over('abcdefghij', $command.'   ', 2, 10, false)), json_encode($command));
        }
    }

    public function testABackgroundGivenWithColonsHidesWhatIsUnderIt()
    {
        foreach (["\x1b[48:2::1:2:3:0:0m", "\x1b[48:5:m", "\x1b[48:2:1:2:3m"] as $background) {
            $this->assertSame('ab   fghij', AnsiUtils::stripAnsiCodes(self::over('abcdefghij', $background.'   '."\x1b[0m", 2, 10, false)), json_encode($background));
        }
    }

    public function testHiddenCharactersShowWhatIsUnderThem()
    {
        $this->assertSame('abcdefghij', AnsiUtils::stripAnsiCodes(self::over('abcdefghij', "\x1b[8mXYZ\x1b[0m", 2, 10, false)));
        $this->assertSame('abcdefghij', AnsiUtils::stripAnsiCodes(self::over('abcdefghij', "\x1b[1;8mXYZ\x1b[0m", 2, 10, false)));
        $this->assertSame('abcdefghij', AnsiUtils::stripAnsiCodes(self::over('abcdefghij', "\x1b[08mXYZ\x1b[0m", 2, 10, false)));
        $this->assertSame('abXYZfghij', AnsiUtils::stripAnsiCodes(self::over('abcdefghij', "\x1b[38;5;8mXYZ\x1b[0m", 2, 10, false)));
        $this->assertSame('abXdefghij', AnsiUtils::stripAnsiCodes(self::over('abcdefghij', "\x1b[8;4mX\x1b[24mYZ\x1b[0m", 2, 10, false)), 'Underlined, it draws.');
        $this->assertSame('abXYZfghij', AnsiUtils::stripAnsiCodes(self::over('abcdefghij', "\x1b[8mXYZ\x1b[0m", 2, 10, true)), 'Hidden in an opaque box, drawn as the cells hold it.');
    }

    public function testAWideCharacterAcrossAnEdgeAfterAMarkOfNoCellIsDrawnWhole()
    {
        $this->assertStringContainsString('中', AnsiUtils::stripAnsiCodes(self::over("a\x1b[1m\u{301}中cdef", '  ', 0, 6, false)));
    }

    public function testTheTextAlternativeOfAnImageFloatedHidesWhatIsUnderIt()
    {
        $drawn = self::over('abcdefghij', '[IMG]', 2, 10, true);
        $this->assertSame('ab[IMG]hij', AnsiUtils::stripAnsiCodes($drawn));
        $drawn = self::over('abcdefghij', ' IMG ', 2, 10, true);
        $this->assertSame('ab IMG hij', AnsiUtils::stripAnsiCodes($drawn), 'Its blank cells too, an opaque box.');
    }

    private function check(string $row, string $float, int $x, int $width, int $columns, bool $opaque, string $message): void
    {
        $drawn = self::over($row, $float, $x, $columns, $opaque);
        [$plainRow] = self::sequences($row);
        [$plainFloat, $floatSequences] = self::sequences($float);
        // A row of it holding an image drawn by the terminal: over its columns, what is under it not printed into it
        $opaque = $opaque || array_any($floatSequences, static fn (array $sequence): bool => self::isImage($sequence[1]));
        // The cells: those of the row, the float written over them, transparent or not, past the edges cut
        $expected = new CellBuffer($columns, 1);
        $expected->writeAnsiLines([self::withoutLinks($plainRow)]);
        $visible = max(0, min($width, $columns - max(0, $x)) - max(0, -$x));
        if ($visible > 0) {
            $expected->writeAnsiLines([AnsiUtils::sliceToWidth(self::withoutLinks($plainFloat), max(0, -$x), $visible)], 0, max(0, $x), !$opaque);
        }
        [$plainDrawn, $drawnSequences] = self::sequences($drawn);
        $this->assertSame($columns, AnsiUtils::visibleWidth($plainDrawn), $message.': as wide as the row');
        $this->assertSame(self::cells(implode('', $expected->toLines())), self::cells(self::withoutLinks($plainDrawn)), $message.': the cells');
        $start = max(0, $x);
        $end = $start + $visible;
        // The cells of the float that show what is under them, as a composition tells them: those that leave a row of
        // a character it holds nowhere as it is; none of an opaque one
        $shows = [];
        if (!$opaque && $visible > 0) {
            $sentinel = new CellBuffer($columns, 1);
            $sentinel->writeAnsiLines([str_repeat('#', $columns)]);
            $sentinel->writeAnsiLines([AnsiUtils::sliceToWidth(self::withoutLinks($plainFloat), max(0, -$x), $visible)], 0, $start, true);
            foreach (self::cells(implode('', $sentinel->toLines())) as $column => [$character]) {
                $shows[$column] = '#' === $character;
            }
        }
        // Each sequence that is no cell once, the float's dropped only when none of it shows; an image of the row starting
        // under a cell the float draws not drawn, only transmitted when it has an id
        [, $rowSequences] = self::sequences($row);
        $covered = static fn (array $sequence): bool => self::isImage($sequence[1]) && $sequence[0] >= $start && $sequence[0] < $end && !($shows[$sequence[0]] ?? false);
        $kept = static fn (string $sequence): ?string => str_starts_with($sequence, "\x1b_Ga=T,i=") ? "\x1b_Ga=t,".substr($sequence, 7) : null;
        $wanted = [];
        foreach ($visible > 0 ? [...$rowSequences, ...$floatSequences] : $rowSequences as $sequence) {
            if (\in_array($sequence, $rowSequences, true) && $covered($sequence)) {
                if (null !== $sequence[1] = $kept($sequence[1])) {
                    $wanted[] = $sequence;
                }
                continue;
            }
            $wanted[] = $sequence;
        }
        $wanted = array_count_values(array_column($wanted, 1));
        $found = array_count_values(array_column($drawnSequences, 1));
        ksort($wanted);
        ksort($found);
        $this->assertSame($wanted, $found, $message.': each sequence once');
        // The markers of a cell under it at their columns where it shows what is under it, after it where it covers them
        if ($visible <= 0) {
            return;
        }
        // After it, past a wide character of the row across its right edge, drawn whole where it shows what is under it
        $buffer = new ScreenBuffer($columns, 1);
        $buffer->write(self::withoutLinks($plainRow));
        $past = $end < $columns && '' === ($buffer->getCells()[0][$end]['char'] ?? null) && ($shows[$end - 1] ?? false) ? 1 : 0;
        foreach ($rowSequences as $index => [$column, $sequence]) {
            if (!preg_match('/^(?:'.self::MARKED.')$/', $sequence) || $column < $start || $column >= $end) {
                continue;
            }
            // Covered, transmitted only after it, or not at all
            if ($covered([$column, $sequence])) {
                if (null !== $transmitted = $kept($sequence)) {
                    $at = array_values(array_filter($drawnSequences, static fn (array $drawn): bool => $drawn[1] === $transmitted))[0][0] ?? null;
                    $this->assertSame($end + $past, $at, $message.': the column of an image transmitted only');
                }
                continue;
            }
            $at = self::occurrence($drawnSequences, $rowSequences, $index);
            $this->assertSame(($shows[$column] ?? false) ? $column : $end + $past, $at, $message.': the column of a marker');
        }
    }

    /**
     * Whether a sequence draws an image at the cursor: kitty's displaying one, iTerm2's drawn inline.
     */
    private static function isImage(string $sequence): bool
    {
        return str_starts_with($sequence, "\x1b_Ga=T,") || str_starts_with($sequence, "\x1b_Ga=p,") || str_starts_with($sequence, "\x1b]1337;File=");
    }

    /**
     * A sequence that marks a cell: of the cursor or of text drawn larger, or an image of kitty or iTerm2, each told
     * apart from the others by its number.
     */
    private static function marker(): string
    {
        static $count = 0;
        ++$count;

        // Of a size, in cells and rows, or not
        $cells = self::$random->getInt(1, 5);
        $rows = self::$random->getInt(1, 2);
        $sized = 0 !== self::$random->getInt(0, 2);

        return match (self::$random->getInt(0, 5)) {
            0 => "\x1b_pi:c;$count\x07",
            1 => "\x1b_pi:s;$count\x07",
            2 => "\x1b_Ga=T,".(self::$random->getInt(0, 1) ? 'i' : 'N')."=$count".($sized ? ",c=$cells,r=$rows" : '').";AAAA\x1b\\",
            3 => "\x1b_Ga=p,i=$count".($sized ? ",c=$cells,r=$rows" : '')."\x1b\\",
            4 => "\x1b_Ga=d,i=$count\x1b\\",
            default => "\x1b]1337;File=inline=1;name=$count".($sized ? ";width=$cells;height=$rows" : '').":AAAA\x07",
        };
    }

    private static function over(string $row, string $float, int $x, int $columns, bool $opaque): string
    {
        return (new \ReflectionMethod(LayoutEngine::class, 'floatOver'))->invoke(null, $row, $float, $x, AnsiUtils::visibleWidth($float), $columns, $opaque);
    }

    /**
     * A random line exactly as wide as columns: text, wide characters, tabs, styles, links and sequences that are no
     * cells.
     */
    private static function randomLine(int $columns, bool $row): string
    {
        $line = '';
        $width = 0;
        while ($width < $columns) {
            $kind = self::$random->getInt(0, 9);
            $room = $columns - $width;
            if ($kind <= 3) {
                $letter = $row || 0 === self::$random->getInt(0, 1);
                $line .= $letter ? \chr(self::$random->getInt($row ? 97 : 65, $row ? 122 : 90)) : ' ';
                ++$width;
            } elseif (4 === $kind && $room >= 2) {
                $line .= ['中', '😀', '日'][self::$random->getInt(0, 2)];
                $width += 2;
            } elseif (5 === $kind && $room >= AnsiUtils::TAB_WIDTH) {
                $line .= "\t";
                $width += AnsiUtils::TAB_WIDTH;
            } elseif (6 === $kind) {
                $line .= self::STYLES[self::$random->getInt(0, \count(self::STYLES) - 1)];
            } elseif (7 === $kind && $row) {
                $line .= self::LINKS[self::$random->getInt(0, 1)];
            } elseif (8 === $kind) {
                $line .= self::marker();
            } elseif (9 === $kind) {
                $line .= self::OTHERS[self::$random->getInt(0, \count(self::OTHERS) - 1)];
            }
        }

        return $line;
    }

    /**
     * A line without its sequences that are no cells, and those with their columns.
     *
     * @return array{string, list<array{int, string}>}
     */
    private static function sequences(string $line): array
    {
        $plain = '';
        $sequences = [];
        $column = 0;
        $pattern = '/('.self::MARKED.'|'.implode('|', array_map(static fn (string $sequence): string => preg_quote($sequence, '/'), self::OTHERS)).')/';
        foreach (preg_split($pattern, $line, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if (preg_match('/^(?:'.self::MARKED.')$/', $part) || \in_array($part, self::OTHERS, true)) {
                $sequences[] = [$column, $part];
                continue;
            }
            $plain .= $part;
            $column += AnsiUtils::visibleWidth(str_replace("\t", str_repeat(' ', AnsiUtils::TAB_WIDTH), $part));
        }

        return [$plain, $sequences];
    }

    /**
     * The column the sequence of the row at an index is drawn at: that of its occurrence among those alike, in order.
     *
     * @param list<array{int, string}> $drawn
     * @param list<array{int, string}> $row
     */
    private static function occurrence(array $drawn, array $row, int $index): ?int
    {
        $nth = \count(array_filter(\array_slice($row, 0, $index), static fn (array $sequence): bool => $sequence[1] === $row[$index][1]));
        foreach ($drawn as [$column, $sequence]) {
            if ($sequence === $row[$index][1] && 0 === $nth--) {
                return $column;
            }
        }

        return null;
    }

    private static function withoutLinks(string $line): string
    {
        return (string) preg_replace('/\x1b\]8;[^\x07\x1b]*\x07/', '', str_replace("\t", str_repeat(' ', AnsiUtils::TAB_WIDTH), $line));
    }

    /**
     * The cells of a line, each its character and what shows of its style: all of it for a glyph, what paints for a
     * space, like a background, a hidden character a space.
     *
     * @return list<array{string, string}>
     */
    private static function cells(string $line): array
    {
        $width = max(1, AnsiUtils::visibleWidth($line));
        $buffer = new ScreenBuffer($width, 1);
        $buffer->write($line);
        $cells = [];
        foreach ($buffer->getCells()[0] ?? [] as $cell) {
            $tracker = new AnsiCodeTracker();
            $tracker->processText($cell['style']);
            // A color with colons as with semicolons; a styled underline as an underline, which compositions draw
            $style = (string) preg_replace(['/([345]8):2::?(\d+):(\d+):(\d+)/', '/([345]8):5:(\d+)/', '/(?<=[\[;])4:[1-5](?=[;m])/'], ['$1;2;$2;$3;$4', '$1;5;$2', '4'], $tracker->getActiveCodes());
            // A hidden character drawn as a space
            $blank = ' ' === $cell['char'] || '' === $cell['char'] || $tracker->isHidden();
            $cells[] = [$tracker->isHidden() ? ' ' : $cell['char'], !$blank || $tracker->paintsCells() ? $style : ''];
        }

        return $cells;
    }
}
