<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Output;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Output\AnsiColorMode;

class AnsiColorModeTest extends TestCase
{
    #[DataProvider('provideColorsConversion')]
    public function testColorsConversionToAnsi4(string $corlorHex, array $expected)
    {
        $this->assertSame((string) $expected[AnsiColorMode::Ansi4->name], AnsiColorMode::Ansi4->convertFromHexToAnsiColorCode($corlorHex));
    }

    #[DataProvider('provideColorsConversion')]
    public function testColorsConversionToAnsi8(string $corlorHex, array $expected)
    {
        $this->assertSame('8;5;'.$expected[AnsiColorMode::Ansi8->name], AnsiColorMode::Ansi8->convertFromHexToAnsiColorCode($corlorHex));
    }

    public static function provideColorsConversion(): \Generator
    {
        yield ['#606702', [
            AnsiColorMode::Ansi8->name => 100,
            AnsiColorMode::Ansi4->name => 3,
        ]];

        yield ['#f40502', [
            AnsiColorMode::Ansi8->name => 196,
            AnsiColorMode::Ansi4->name => 1,
        ]];

        yield ['#2a2a2a', [
            AnsiColorMode::Ansi8->name => 235,
            AnsiColorMode::Ansi4->name => 0,
        ]];

        yield ['#57f70f', [
            AnsiColorMode::Ansi8->name => 118,
            AnsiColorMode::Ansi4->name => 2,
        ]];

        yield ['#eec7fa', [
            AnsiColorMode::Ansi8->name => 225,
            AnsiColorMode::Ansi4->name => 5,
        ]];

        yield ['#a8a8a8', [
            AnsiColorMode::Ansi8->name => 248,
            AnsiColorMode::Ansi4->name => 7,
        ]];

        yield ['#000000', [
            AnsiColorMode::Ansi8->name => 16,
            AnsiColorMode::Ansi4->name => 0,
        ]];

        yield ['#ffffff', [
            AnsiColorMode::Ansi8->name => 231,
            AnsiColorMode::Ansi4->name => 7,
        ]];
    }

    #[DataProvider('provideAnsi4ClosestHue')]
    public function testAnsi4PicksClosestHue(string $colorHex, int $expected)
    {
        $this->assertSame((string) $expected, AnsiColorMode::Ansi4->convertFromHexToAnsiColorCode($colorHex));
    }

    public static function provideAnsi4ClosestHue(): \Generator
    {
        yield 'dark red' => ['#800000', 1];
        yield 'orange' => ['#ff8000', 3];
        yield 'olive' => ['#808000', 3];
        yield 'dark green' => ['#006400', 2];
        yield 'teal' => ['#008080', 6];
        yield 'navy' => ['#000080', 4];
        yield 'royal blue' => ['#4169e1', 4];
        yield 'purple' => ['#800080', 5];
        yield 'pink' => ['#ff1493', 5];
        yield 'crimson' => ['#dc143c', 1];
        yield 'very dark magenta' => ['#200010', 5];
        yield 'dark grey' => ['#555555', 0];
        yield 'darkest grey rendered white' => ['#808080', 7];
        yield 'lightest grey rendered black' => ['#7f7f7f', 0];
        yield 'light grey' => ['#c0c0c0', 7];
        yield 'pastel lilac' => ['#eec7fa', 5];
        yield 'nearly white' => ['#fffffe', 7];
        yield 'ghost white' => ['#f8f8ff', 7];
        yield 'floral white' => ['#fffaf0', 7];
        yield 'nearly black' => ['#010000', 0];
        yield 'dark editor background' => ['#202124', 0];
        yield 'channels 31 apart rendered grey' => ['#1f0010', 0];
        yield 'channels 32 apart rendered with their hue' => ['#200010', 5];
    }

    public function testColorsConversionWithoutSharp()
    {
        $this->assertSame('8;5;102', AnsiColorMode::Ansi8->convertFromHexToAnsiColorCode('547869'));
    }

    public function testColorsConversionWithout3Characters()
    {
        $this->assertSame('8;5;241', AnsiColorMode::Ansi8->convertFromHexToAnsiColorCode('#666'));
    }

    public function testInvalidHexCode()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid "#6666" color.');

        AnsiColorMode::Ansi8->convertFromHexToAnsiColorCode('#6666');
    }
}
