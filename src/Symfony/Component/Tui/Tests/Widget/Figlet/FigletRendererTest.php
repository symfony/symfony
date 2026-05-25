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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Widget\Figlet\FigletFont;
use Symfony\Component\Tui\Widget\Figlet\FigletRenderer;

class FigletRendererTest extends TestCase
{
    private const FONTS_DIR = __DIR__.'/../../../Widget/Figlet/fonts';

    public function testRenderEmptyString()
    {
        $font = FigletFont::load(self::FONTS_DIR.'/big.flf');
        $renderer = new FigletRenderer($font);

        $this->assertSame([], $renderer->render(''));
    }

    public function testRenderSingleCharacter()
    {
        $font = FigletFont::load(self::FONTS_DIR.'/big.flf');
        $renderer = new FigletRenderer($font);

        $lines = $renderer->render('A');

        // big font is 8 tall, trailing blank lines stripped; should be ≤ 8 and ≥ 1
        $this->assertGreaterThanOrEqual(1, \count($lines));
        $this->assertLessThanOrEqual(8, \count($lines));

        // Should contain the recognizable parts of 'A' in big font
        $joined = implode("\n", $lines);
        $this->assertStringContainsString('/\\', $joined);
    }

    public function testRenderMultipleCharacters()
    {
        $font = FigletFont::load(self::FONTS_DIR.'/big.flf');
        $renderer = new FigletRenderer($font);

        $lines = $renderer->render('Hi');

        $this->assertGreaterThanOrEqual(1, \count($lines));
        $this->assertLessThanOrEqual(8, \count($lines));

        // Each line should be wider than a single character
        $singleCharLines = $renderer->render('H');
        $this->assertGreaterThanOrEqual(1, \count($singleCharLines));
        $this->assertGreaterThan(
            $this->maxLineLength($singleCharLines),
            $this->maxLineLength($lines),
        );
    }

    public function testTrailingWhitespaceIsStripped()
    {
        $font = FigletFont::load(self::FONTS_DIR.'/big.flf');
        $renderer = new FigletRenderer($font);

        $lines = $renderer->render('A');

        foreach ($lines as $line) {
            $this->assertSame($line, rtrim($line), 'Lines should not have trailing whitespace');
        }
    }

    public function testTrailingBlankLinesAreRemoved()
    {
        $font = FigletFont::load(self::FONTS_DIR.'/big.flf');
        $renderer = new FigletRenderer($font);

        $lines = $renderer->render('A');

        // Last line should not be blank
        if ($lines) {
            $this->assertNotSame('', end($lines), 'Trailing blank lines should be removed');
        }
    }

    public function testRenderSpace()
    {
        $font = FigletFont::load(self::FONTS_DIR.'/big.flf');
        $renderer = new FigletRenderer($font);

        $linesWithSpace = $renderer->render('A B');
        $linesWithout = $renderer->render('AB');

        // With space should be wider
        $this->assertGreaterThanOrEqual(1, \count($linesWithout));
        $this->assertGreaterThanOrEqual(1, \count($linesWithSpace));
        $this->assertGreaterThan(
            $this->maxLineLength($linesWithout),
            $this->maxLineLength($linesWithSpace),
        );
    }

    public function testRenderWithNamedColor()
    {
        $font = FigletFont::load(self::FONTS_DIR.'/big.flf');
        $renderer = new FigletRenderer($font);

        $lines = $renderer->render('A', 'red');

        $this->assertNotSame([], $lines);
        // Each non-empty line should start with ANSI escape and end with reset
        foreach ($lines as $line) {
            if ('' === $line) {
                continue;
            }
            $this->assertStringStartsWith("\x1b[", $line);
            $this->assertStringEndsWith("\x1b[0m", $line);
        }
    }

    public function testRenderWithHexColor()
    {
        $font = FigletFont::load(self::FONTS_DIR.'/big.flf');
        $renderer = new FigletRenderer($font);

        $lines = $renderer->render('A', '#ff5500');

        $this->assertNotSame([], $lines);
        foreach ($lines as $line) {
            if ('' === $line) {
                continue;
            }
            $this->assertStringContainsString('38;2;255;85;0', $line);
        }
    }

    public function testRenderWithColorPreservesContent()
    {
        $font = FigletFont::load(self::FONTS_DIR.'/big.flf');
        $renderer = new FigletRenderer($font);

        $plain = $renderer->render('A');
        $colored = $renderer->render('A', 'red');

        $this->assertCount(\count($plain), $colored);

        // Stripping ANSI codes should give back the plain text
        foreach ($colored as $i => $line) {
            $stripped = preg_replace('/\x1b\[[0-9;]*m/', '', $line);
            $this->assertSame($plain[$i], $stripped);
        }
    }

    public function testRenderWithColorEmptyLinesStayEmpty()
    {
        $font = FigletFont::load(self::FONTS_DIR.'/big.flf');
        $renderer = new FigletRenderer($font);

        $lines = $renderer->render('A', 'cyan');

        // If any blank lines exist in the output, they should remain empty
        // (no color wrapping) so they stay transparent in Compositor
        foreach ($lines as $line) {
            if (!str_contains($line, "\x1b")) {
                $this->assertSame('', $line);
            }
        }
    }

    public function testRenderWithNullColorReturnsPlain()
    {
        $font = FigletFont::load(self::FONTS_DIR.'/big.flf');
        $renderer = new FigletRenderer($font);

        $plain = $renderer->render('A');
        $withNull = $renderer->render('A', null);

        $this->assertSame($plain, $withNull);
    }

    /**
     * @param string[] $lines
     */
    private function maxLineLength(array $lines): int
    {
        $lengths = array_map('strlen', $lines);

        return $lengths ? max($lengths) : 0;
    }
}
