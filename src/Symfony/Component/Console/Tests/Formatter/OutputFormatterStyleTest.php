<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Formatter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class OutputFormatterStyleTest extends TestCase
{
    public function testConstructor()
    {
        $style = new OutputFormatterStyle('green', 'black', ['bold', 'underscore']);
        self::assertEquals("\033[32;40;1;4mfoo\033[39;49;22;24m", $style->apply('foo'));

        $style = new OutputFormatterStyle('red', null, ['blink']);
        self::assertEquals("\033[31;5mfoo\033[39;25m", $style->apply('foo'));

        $style = new OutputFormatterStyle(null, 'white');
        self::assertEquals("\033[47mfoo\033[49m", $style->apply('foo'));
    }

    public function testForeground()
    {
        $style = new OutputFormatterStyle();

        $style->setForeground('black');
        self::assertEquals("\033[30mfoo\033[39m", $style->apply('foo'));

        $style->setForeground('blue');
        self::assertEquals("\033[34mfoo\033[39m", $style->apply('foo'));

        $style->setForeground('default');
        self::assertEquals("\033[39mfoo\033[39m", $style->apply('foo'));

        self::expectException(\InvalidArgumentException::class);
        $style->setForeground('undefined-color');
    }

    public function testBackground()
    {
        $style = new OutputFormatterStyle();

        $style->setBackground('black');
        self::assertEquals("\033[40mfoo\033[49m", $style->apply('foo'));

        $style->setBackground('yellow');
        self::assertEquals("\033[43mfoo\033[49m", $style->apply('foo'));

        $style->setBackground('default');
        self::assertEquals("\033[49mfoo\033[49m", $style->apply('foo'));

        self::expectException(\InvalidArgumentException::class);
        $style->setBackground('undefined-color');
    }

    public function testOptions()
    {
        $style = new OutputFormatterStyle();

        $style->setOptions(['reverse', 'conceal']);
        self::assertEquals("\033[7;8mfoo\033[27;28m", $style->apply('foo'));

        $style->setOption('bold');
        self::assertEquals("\033[7;8;1mfoo\033[27;28;22m", $style->apply('foo'));

        $style->unsetOption('reverse');
        self::assertEquals("\033[8;1mfoo\033[28;22m", $style->apply('foo'));

        $style->setOption('bold');
        self::assertEquals("\033[8;1mfoo\033[28;22m", $style->apply('foo'));

        $style->setOptions(['bold']);
        self::assertEquals("\033[1mfoo\033[22m", $style->apply('foo'));

        try {
            $style->setOption('foo');
            self::fail('->setOption() throws an \InvalidArgumentException when the option does not exist in the available options');
        } catch (\Exception $e) {
            self::assertInstanceOf(\InvalidArgumentException::class, $e, '->setOption() throws an \InvalidArgumentException when the option does not exist in the available options');
            self::assertStringContainsString('Invalid option specified: "foo"', $e->getMessage(), '->setOption() throws an \InvalidArgumentException when the option does not exist in the available options');
        }
    }

    public function testHref()
    {
        $prevTerminalEmulator = getenv('TERMINAL_EMULATOR');
        putenv('TERMINAL_EMULATOR');

        $style = new OutputFormatterStyle();

        try {
            $style->setHref('idea://open/?file=/path/SomeFile.php&line=12');
            self::assertSame("\e]8;;idea://open/?file=/path/SomeFile.php&line=12\e\\some URL\e]8;;\e\\", $style->apply('some URL'));
        } finally {
            putenv('TERMINAL_EMULATOR'.($prevTerminalEmulator ? "=$prevTerminalEmulator" : ''));
        }
    }
}
