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
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Style\Border;
use Symfony\Component\Tui\Style\BorderPattern;
use Symfony\Component\Tui\Style\Color;
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
}
