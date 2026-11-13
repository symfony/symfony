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
use Symfony\Component\Tui\Render\BackdropTint;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\ColorScheme;

final class BackdropTintTest extends TestCase
{
    public function testColorsAreBlendedTowardTheTint()
    {
        $tint = new BackdropTint(Color::hex('#0000ff'), 50, ColorScheme::Dark);
        [$line] = $tint->apply(["a\e[1;38;2;255;0;0;48;2;0;255;0mb\e[0mc"], 3, 1);

        $this->assertSame('abc', AnsiUtils::stripAnsiCodes($line));
        $this->assertStringStartsWith("\e[38;2;115;115;242;49ma", $line, 'The default foreground, on the terminal background.');
        $this->assertStringContainsString("\e[1m\e[38;2;128;0;128;48;2;0;128;128mb", $line, 'Other attributes stay.');
        $this->assertStringContainsString("\e[0m\e[38;2;115;115;242;49mc", $line, 'A reset goes back to the defaults, tinted.');
    }

    public function testPaletteColorsAndTheLightScheme()
    {
        $tint = new BackdropTint(Color::hex('#ffffff'), 50, ColorScheme::Light);
        [$line] = $tint->apply(["\e[31;44mx"], 1, 1);

        $red = Color::palette(1)->toRgb();
        $this->assertStringContainsString(\sprintf('38;2;%d;%d;%d', round($red['r'] + (255 - $red['r']) / 2), round($red['g'] + (255 - $red['g']) / 2), round($red['b'] + (255 - $red['b']) / 2)), $line);
        $this->assertStringStartsWith("\e[38;2;128;128;128;49m", $line, 'Black text, the default of a light scheme, toward white.');
    }

    public function testAnOpaqueTintPaintsEveryCell()
    {
        $tint = new BackdropTint(Color::hex('#202020'), 100, ColorScheme::Dark);
        $lines = $tint->apply(['ab'], 4, 2);

        $this->assertCount(2, $lines, 'Up to the rows.');
        $this->assertSame(['ab  ', '    '], array_map(AnsiUtils::stripAnsiCodes(...), $lines), 'Up to the columns.');
        $this->assertStringStartsWith("\e[38;2;32;32;32;48;2;32;32;32m", $lines[1]);
    }
}
