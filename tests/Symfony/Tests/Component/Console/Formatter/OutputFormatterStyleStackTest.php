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

use Symfony\Component\Console\Formatter\OutputFormatterStyleStack;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class OutputFormatterStyleStackTest extends \PHPUnit_Framework_TestCase
{
    public function testPush()
    {
        $stack = new OutputFormatterStyleStack();
        $stack->pushStyle(new OutputFormatterStyle('white'));
        $stack->pushStyle(new OutputFormatterStyle(null, 'black'));
        $stack->pushStyle(new OutputFormatterStyle(null, null, array('bold')));

        $style = $stack->getCurrentStyle();
        $this->assertEquals('white', $style->getForeground());
        $this->assertEquals('black', $style->getBackground());
        $this->assertEquals(array('bold'), $style->getOptions());

        $stack->pushStyle(new OutputFormatterStyle('yellow', null, array('blink')));

        $style = $stack->getCurrentStyle();
        $this->assertEquals('yellow', $style->getForeground());
        $this->assertEquals('black', $style->getBackground());
        $this->assertEquals(array('bold', 'blink'), $style->getOptions());
    }

    public function testPop()
    {
        $stack = new OutputFormatterStyleStack();
        $stack->pushStyle(new OutputFormatterStyle('white', 'black', array('blink', 'bold')));
        $stack->pushStyle(new OutputFormatterStyle('yellow', 'blue'));

        $style = $stack->getCurrentStyle();
        $this->assertEquals('yellow', $style->getForeground());
        $this->assertEquals('blue', $style->getBackground());
        $this->assertEquals(array('blink', 'bold'), $style->getOptions());

        $stack->popStyle(new OutputFormatterStyle(null, 'blue', array('blink')));

        $style = $stack->getCurrentStyle();
        $this->assertEquals('yellow', $style->getForeground());
        $this->assertEquals('black', $style->getBackground());
        $this->assertEquals(array('bold'), $style->getOptions());

        $stack->popStyle(new OutputFormatterStyle('yellow', 'black', array('bold')));

        $style = $stack->getCurrentStyle();
        $this->assertEquals('white', $style->getForeground());
        $this->assertEquals(null, $style->getBackground());
        $this->assertEquals(array(), $style->getOptions());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidPop()
    {
        $stack = new OutputFormatterStyleStack();
        $stack->pushStyle(new OutputFormatterStyle('white', 'black', array('blink', 'bold')));
        $stack->popStyle(new OutputFormatterStyle('yellow'));
    }
}
