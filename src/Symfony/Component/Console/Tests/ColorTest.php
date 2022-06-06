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

class ColorTest extends TestCase
{
    public function testAnsiColors()
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
        if ('truecolor' !== getenv('COLORTERM')) {
            $this->markTestSkipped('True color not supported.');
        }

        $color = new Color('#fff', '#000');
        $this->assertSame("\033[38;2;255;255;255;48;2;0;0;0m \033[39;49m", $color->apply(' '));

        $color = new Color('#ffffff', '#000000');
        $this->assertSame("\033[38;2;255;255;255;48;2;0;0;0m \033[39;49m", $color->apply(' '));
    }

    public function testDegradedTrueColors()
    {
        $colorterm = getenv('COLORTERM');
        putenv('COLORTERM=');

        try {
            $color = new Color('#f00', '#ff0');
            $this->assertSame("\033[31;43m \033[39;49m", $color->apply(' '));

            $color = new Color('#c0392b', '#f1c40f');
            $this->assertSame("\033[31;43m \033[39;49m", $color->apply(' '));
        } finally {
            putenv('COLORTERM='.$colorterm);
        }
    }
}
