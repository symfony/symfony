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

use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class OutputFormatterStyleTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $style = new OutputFormatterStyle('green', 'black', array('bold', 'underscore'));
        $this->assertEquals('fg=green;bg=black;options=bold;underscore', $style->getDefinition());

        $style = new OutputFormatterStyle('red', null, array('blink'));
        $this->assertEquals('fg=red;options=blink', $style->getDefinition());

        $style = new OutputFormatterStyle(null, 'white');
        $this->assertEquals('bg=white', $style->getDefinition());
    }

    public function testForeground()
    {
        $style = new OutputFormatterStyle();

        $style->setForeground('black');
        $this->assertEquals('fg=black', $style->getDefinition());

        $style->setForeground('blue');
        $this->assertEquals('fg=blue', $style->getDefinition());

        $this->setExpectedException('InvalidArgumentException');
        $style->setForeground('undefined-color');
    }

    public function testBackground()
    {
        $style = new OutputFormatterStyle();

        $style->setBackground('black');
        $this->assertEquals('bg=black', $style->getDefinition());

        $style->setBackground('yellow');
        $this->assertEquals('bg=yellow', $style->getDefinition());

        $this->setExpectedException('InvalidArgumentException');
        $style->setBackground('undefined-color');
    }

    public function testOptions()
    {
        $style = new OutputFormatterStyle();

        $style->setOptions(array('reverse', 'conceal'));
        $this->assertEquals('options=reverse;conceal', $style->getDefinition());

        $style->setOption('bold');
        $this->assertEquals('options=reverse;conceal;bold', $style->getDefinition());

        $style->unsetOption('reverse');
        $this->assertEquals('options=conceal;bold', $style->getDefinition());

        $style->setOption('bold');
        $this->assertEquals('options=conceal;bold', $style->getDefinition());

        $style->setOptions(array('bold'));
        $this->assertEquals('options=bold', $style->getDefinition());

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
