<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\ColorRewriter;
use Symfony\Component\Tui\Style\ColorScheme;

final class ColorRewriterTest extends TestCase
{
    public function testEveryColorFormIsRewritten()
    {
        // Swaps red and blue, to see each color come through
        $rewriter = new ColorRewriter(static fn (array $rgb): array => [$rgb[2], $rgb[1], $rgb[0]], ColorScheme::Dark);

        $line = $rewriter->line("a\e[38;2;255;0;0mb\e[48;5;21mc\e[38:2::1:2:3md\e[91me\e[4:3;58;2;10;20;30mf\e[0mg");

        $this->assertSame('abcdefg', AnsiUtils::stripAnsiCodes($line));
        $this->assertStringStartsWith("\e[38;2;229;229;229;49ma", $line, 'The default foreground, of the color scheme.');
        $this->assertStringContainsString("\e[38;2;0;0;255;49mb", $line, 'A true color.');
        $this->assertStringContainsString("\e[38;2;0;0;255;48;2;255;0;0mc", $line, 'A palette background.');
        $this->assertStringContainsString("\e[38;2;3;2;1;48;2;255;0;0md", $line, 'The colon form.');
        $this->assertStringContainsString("\e[4:3m\e[38;2;0;0;255;48;2;255;0;0;58;2;30;20;10mf", $line, 'A bright basic color, an underline color, and a curly underline kept.');
        $this->assertStringContainsString("\e[0m\e[38;2;229;229;229;49mg", $line, 'A reset goes back to the defaults.');
    }

    public function testColorsAreOnlyWrittenWhenTheyChange()
    {
        $rewriter = new ColorRewriter(static fn (array $rgb): array => $rgb, ColorScheme::Light);

        $line = $rewriter->line("\e[38;2;1;2;3ma\e[1mb\e[38;2;1;2;3mc");

        $this->assertSame("\e[38;2;0;0;0;49m\e[38;2;1;2;3;49ma\e[1mbc\e[0m", $line);
    }

    public function testWhatIsBehindAColor()
    {
        $behind = [];
        $rewriter = new ColorRewriter(static function (array $rgb, array $under) use (&$behind): array {
            $behind[] = $under;

            return $rgb;
        }, ColorScheme::Dark);

        $rewriter->line("\e[38;2;9;9;9;48;2;1;2;3mx");

        $this->assertContains([1, 2, 3], $behind, 'The text is over its cell background.');
        $this->assertContains([0, 0, 0], $behind, 'The background is over the terminal one, of the color scheme.');
    }
}
