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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Output\AnsiColorMode;

class AnsiColorModeTest extends TestCase
{
    /**
     * @dataProvider provideColorsConversion
     */
    public function testColorsConversionToAnsi4(string $corlorHex, array $expected)
    {
        $this->assertSame((string) $expected[AnsiColorMode::Ansi4->name], AnsiColorMode::Ansi4->convertFromHexToAnsiColorCode($corlorHex));
    }

    /**
     * @dataProvider provideColorsConversion
     */
    public function testColorsConversionToAnsi8(string $corlorHex, array $expected)
    {
        $this->assertSame('8;5;'.$expected[AnsiColorMode::Ansi8->name], AnsiColorMode::Ansi8->convertFromHexToAnsiColorCode($corlorHex));
    }

    public static function provideColorsConversion(): \Generator
    {
        yield ['#606702', [
            AnsiColorMode::Ansi8->name => 100,
            AnsiColorMode::Ansi4->name => 0,
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
            AnsiColorMode::Ansi4->name => 7,
        ]];

        yield ['#a8a8a8', [
            AnsiColorMode::Ansi8->name => 248,
            AnsiColorMode::Ansi4->name => 7,
        ]];
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
