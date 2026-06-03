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
use Symfony\Component\Tui\Style\Color;

class ColorTest extends TestCase
{
    #[DataProvider('namedColorProvider')]
    public function testNamedColor(string $name, string $expectedFg, string $expectedBg)
    {
        $color = Color::named($name);
        $this->assertSame($expectedFg, $color->toForegroundCode());
        $this->assertSame($expectedBg, $color->toBackgroundCode());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function namedColorProvider(): iterable
    {
        yield 'black' => ['black', "\x1b[30m", "\x1b[40m"];
        yield 'red' => ['red', "\x1b[31m", "\x1b[41m"];
        yield 'green' => ['green', "\x1b[32m", "\x1b[42m"];
        yield 'yellow' => ['yellow', "\x1b[33m", "\x1b[43m"];
        yield 'blue' => ['blue', "\x1b[34m", "\x1b[44m"];
        yield 'magenta' => ['magenta', "\x1b[35m", "\x1b[45m"];
        yield 'cyan' => ['cyan', "\x1b[36m", "\x1b[46m"];
        yield 'white' => ['white', "\x1b[37m", "\x1b[47m"];
        yield 'default' => ['default', "\x1b[39m", "\x1b[49m"];
        yield 'bright_white' => ['bright_white', "\x1b[97m", "\x1b[107m"];
        yield 'case insensitive' => ['RED', "\x1b[31m", "\x1b[41m"];
    }

    public function testColorAliases()
    {
        $gray = Color::named('gray');
        $grey = Color::named('grey');
        $this->assertSame($gray->toForegroundCode(), $grey->toForegroundCode());
    }

    public function testInvalidNamedColor()
    {
        $this->expectException(InvalidArgumentException::class);
        Color::named('invalid_color');
    }

    #[DataProvider('paletteColorProvider')]
    public function testPaletteColor(int $index, string $expectedFg, ?string $expectedBg)
    {
        $color = Color::palette($index);
        $this->assertSame($expectedFg, $color->toForegroundCode());
        if (null !== $expectedBg) {
            $this->assertSame($expectedBg, $color->toBackgroundCode());
        }
    }

    /**
     * @return iterable<string, array{int, string, string|null}>
     */
    public static function paletteColorProvider(): iterable
    {
        yield 'foreground 196' => [196, "\x1b[38;5;196m", null];
        yield 'background 236' => [236, "\x1b[38;5;236m", "\x1b[48;5;236m"];
        yield 'boundary 0' => [0, "\x1b[38;5;0m", null];
        yield 'boundary 255' => [255, "\x1b[38;5;255m", null];
    }

    #[DataProvider('invalidPaletteProvider')]
    public function testInvalidPaletteColor(int $index)
    {
        $this->expectException(InvalidArgumentException::class);
        Color::palette($index);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidPaletteProvider(): iterable
    {
        yield 'negative' => [-1];
        yield 'too high' => [256];
    }

    public function testHexColorForeground()
    {
        $color = Color::hex('#ff5500');
        $this->assertSame("\x1b[38;2;255;85;0m", $color->toForegroundCode());
    }

    public function testHexColorBackground()
    {
        $color = Color::hex('#ff5500');
        $this->assertSame("\x1b[48;2;255;85;0m", $color->toBackgroundCode());
    }

    /**
     * @param non-empty-string $hex
     */
    #[DataProvider('hexColorParsingProvider')]
    public function testHexColorParsing(string $hex, string $expectedFg)
    {
        $this->assertSame($expectedFg, Color::hex($hex)->toForegroundCode());
    }

    /**
     * @return iterable<string, array{non-empty-string, string}>
     */
    public static function hexColorParsingProvider(): iterable
    {
        yield 'with hash' => ['#ff5500', "\x1b[38;2;255;85;0m"];
        yield 'without hash' => ['ff5500', "\x1b[38;2;255;85;0m"];
        yield 'short form with hash' => ['#f50', "\x1b[38;2;255;85;0m"];
        yield 'short form without hash' => ['f50', "\x1b[38;2;255;85;0m"];
    }

    #[DataProvider('invalidHexColorProvider')]
    public function testInvalidHexColor(string $hex)
    {
        $this->expectException(InvalidArgumentException::class);
        Color::hex($hex);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidHexColorProvider(): iterable
    {
        yield 'invalid characters' => ['#gg0000'];
        yield 'invalid length' => ['#ff00'];
    }

    public function testRgbColor()
    {
        $color = Color::rgb(255, 85, 0);
        $this->assertSame("\x1b[38;2;255;85;0m", $color->toForegroundCode());
    }

    public function testInvalidRgbColor()
    {
        $this->expectException(InvalidArgumentException::class);
        Color::rgb(256, 0, 0);
    }

    #[DataProvider('colorFromProvider')]
    public function testFrom(Color|string|int $input, string $expectedFg)
    {
        $this->assertSame($expectedFg, Color::from($input)->toForegroundCode());
    }

    /**
     * @return iterable<string, array{Color|string|int, string}>
     */
    public static function colorFromProvider(): iterable
    {
        yield 'integer (palette)' => [196, "\x1b[38;5;196m"];
        yield 'hex string' => ['#ff5500', "\x1b[38;2;255;85;0m"];
        yield 'named string' => ['red', "\x1b[31m"];
    }

    #[DataProvider('toRgbProvider')]
    public function testToRgb(Color $color, int $r, int $g, int $b)
    {
        $rgb = $color->toRgb();

        $this->assertSame($r, $rgb['r']);
        $this->assertSame($g, $rgb['g']);
        $this->assertSame($b, $rgb['b']);
    }

    /**
     * @return iterable<string, array{Color, int, int, int}>
     */
    public static function toRgbProvider(): iterable
    {
        yield 'named red' => [Color::named('red'), 205, 0, 0];
        yield 'hex #ff8000' => [Color::hex('#ff8000'), 255, 128, 0];
        yield 'palette basic (1=red)' => [Color::palette(1), 205, 0, 0];
        yield 'palette cube first (16)' => [Color::palette(16), 0, 0, 0];
        yield 'palette cube red (196)' => [Color::palette(196), 255, 0, 0];
        yield 'palette grayscale first (232)' => [Color::palette(232), 8, 8, 8];
        yield 'palette grayscale last (255)' => [Color::palette(255), 238, 238, 238];
    }

    #[DataProvider('mixProvider')]
    public function testMix(int $amount, int $r, int $g, int $b)
    {
        $color = Color::hex('#ff0000');
        $mixed = $color->mix('#0000ff', $amount);
        $rgb = $mixed->toRgb();

        $this->assertSame($r, $rgb['r']);
        $this->assertSame($g, $rgb['g']);
        $this->assertSame($b, $rgb['b']);
    }

    /**
     * @return iterable<string, array{int, int, int, int}>
     */
    public static function mixProvider(): iterable
    {
        yield 'zero returns base' => [0, 255, 0, 0];
        yield 'hundred returns other' => [100, 0, 0, 255];
        yield 'fifty is halfway' => [50, 128, 0, 128];
    }

    public function testMixAcceptsColorInstance()
    {
        $color = Color::hex('#ff0000');
        $other = Color::hex('#00ff00');
        $mixed = $color->mix($other, 50);
        $rgb = $mixed->toRgb();

        $this->assertSame(128, $rgb['r']);
        $this->assertSame(128, $rgb['g']);
        $this->assertSame(0, $rgb['b']);
    }

    #[DataProvider('invalidMixPercentageProvider')]
    public function testMixInvalidPercentageThrows(int $percentage)
    {
        $this->expectException(InvalidArgumentException::class);
        Color::hex('#ff0000')->mix('#000000', $percentage);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidMixPercentageProvider(): iterable
    {
        yield 'over 100' => [101];
        yield 'negative' => [-1];
    }

    #[DataProvider('tintProvider')]
    public function testTint(int $amount, string $hex, int $r, int $g, int $b)
    {
        $rgb = Color::hex($hex)->tint($amount)->toRgb();

        $this->assertSame($r, $rgb['r']);
        $this->assertSame($g, $rgb['g']);
        $this->assertSame($b, $rgb['b']);
    }

    /**
     * @return iterable<string, array{int, string, int, int, int}>
     */
    public static function tintProvider(): iterable
    {
        yield 'lightens color' => [50, '#ff0000', 255, 128, 128];
        yield 'zero is unchanged' => [0, '#336699', 51, 102, 153];
        yield 'hundred is white' => [100, '#336699', 255, 255, 255];
    }

    #[DataProvider('shadeProvider')]
    public function testShade(int $amount, string $hex, int $r, int $g, int $b)
    {
        $rgb = Color::hex($hex)->shade($amount)->toRgb();

        $this->assertSame($r, $rgb['r']);
        $this->assertSame($g, $rgb['g']);
        $this->assertSame($b, $rgb['b']);
    }

    /**
     * @return iterable<string, array{int, string, int, int, int}>
     */
    public static function shadeProvider(): iterable
    {
        yield 'darkens color' => [50, '#ff0000', 128, 0, 0];
        yield 'zero is unchanged' => [0, '#336699', 51, 102, 153];
        yield 'hundred is black' => [100, '#336699', 0, 0, 0];
    }

    #[DataProvider('scaleProvider')]
    public function testScale(int $amount, string $method)
    {
        $color = Color::hex('#ff0000');
        $scaled = $color->scale($amount);
        $expected = $color->$method(abs($amount));

        $this->assertSame($expected->toRgb(), $scaled->toRgb());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function scaleProvider(): iterable
    {
        yield 'positive darkens (shade)' => [50, 'shade'];
        yield 'negative lightens (tint)' => [-50, 'tint'];
        yield 'zero is unchanged' => [0, 'shade'];
    }
}
