<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\TuiHtml\Render\ColorRewriter;
use Symfony\Component\TuiHtml\Style\ColorScheme;

final class ColorRewriterTest extends TestCase
{
    public function testEveryColorFormIsRewritten()
    {
        // Swaps red and blue, to see each color come through
        $rewriter = new ColorRewriter(static fn (array $rgb): array => [$rgb[2], $rgb[1], $rgb[0]], ColorScheme::Dark);
        $line = $rewriter->line("a\x1b[38;2;255;0;0mb\x1b[48;5;21mc\x1b[38:2::1:2:3md\x1b[91me\x1b[4:3;58;2;10;20;30mf\x1b[0mg");
        $this->assertSame('abcdefg', AnsiUtils::stripAnsiCodes($line));
        $this->assertStringStartsWith("\x1b[38;2;229;229;229;49ma", $line, 'The default foreground, of the color scheme.');
        $this->assertStringContainsString("\x1b[38;2;0;0;255;49mb", $line, 'A true color.');
        $this->assertStringContainsString("\x1b[38;2;0;0;255;48;2;255;0;0mc", $line, 'A palette background.');
        $this->assertStringContainsString("\x1b[38;2;3;2;1;48;2;255;0;0md", $line, 'The colon form.');
        $this->assertStringContainsString("\x1b[4:3m\x1b[38;2;0;0;255;48;2;255;0;0;58;2;30;20;10mf", $line, 'A bright basic color, an underline color, and a curly underline kept.');
        $this->assertStringContainsString("\x1b[0m\x1b[38;2;229;229;229;49mg", $line, 'A reset goes back to the defaults.');
    }

    public function testColorsAreOnlyWrittenWhenTheyChange()
    {
        $rewriter = new ColorRewriter(static fn (array $rgb): array => $rgb, ColorScheme::Light);
        $line = $rewriter->line("\x1b[38;2;1;2;3ma\x1b[1mb\x1b[38;2;1;2;3mc");
        $this->assertSame("\x1b[38;2;0;0;0;49m\x1b[38;2;1;2;3;49ma\x1b[1mbc\x1b[0m", $line);
    }

    public function testWhatIsBehindAColor()
    {
        $behind = [];
        $rewriter = new ColorRewriter(static function (array $rgb, array $under) use (&$behind): array {
            $behind[] = $under;

            return $rgb;
        }, ColorScheme::Dark);
        $rewriter->line("\x1b[38;2;9;9;9;48;2;1;2;3mx");
        $this->assertContains([1, 2, 3], $behind, 'The text is over its cell background.');
        $this->assertContains([0, 0, 0], $behind, 'The background is over the terminal one, of the color scheme.');
    }
}
