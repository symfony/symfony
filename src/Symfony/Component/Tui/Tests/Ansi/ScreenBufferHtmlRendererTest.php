<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Ansi;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\ScreenBufferHtmlRenderer;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Terminal\ScreenBuffer;

class ScreenBufferHtmlRendererTest extends TestCase
{
    /**
     * @param string[] $expectedCss
     */
    #[DataProvider('ansiToHtmlProvider')]
    public function testAnsiToHtmlConversion(string $ansi, array $expectedCss)
    {
        $html = $this->convert($ansi);

        foreach ($expectedCss as $css) {
            $this->assertStringContainsString($css, $html);
        }
    }

    /**
     * @return iterable<string, array{string, string[]}>
     */
    public static function ansiToHtmlProvider(): iterable
    {
        yield 'foreground color' => ["\x1b[32mHello\x1b[0m", ['color: #00cd00']];
        yield 'bold with color' => ["\x1b[1;32mHello\x1b[0m", ['font-weight: bold', 'color: #00cd00']];
        yield 'background color' => ["\x1b[41mHello\x1b[0m", ['background-color: #cd0000']];
        yield 'bright foreground' => ["\x1b[91mHello\x1b[0m", ['color: #ff0000']];
        yield 'bright background' => ["\x1b[101mHello\x1b[0m", ['background-color: #ff0000']];
        yield '256-color foreground' => ["\x1b[38;5;196mHello\x1b[0m", ['color: #ff0000']];
        yield '256-color background' => ["\x1b[48;5;21mHello\x1b[0m", ['background-color:']];
        yield 'bold + italic + color' => ["\x1b[1;3;34mHello\x1b[0m", ['font-weight: bold', 'font-style: italic', 'color: #0000ee']];
        yield 'truecolor foreground' => ["\x1b[38;2;255;128;0mX\x1b[0m", ['color: #ff8000']];
        yield 'truecolor foreground black' => ["\x1b[38;2;0;0;0mX\x1b[0m", ['color: #000000']];
        yield 'truecolor foreground white' => ["\x1b[38;2;255;255;255mX\x1b[0m", ['color: #ffffff']];
        yield 'truecolor background' => ["\x1b[48;2;100;200;50mX\x1b[0m", ['background-color: #64c832']];
        yield 'truecolor background black' => ["\x1b[48;2;0;0;0mX\x1b[0m", ['background-color: #000000']];
        yield 'truecolor background white' => ["\x1b[48;2;255;255;255mX\x1b[0m", ['background-color: #ffffff']];
        yield 'truecolor fg + bg' => ["\x1b[38;2;255;0;0;48;2;0;0;255mX\x1b[0m", ['color: #ff0000', 'background-color: #0000ff']];
        yield 'truecolor with bold' => ["\x1b[1;38;2;128;64;32mX\x1b[0m", ['font-weight: bold', 'color: #804020']];
    }

    #[DataProvider('colorCodeMappingProvider')]
    public function testColorCodeMapping(string $ansi, string $expectedCss)
    {
        $html = $this->convert($ansi);

        $this->assertStringContainsString($expectedCss, $html);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function colorCodeMappingProvider(): iterable
    {
        // Standard foreground colors (30-37) - xterm defaults
        $standardFg = [
            30 => '#000000', 31 => '#cd0000', 32 => '#00cd00', 33 => '#cdcd00',
            34 => '#0000ee', 35 => '#cd00cd', 36 => '#00cdcd', 37 => '#e5e5e5',
        ];
        foreach ($standardFg as $code => $hex) {
            yield "standard fg {$code}" => ["\x1b[{$code}mX\x1b[0m", "color: {$hex}"];
        }

        // Standard background colors (40-47) - xterm defaults
        $standardBg = [
            40 => '#000000', 41 => '#cd0000', 42 => '#00cd00', 43 => '#cdcd00',
            44 => '#0000ee', 45 => '#cd00cd', 46 => '#00cdcd', 47 => '#e5e5e5',
        ];
        foreach ($standardBg as $code => $hex) {
            yield "standard bg {$code}" => ["\x1b[{$code}mX\x1b[0m", "background-color: {$hex}"];
        }

        // Bright foreground colors (90-97) - xterm defaults
        $brightFg = [
            90 => '#7f7f7f', 91 => '#ff0000', 92 => '#00ff00', 93 => '#ffff00',
            94 => '#5c5cff', 95 => '#ff00ff', 96 => '#00ffff', 97 => '#ffffff',
        ];
        foreach ($brightFg as $code => $hex) {
            yield "bright fg {$code}" => ["\x1b[{$code}mX\x1b[0m", "color: {$hex}"];
        }

        // Bright background colors (100-107) - xterm defaults
        $brightBg = [
            100 => '#7f7f7f', 101 => '#ff0000', 102 => '#00ff00', 103 => '#ffff00',
            104 => '#5c5cff', 105 => '#ff00ff', 106 => '#00ffff', 107 => '#ffffff',
        ];
        foreach ($brightBg as $code => $hex) {
            yield "bright bg {$code}" => ["\x1b[{$code}mX\x1b[0m", "background-color: {$hex}"];
        }

        // 256-color: standard (0-15)
        yield '256 standard black' => ["\x1b[38;5;0mX\x1b[0m", 'color: #000000'];
        yield '256 standard white' => ["\x1b[38;5;15mX\x1b[0m", 'color: #ffffff'];

        // 256-color: cube (16-231)
        yield '256 cube red' => ["\x1b[38;5;196mX\x1b[0m", 'color: #ff0000'];
        yield '256 cube green' => ["\x1b[38;5;46mX\x1b[0m", 'color: #00ff00'];
        yield '256 cube blue' => ["\x1b[38;5;21mX\x1b[0m", 'color: #0000ff'];

        // 256-color: grayscale (232-255)
        yield '256 grayscale darkest' => ["\x1b[38;5;232mX\x1b[0m", 'color: #080808'];
        yield '256 grayscale lightest' => ["\x1b[38;5;255mX\x1b[0m", 'color: #eeeeee'];
        yield '256 grayscale mid' => ["\x1b[38;5;240mX\x1b[0m", 'color: #585858'];
    }

    public function testReverseVideo()
    {
        $html = $this->convert("\x1b[7mX\x1b[0m");

        $this->assertStringContainsString('color: #1e1e1e', $html);
        $this->assertStringContainsString('background-color: #d4d4d4', $html);
    }

    public function testReverseVideoWithCustomDefaults()
    {
        $screen = new ScreenBuffer(40, 10);
        $screen->write("\x1b[7mX\x1b[0m");

        $converter = new ScreenBufferHtmlRenderer(Color::hex('#ffffff'), Color::hex('#000000'));
        $html = $converter->convert($screen);

        $this->assertStringContainsString('color: #000000', $html);
        $this->assertStringContainsString('background-color: #ffffff', $html);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function textDecorationProvider(): iterable
    {
        yield 'italic' => ["\x1b[3mHello\x1b[0m", 'font-style: italic'];
        yield 'underline' => ["\x1b[4mHello\x1b[0m", 'text-decoration: underline'];
        yield 'line-through' => ["\x1b[9mHello\x1b[0m", 'text-decoration: line-through'];
        yield 'dim' => ["\x1b[2mHello\x1b[0m", 'opacity: 0.7'];
    }

    #[DataProvider('textDecorationProvider')]
    public function testTextDecorations(string $ansi, string $expectedCss)
    {
        $html = $this->convert($ansi);

        $this->assertStringContainsString($expectedCss, $html);
    }

    public function testUnderlineAndStrikethrough()
    {
        $html = $this->convert("\x1b[4;9mText\x1b[0m");

        $this->assertStringContainsString('text-decoration: underline line-through', $html);
    }

    public function testResetProducesNoStyle()
    {
        $html = $this->convert("\x1b[32mGreen\x1b[0m Plain");

        $this->assertStringContainsString('<span style="color: #00cd00;">Green</span>', $html);
        $this->assertStringContainsString('Plain', $html);
        $this->assertStringNotContainsString('>Plain</span>', $html);
    }

    public function testPlainTextNoSpans()
    {
        $html = $this->convert('Hello World');

        $this->assertSame('Hello World', $html);
        $this->assertStringNotContainsString('<span', $html);
    }

    public function testMultipleLines()
    {
        $screen = new ScreenBuffer(40, 10);
        $screen->write("Line 1\n\x1b[1mLine 2\x1b[0m\nLine 3");

        $converter = new ScreenBufferHtmlRenderer();
        $html = $converter->convert($screen);

        $lines = explode("\n", $html);
        $this->assertCount(3, $lines);
        $this->assertSame('Line 1', $lines[0]);
        $this->assertStringContainsString('font-weight: bold', $lines[1]);
        $this->assertStringContainsString('Line 2', $lines[1]);
        $this->assertSame('Line 3', $lines[2]);
    }

    public function testEmptyScreen()
    {
        $screen = new ScreenBuffer(40, 10);

        $converter = new ScreenBufferHtmlRenderer();
        $html = $converter->convert($screen);

        $this->assertSame('', $html);
    }

    public function testTrailingWhitespaceOnlyLinesAreStripped()
    {
        $screen = new ScreenBuffer(40, 10);
        $screen->write("Hello\n     ");

        $converter = new ScreenBufferHtmlRenderer();
        $html = $converter->convert($screen);

        $this->assertSame('Hello', $html);
    }

    public function testHtmlEntitiesEscaped()
    {
        $html = $this->convert('<script>alert("xss")</script>');

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testWideCharactersRenderedCorrectly()
    {
        $html = $this->convert("\x1b[31m你好\x1b[0m World");

        $this->assertStringContainsString('你好', $html);
        $this->assertStringContainsString('World', $html);
        $this->assertStringContainsString('color: #cd0000', $html);
        $this->assertStringNotContainsString('你好</span> </span>', $html);
    }

    #[DataProvider('underlineColorProvider')]
    public function testUnderlineColor(string $ansi, string $expectedDecorationColor)
    {
        $html = $this->convert($ansi);

        $this->assertStringContainsString('text-decoration: underline', $html);
        $this->assertStringContainsString("text-decoration-color: {$expectedDecorationColor}", $html);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function underlineColorProvider(): iterable
    {
        yield '256-color' => ["\x1b[4;58;5;196mHello\x1b[0m", '#ff0000'];
        yield 'truecolor' => ["\x1b[4;58;2;255;128;0mHello\x1b[0m", '#ff8000'];
    }

    public function testUnderlineColorResetWithCode59()
    {
        $html = $this->convert("\x1b[4;58;5;196mRed\x1b[59mDefault\x1b[0m");

        $this->assertStringContainsString('text-decoration-color: #ff0000', $html);
    }

    private function convert(string $text): string
    {
        $screen = new ScreenBuffer(80, 24);
        $screen->write($text);

        return new ScreenBufferHtmlRenderer()->convert($screen);
    }
}
