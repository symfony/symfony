<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Widget\Figlet;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Widget\Figlet\FigletFont;

class FigletFontTest extends TestCase
{
    private const FONTS_DIR = __DIR__.'/../../../Widget/Figlet/fonts';

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function bundledFontProvider(): iterable
    {
        yield 'big' => ['big', 8];
        yield 'small' => ['small', 5];
        yield 'slant' => ['slant', 6];
        yield 'standard' => ['standard', 6];
        yield 'mini' => ['mini', 4];
    }

    #[DataProvider('bundledFontProvider')]
    public function testLoadBundledFont(string $name, int $expectedHeight)
    {
        $font = FigletFont::load(self::FONTS_DIR.'/'.$name.'.flf');

        $this->assertSame($expectedHeight, $font->getHeight());

        // Every bundled font must cover the full printable ASCII range
        for ($code = 32; $code <= 126; ++$code) {
            $this->assertTrue(
                $font->hasCharacter($code),
                \sprintf('Font "%s" is missing character %d (%s)', $name, $code, \chr($code)),
            );
        }
    }

    public function testGetCharacterReturnsCorrectHeight()
    {
        $font = FigletFont::load(self::FONTS_DIR.'/big.flf');
        $charLines = $font->getCharacter(65); // A

        $this->assertCount(8, $charLines);
    }

    public function testGetCharacterForUnknownCodepoint()
    {
        $font = FigletFont::load(self::FONTS_DIR.'/big.flf');
        $charLines = $font->getCharacter(9999);

        $this->assertCount(8, $charLines);
        foreach ($charLines as $line) {
            $this->assertSame('', $line);
        }
    }

    public function testHardblankIsReplacedWithSpace()
    {
        $font = FigletFont::load(self::FONTS_DIR.'/big.flf');

        // Space character (ASCII 32); in the .flf file it uses $ (hardblank)
        $spaceLines = $font->getCharacter(32);

        // No $ should remain in the output
        foreach ($spaceLines as $line) {
            $this->assertStringNotContainsString('$', $line);
        }
    }

    public function testParseInvalidHeader()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing flf2 signature');

        FigletFont::parse("not a figlet font\n");
    }

    public function testLoadNonExistentFile()
    {
        $this->expectException(InvalidArgumentException::class);

        FigletFont::load('/nonexistent/path/to/font.flf');
    }
}
