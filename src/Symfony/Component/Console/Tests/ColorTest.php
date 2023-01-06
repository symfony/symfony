<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Color;
use Symfony\Component\Console\Output\AnsiColorMode;
use Symfony\Component\Console\Terminal;

class ColorTest extends TestCase
{
    public function testAnsi4Colors()
    {
        $color = new Color();
        $this->assertSame(' ', $color->apply(' '));

        $color = new Color('red', 'yellow');
        $this->assertSame("\033[31;43m \033[39;49m", $color->apply(' '));

        $color = new Color('bright-red', 'bright-yellow');
        $this->assertSame("\033[91;103m \033[39;49m", $color->apply(' '));

        $color = new Color('red', 'yellow', ['underscore']);
        $this->assertSame("\033[31;43;4m \033[39;49;24m", $color->apply(' '));
    }

    public function testTrueColors()
    {
        Terminal::setColorMode(AnsiColorMode::Ansi24);

        try {
            $color = new Color('#fff', '#000');
            $this->assertSame("\033[38;2;255;255;255;48;2;0;0;0m \033[39;49m", $color->apply(' '));

            $color = new Color('#ffffff', '#000000');
            $this->assertSame("\033[38;2;255;255;255;48;2;0;0;0m \033[39;49m", $color->apply(' '));
        } finally {
            Terminal::setColorMode(null);
        }
    }

    public function testDegradedTrueColorsToAnsi4()
    {
        Terminal::setColorMode(AnsiColorMode::Ansi4);

        try {
            $color = new Color('#f00', '#ff0');
            $this->assertSame("\033[31;43m \033[39;49m", $color->apply(' '));

            $color = new Color('#c0392b', '#f1c40f');
            $this->assertSame("\033[31;43m \033[39;49m", $color->apply(' '));
        } finally {
            Terminal::setColorMode(null);
        }
    }

    public function testDegradedTrueColorsToAnsi8()
    {
        Terminal::setColorMode(AnsiColorMode::Ansi8);

        try {
            $color = new Color('#f57255', '#8993c0');
            $this->assertSame("\033[38;5;210;48;5;146m \033[39;49m", $color->apply(' '));

            $color = new Color('#000000', '#ffffff');
            $this->assertSame("\033[38;5;16;48;5;231m \033[39;49m", $color->apply(' '));
        } finally {
            Terminal::setColorMode(null);
        }
    }
}
