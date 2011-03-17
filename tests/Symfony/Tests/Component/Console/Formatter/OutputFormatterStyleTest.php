<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Console\Formatter;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class OutputFormatterStyleTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $style = new OutputFormatterStyle('green', 'black', array('bold', 'underscore'));

        $this->assertEquals("\033[32;40;1;4m", $style->getBeginStyle());
        $this->assertEquals("\033[0m", $style->getEndStyle());

        $style = new OutputFormatterStyle('red', null, array('blink'));

        $this->assertEquals("\033[31;5m", $style->getBeginStyle());
        $this->assertEquals("\033[0m", $style->getEndStyle());

        $style = new OutputFormatterStyle(null, 'white');

        $this->assertEquals("\033[47m", $style->getBeginStyle());
        $this->assertEquals("\033[0m", $style->getEndStyle());
    }

    public function testForeground()
    {
        $style = new OutputFormatterStyle();

        $style->setForeground('black');

        $this->assertEquals("\033[30m", $style->getBeginStyle());
        $this->assertEquals("\033[0m", $style->getEndStyle());

        $style->setForeground('blue');

        $this->assertEquals("\033[34m", $style->getBeginStyle());
        $this->assertEquals("\033[0m", $style->getEndStyle());

        $this->setExpectedException('InvalidArgumentException');

        $style->setForeground('undefined-color');
    }

    public function testBackground()
    {
        $style = new OutputFormatterStyle();

        $style->setBackground('black');

        $this->assertEquals("\033[40m", $style->getBeginStyle());
        $this->assertEquals("\033[0m", $style->getEndStyle());

        $style->setBackground('yellow');

        $this->assertEquals("\033[43m", $style->getBeginStyle());

        $this->setExpectedException('InvalidArgumentException');

        $style->setBackground('undefined-color');
    }

    public function testOptions()
    {
        $style = new OutputFormatterStyle();

        $style->setOptions(array('reverse', 'conceal'));

        $this->assertEquals("\033[7;8m", $style->getBeginStyle());

        $style->setOption('bold');

        $this->assertEquals("\033[7;8;1m", $style->getBeginStyle());

        $style->unsetOption('reverse');

        $this->assertEquals("\033[8;1m", $style->getBeginStyle());

        $style->setOption('bold');

        $this->assertEquals("\033[8;1m", $style->getBeginStyle());

        $style->setOptions(array('bold'));

        $this->assertEquals("\033[1m", $style->getBeginStyle());
    }
}
