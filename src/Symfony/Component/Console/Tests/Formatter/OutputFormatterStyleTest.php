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
        $style = new OutputFormatterStyle('green', 'black', array('bold', 'underscore'));
        $this->assertEquals("\033[32;40;1;4mfoo\033[39;49;22;24m", $style->apply('foo'));

        $style = new OutputFormatterStyle('red', null, array('blink'));
        $this->assertEquals("\033[31;5mfoo\033[39;25m", $style->apply('foo'));

        $style = new OutputFormatterStyle(null, 'white');
        $this->assertEquals("\033[47mfoo\033[49m", $style->apply('foo'));
    }

    public function testForeground()
    {
        $style = new OutputFormatterStyle();

        $style->setForeground(null);
        $this->assertEquals('foo', $style->apply('foo'));

        $style->setForeground('black');
        $this->assertEquals("\033[30mfoo\033[39m", $style->apply('foo'));

        $style->setForeground('red');
        $this->assertEquals("\033[31mfoo\033[39m", $style->apply('foo'));

        $style->setForeground('default');
        $this->assertEquals("\033[39mfoo\033[39m", $style->apply('foo'));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('InvalidArgumentException');
        $style->setForeground('undefined-color');
    }

    public function testForegroundBright()
    {
        $style = new OutputFormatterStyle();

        $style->setForeground('bright-green');
        $this->assertEquals("\033[92mfoo\033[39m", $style->apply('foo'));

        $style->setForeground('bright-yellow');
        $this->assertEquals("\033[93mfoo\033[39m", $style->apply('foo'));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('InvalidArgumentException');
        $style->setForeground('bright-undefined-color');
    }

    public function testForegroundMode()
    {
        $style = new OutputFormatterStyle();

        for ($i = 0; $i <= 255; ++$i) {
            $style->setForeground(sprintf('mode-%d', $i));
            $this->assertEquals(sprintf("\033[38;5;%dmfoo\033[39m", $i), $style->apply('foo'));
        }

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('InvalidArgumentException');
        $style->setForeground('mode-500');
    }

    public function testBackground()
    {
        $style = new OutputFormatterStyle();

        $style->setBackground(null);
        $this->assertEquals('foo', $style->apply('foo'));

        $style->setBackground('blue');
        $this->assertEquals("\033[44mfoo\033[49m", $style->apply('foo'));

        $style->setBackground('magenta');
        $this->assertEquals("\033[45mfoo\033[49m", $style->apply('foo'));

        $style->setBackground('default');
        $this->assertEquals("\033[49mfoo\033[49m", $style->apply('foo'));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('InvalidArgumentException');
        $style->setBackground('undefined-color');
    }

    public function testBackgroundBright()
    {
        $style = new OutputFormatterStyle();

        $style->setBackground('bright-cyan');
        $this->assertEquals("\033[106mfoo\033[49m", $style->apply('foo'));

        $style->setBackground('bright-white');
        $this->assertEquals("\033[107mfoo\033[49m", $style->apply('foo'));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('InvalidArgumentException');
        $style->setBackground('bright-undefined-color');
    }

    public function testBackgroundMode()
    {
        $style = new OutputFormatterStyle();

        for ($i = 0; $i <= 255; ++$i) {
            $style->setBackground(sprintf('mode-%d', $i));
            $this->assertEquals(sprintf("\033[48;5;%dmfoo\033[49m", $i), $style->apply('foo'));
        }

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('InvalidArgumentException');
        $style->setBackground('mode-500');
    }

    public function testOptions()
    {
        $style = new OutputFormatterStyle();

        $style->setOptions(array('reverse', 'conceal'));
        $this->assertEquals("\033[7;8mfoo\033[27;28m", $style->apply('foo'));

        $style->setOption('bold');
        $this->assertEquals("\033[7;8;1mfoo\033[27;28;22m", $style->apply('foo'));

        $style->unsetOption('reverse');
        $this->assertEquals("\033[8;1mfoo\033[28;22m", $style->apply('foo'));

        $style->setOption('bold');
        $this->assertEquals("\033[8;1mfoo\033[28;22m", $style->apply('foo'));

        $style->setOptions(array('bold'));
        $this->assertEquals("\033[1mfoo\033[22m", $style->apply('foo'));

        try {
            $style->setOption('foo');
            $this->fail('->setOption() throws an \InvalidArgumentException when the option does not exist in the available options');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->setOption() throws an \InvalidArgumentException when the option does not exist in the available options');
            $this->assertContains('Invalid option specified: "foo"', $e->getMessage(), '->setOption() throws an \InvalidArgumentException when the option does not exist in the available options');
        }

        try {
            $style->unsetOption('foo');
            $this->fail('->unsetOption() throws an \InvalidArgumentException when the option does not exist in the available options');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->unsetOption() throws an \InvalidArgumentException when the option does not exist in the available options');
            $this->assertContains('Invalid option specified: "foo"', $e->getMessage(), '->unsetOption() throws an \InvalidArgumentException when the option does not exist in the available options');
        }
    }
}
