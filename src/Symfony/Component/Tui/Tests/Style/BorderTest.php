<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Style;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\BorderPattern;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\GradientDirection;
use Symfony\Component\Tui\Style\Style;

class BorderTest extends TestCase
{
    public function testNegativeValuesClampedToZero()
    {
        $border = new Border(-5, -3, -1, -2);

        $this->assertSame(0, $border->top);
        $this->assertSame(0, $border->right);
        $this->assertSame(0, $border->bottom);
        $this->assertSame(0, $border->left);
    }

    /**
     * @param list<int> $input
     */
    #[DataProvider('fromArrayProvider')]
    public function testFromArray(array $input, int $top, int $right, int $bottom, int $left)
    {
        $border = Border::from($input);

        $this->assertSame($top, $border->top);
        $this->assertSame($right, $border->right);
        $this->assertSame($bottom, $border->bottom);
        $this->assertSame($left, $border->left);
    }

    /**
     * @return iterable<string, array{list<int>, int, int, int, int}>
     */
    public static function fromArrayProvider(): iterable
    {
        yield '1 element (all sides)' => [[3], 3, 3, 3, 3];
        yield '2 elements (y, x)' => [[1, 2], 1, 2, 1, 2];
        yield '3 elements (top, x, bottom)' => [[1, 2, 3], 1, 2, 3, 2];
        yield '4 elements (top, right, bottom, left)' => [[1, 2, 3, 4], 1, 2, 3, 4];
    }

    public function testFromBorderInstance()
    {
        $original = new Border(1, 2, 3, 4);
        $result = Border::from($original);

        $this->assertSame($original, $result);
    }

    public function testFromBorderInstanceWithPattern()
    {
        $original = new Border(1, 2, 3, 4);
        $result = Border::from($original, BorderPattern::ROUNDED);

        $this->assertNotSame($original, $result);
        $this->assertSame(1, $result->top);
        $this->assertSame(2, $result->right);
        $this->assertSame(3, $result->bottom);
        $this->assertSame(4, $result->left);
        $this->assertSame(
            BorderPattern::rounded()->getChars(),
            $result->pattern->getChars(),
        );
    }

    public function testFromBorderInstanceWithColor()
    {
        $original = new Border(1, 2, 3, 4);
        $result = Border::from($original, color: '#ff0000');

        $this->assertNotSame($original, $result);
        $this->assertSame(1, $result->top);
        $this->assertSame(2, $result->right);
        $this->assertSame(3, $result->bottom);
        $this->assertSame(4, $result->left);
        $this->assertSame(Color::from('#ff0000')->toRgb(), $result->color->toRgb());
    }

    /**
     * @param list<int> $input
     */
    #[DataProvider('invalidFromArrayProvider')]
    public function testFromInvalidArray(array $input)
    {
        $this->expectException(InvalidArgumentException::class);
        Border::from($input);
    }

    /**
     * @return iterable<string, array{list<int>}>
     */
    public static function invalidFromArrayProvider(): iterable
    {
        yield 'empty array' => [[]];
        yield '5 elements' => [[1, 2, 3, 4, 5]];
    }

    #[DataProvider('factoryMethodProvider')]
    public function testFactoryMethods(Border $border, int $top, int $right, int $bottom, int $left)
    {
        $this->assertSame($top, $border->top);
        $this->assertSame($right, $border->right);
        $this->assertSame($bottom, $border->bottom);
        $this->assertSame($left, $border->left);
    }

    /**
     * @return iterable<string, array{Border, int, int, int, int}>
     */
    public static function factoryMethodProvider(): iterable
    {
        yield 'all(5)' => [Border::all(5), 5, 5, 5, 5];
        yield 'xy(3, 1)' => [Border::xy(3, 1), 1, 3, 1, 3];
        yield 'xy(5) default y' => [Border::xy(5), 0, 5, 0, 5];
    }

    /**
     * @param array<mixed> $expectedChars
     * @param array<mixed> $expectedRgb
     */
    #[DataProvider('factoryWithPatternAndColorProvider')]
    public function testFactoryWithPatternAndColor(Border $border, int $top, int $right, int $bottom, int $left, array $expectedChars, array $expectedRgb)
    {
        $this->assertSame($top, $border->top);
        $this->assertSame($right, $border->right);
        $this->assertSame($bottom, $border->bottom);
        $this->assertSame($left, $border->left);
        $this->assertSame($expectedChars, $border->pattern->getChars());
        $this->assertSame($expectedRgb, $border->color->toRgb());
    }

    /**
     * @return iterable<string, array{Border, int, int, int, int, array<mixed>, array<mixed>}>
     */
    public static function factoryWithPatternAndColorProvider(): iterable
    {
        yield 'from() with pattern and color' => [
            Border::from([1], BorderPattern::ROUNDED, '#ff0000'),
            1, 1, 1, 1,
            BorderPattern::rounded()->getChars(),
            Color::from('#ff0000')->toRgb(),
        ];
        yield 'all() with pattern and color' => [
            Border::all(2, BorderPattern::DOUBLE, '#00ff00'),
            2, 2, 2, 2,
            BorderPattern::double()->getChars(),
            Color::from('#00ff00')->toRgb(),
        ];
        yield 'xy() with pattern and color' => [
            Border::xy(3, 1, BorderPattern::TALL, 'red'),
            1, 3, 1, 3,
            BorderPattern::tall()->getChars(),
            Color::from('red')->toRgb(),
        ];
    }

    // --- wrapLines ---

    /**
     * @param list<string> $innerLines
     */
    #[DataProvider('wrapLinesCountProvider')]
    public function testWrapLinesLineCount(Border $border, array $innerLines, int $width, int $expectedCount)
    {
        $result = $border->wrapLines($innerLines, $width, new Style());
        $this->assertCount($expectedCount, $result);
    }

    /**
     * @return iterable<string, array{Border, list<string>, int, int}>
     */
    public static function wrapLinesCountProvider(): iterable
    {
        yield 'all sides = 1' => [Border::all(1, BorderPattern::NONE), ['content'], 7, 3];
        yield 'no border' => [new Border(0, 0, 0, 0, BorderPattern::NONE), ['line1', 'line2'], 5, 2];
        yield 'top only' => [new Border(1, 0, 0, 0, BorderPattern::NONE), ['content'], 7, 2];
        yield 'bottom only' => [new Border(0, 0, 1, 0, BorderPattern::NONE), ['content'], 7, 2];
        yield 'multiple top rows' => [new Border(3, 0, 0, 0, BorderPattern::NONE), ['content'], 7, 4];
        yield 'multiple bottom rows' => [new Border(0, 0, 2, 0, BorderPattern::NONE), ['content'], 7, 3];
        yield 'empty inner lines' => [Border::all(1, BorderPattern::NONE), [], 5, 2];
        yield 'asymmetric (1,2,3,4)' => [new Border(1, 2, 3, 4), ['text'], 4, 5];
        yield 'zero width border' => [Border::all(0, BorderPattern::ROUNDED, '#ff0000'), ['line1', 'line2'], 5, 2];
    }

    /**
     * @param string[] $expectedTopChars
     * @param string[] $expectedBottomChars
     */
    #[DataProvider('wrapLinesPatternProvider')]
    public function testWrapLinesWithPattern(string $pattern, string $expectedSideChar, array $expectedTopChars, array $expectedBottomChars)
    {
        $border = Border::all(1, $pattern);
        $innerStyle = new Style();

        $result = $border->wrapLines(['hello'], 5, $innerStyle);

        $this->assertCount(3, $result);
        foreach ($expectedTopChars as $char) {
            $this->assertStringContainsString($char, $result[0]);
        }
        $this->assertStringContainsString($expectedSideChar, $result[1]);
        $this->assertStringContainsString('hello', $result[1]);
        foreach ($expectedBottomChars as $char) {
            $this->assertStringContainsString($char, $result[2]);
        }
    }

    /**
     * @return iterable<string, array{string, string, string[], string[]}>
     */
    public static function wrapLinesPatternProvider(): iterable
    {
        yield 'normal' => [BorderPattern::NORMAL, '│', ['─'], ['─']];
        yield 'double' => [BorderPattern::DOUBLE, '║', ['═'], ['═']];
        yield 'rounded' => [BorderPattern::ROUNDED, '│', ['╭', '╮'], ['╰', '╯']];
    }

    public function testWrapLinesWithOuterStyle()
    {
        $border = Border::all(1);
        $innerStyle = new Style();
        $outerStyle = new Style();

        $result = $border->wrapLines(['text'], 4, $innerStyle, $outerStyle);

        $this->assertCount(3, $result);
    }

    public function testWrapLinesWithNoLeftRightBorder()
    {
        $border = new Border(1, 0, 1, 0);
        $innerStyle = new Style();

        $result = $border->wrapLines(['content'], 7, $innerStyle);

        // 1 top + 1 content + 1 bottom = 3
        $this->assertCount(3, $result);
        // Middle row should contain content without left/right border chars
        $this->assertStringContainsString('content', $result[1]);
    }

    public function testWrapLinesWithBorderColor()
    {
        $border = Border::all(1, BorderPattern::NORMAL, '#ff0000');
        $innerStyle = new Style();

        $result = $border->wrapLines(['text'], 4, $innerStyle);

        $this->assertCount(3, $result);
        // The border color should be applied
        $this->assertStringContainsString("\x1b[38;2;255;0;0m", $result[0]);
    }

    public function testWrapLinesUsesInnerStyleColorWhenNoBorderColor()
    {
        $border = Border::all(1);
        $innerStyle = new Style()->withColor('#00ff00');

        $result = $border->wrapLines(['text'], 4, $innerStyle);

        $this->assertCount(3, $result);
    }

    public function testWrapLinesWithMultipleInnerLines()
    {
        $border = Border::all(1);
        $innerStyle = new Style();

        $result = $border->wrapLines(['line1', 'line2', 'line3'], 5, $innerStyle);

        // 1 top + 3 content + 1 bottom = 5
        $this->assertCount(5, $result);
        $this->assertStringContainsString('line1', $result[1]);
        $this->assertStringContainsString('line2', $result[2]);
        $this->assertStringContainsString('line3', $result[3]);
    }

    public function testZeroWidthsPreservePatternAndColor()
    {
        $border = Border::all(0, BorderPattern::ROUNDED, '#ff0000');

        $this->assertSame(BorderPattern::rounded()->getChars(), $border->pattern->getChars());
        $this->assertSame(Color::from('#ff0000')->toRgb(), $border->color->toRgb());
    }

    public function testWrapLinesWithGradientAppliesPerColumnCodesToTopRow()
    {
        $border = Border::all(1, 'normal');
        $style = (new Style())->withLinearGradient(['#000000', '#ffffff'], GradientDirection::LeftToRight);

        $lines = $border->wrapLines(['abc'], 3, $style);

        // Top border row should contain both the black code (col 0) and white code (col last)
        $black = Color::from('#000000')->toBackgroundCode();
        $white = Color::from('#ffffff')->toBackgroundCode();

        // lines[0] is top border row (with corners + 3 fill chars = 5 columns total)
        $this->assertStringContainsString($black, $lines[0]);
        $this->assertStringContainsString($white, $lines[0]);

        // lines[2] is bottom border row: should also have gradient
        $this->assertStringContainsString($black, $lines[2]);
        $this->assertStringContainsString($white, $lines[2]);
    }

    public function testWrapLinesWithGradientAppliesGradientToCorners()
    {
        $border = Border::all(1, 'normal');
        $style = (new Style())->withLinearGradient(['#000000', '#ffffff'], GradientDirection::LeftToRight);

        $lines = $border->wrapLines(['abc'], 3, $style);

        $black = Color::from('#000000')->toBackgroundCode();
        $white = Color::from('#ffffff')->toBackgroundCode();

        // A row is 5 columns: left corner, 3 fill cells, right corner. Assert on the
        // background actually in effect at each corner rather than on the presence of
        // a code somewhere in the row, which a repeated color need not restate.
        foreach ([0, 2] as $row) {
            $this->assertSame($black, self::backgroundAtColumn($lines[$row], 0), "row $row: left corner takes the first gradient color");
            $this->assertSame($white, self::backgroundAtColumn($lines[$row], 4), "row $row: right corner takes the last gradient color");
        }
    }

    public function testWrapLinesWithGradientStatesARepeatedBorderColorOnlyOnce()
    {
        // A vertical gradient gives every cell of a row the same color, so the row
        // must state it once instead of before each of its 5 cells.
        $border = Border::all(1, 'normal');
        $style = (new Style())->withLinearGradient(['#000000', '#ffffff'], GradientDirection::TopToBottom);

        $lines = $border->wrapLines(['abc'], 3, $style);

        $this->assertSame(1, substr_count($lines[0], "\x1b[48;2;"), 'top border row');
        $this->assertSame(1, substr_count($lines[2], "\x1b[48;2;"), 'bottom border row');
    }

    public function testWrapLinesWithGradientKeepsReverseVideoOnPatternsUsingIt()
    {
        // tall-medium drives its top fill with strategy 3, which is reverse video.
        // Hoisting the constant foreground out of the run must not unbalance it.
        $border = Border::all(1, 'tall-medium');
        $style = (new Style())->withLinearGradient(['#000000', '#ffffff'], GradientDirection::LeftToRight);

        $lines = $border->wrapLines(['abc'], 3, $style);

        $this->assertStringContainsString("\e[7m", $lines[0]);
        $this->assertSame(
            substr_count($lines[0], "\e[7m"),
            substr_count($lines[0], "\e[27m"),
            'reverse video must be closed as many times as it is opened'
        );
        $this->assertSame('▌▆▆▆▌', AnsiUtils::stripAnsiCodes($lines[0]));
    }

    /**
     * Return the background code in effect at a given visible column of a line.
     */
    private static function backgroundAtColumn(string $line, int $column): string
    {
        $background = '';
        $col = 0;

        foreach (preg_split('/(\x1b\[[0-9;]*[a-zA-Z])/', $line, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY) as $part) {
            if (str_starts_with($part, "\x1b[")) {
                if (preg_match('/^\x1b\[(?:4[0-7]|10[0-7]|48;[25];[0-9;]+)m$/', $part)) {
                    $background = $part;
                } elseif ("\x1b[49m" === $part || "\x1b[0m" === $part) {
                    $background = '';
                }

                continue;
            }

            foreach (preg_split('//u', $part, -1, \PREG_SPLIT_NO_EMPTY) as $ignored) {
                if ($col === $column) {
                    return $background;
                }
                ++$col;
            }
        }

        return $background;
    }

    public function testWrapLinesWithGradientAppliesBackgroundToSideSegments()
    {
        // Standalone gradient container (no outer background): side segments use inner gradient edge colors
        $border = Border::all(1, 'normal');
        $style = (new Style())->withLinearGradient(['#000000', '#ffffff'], GradientDirection::LeftToRight);

        $gradient = $style->getGradient();
        $innerWidth = 3;
        $codes = $gradient->resolve($innerWidth, 1);
        $innerLine = $codes[0][0].' '.$codes[0][1].' '.$codes[0][2].' '."\x1b[49m]";

        $lines = $border->wrapLines([$innerLine], $innerWidth, $style);

        $black = Color::from('#000000')->toBackgroundCode();
        $white = Color::from('#ffffff')->toBackgroundCode();

        $this->assertStringStartsWith($black, $lines[1]);
        $this->assertStringContainsString($white, substr($lines[1], -50));
    }

    public function testWrapLinesWithGradientAlwaysUsesChildGradientRegardlessOfOuterStyle()
    {
        // Border chars (side segs, corners, fill) must always show the child's own gradient,
        // whether outer has a gradient, a solid bg, or nothing.
        $border = Border::all(1, 'normal');
        $innerStyle = (new Style())->withLinearGradient(['#000000', '#ffffff'], GradientDirection::LeftToRight);

        $black = Color::from('#000000')->toBackgroundCode();
        $white = Color::from('#ffffff')->toBackgroundCode();
        $fill50 = Color::from('#000000')->mix(Color::from('#ffffff'), 50)->toBackgroundCode();

        foreach ([
            'no outer' => new Style(),
            'outer gradient' => (new Style())->withLinearGradient(['#ff0000', '#0000ff'], GradientDirection::TopToBottom),
            'outer solid bg' => (new Style())->withBackground('#ff0000'),
        ] as $label => $outerStyle) {
            $lines = $border->wrapLines(['abc'], 3, $innerStyle, $outerStyle);

            // innerWidth=3 gradient: strip=[black, 50%gray, white]
            // Left side seg (content row) = strip[0] of child gradient = black
            $this->assertStringStartsWith($black, $lines[1], "$label: left side seg must use child gradient");
            // Top/bottom border rows must contain the 50% fill color (col 2 of child gradient)
            $this->assertStringContainsString($fill50, $lines[0], "$label: top fill must use child gradient");
            $this->assertStringContainsString($fill50, $lines[2], "$label: bottom fill must use child gradient");
        }
    }

    public function testWrapLinesGradientSpansInnerWidthOnly()
    {
        // Gradient spans innerWidth=3 only (not totalWidth=5).
        // strip for width=3: [offset=0 → black, offset=0.5 → 50%gray, offset=1 → white]
        // Corners use same endpoints as the fill:
        //   left corner  = strip[0] = black
        //   fill[0]      = strip[0] = black  ← pure black, NOT 25% gray
        //   fill[1]      = strip[1] = 50% gray
        //   fill[2]      = strip[2] = white
        //   right corner = strip[2] = white
        $border = Border::all(1, 'normal');
        $style = (new Style())->withLinearGradient(['#000000', '#ffffff'], GradientDirection::LeftToRight);

        $lines = $border->wrapLines(['abc'], 3, $style);

        $black = Color::from('#000000')->toBackgroundCode();
        $gray = Color::from('#000000')->mix(Color::from('#ffffff'), 50)->toBackgroundCode();
        $white = Color::from('#ffffff')->toBackgroundCode();
        $quarter = Color::from('#000000')->mix(Color::from('#ffffff'), 25)->toBackgroundCode();

        foreach ([0, 2] as $row) {
            $this->assertSame($black, self::backgroundAtColumn($lines[$row], 0), "row $row: left corner");
            $this->assertSame($black, self::backgroundAtColumn($lines[$row], 1), "row $row: fill[0] is offset=0");
            $this->assertSame($gray, self::backgroundAtColumn($lines[$row], 2), "row $row: fill[1] is offset=0.5");
            $this->assertSame($white, self::backgroundAtColumn($lines[$row], 3), "row $row: fill[2] is offset=1");
            $this->assertSame($white, self::backgroundAtColumn($lines[$row], 4), "row $row: right corner");

            // Spanning totalWidth=5 instead of innerWidth=3 would put 25% gray on a cell.
            $this->assertStringNotContainsString($quarter, $lines[$row], "row $row: the strip must span innerWidth");
        }
    }
}
