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

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Formatter\OutputFormatterDecorator;

class OutputFormatterDecoratorTest extends \PHPUnit_Framework_TestCase
{
    public function testDecorator()
    {
        $decorator = new OutputFormatterDecorator();

        $style = new OutputFormatterStyle('green', 'black', array('bold', 'underscore'));
        $this->assertEquals("\033[32;40;1;4mfoo\033[39;49;22;24m", $decorator->decorate('foo', $style));

        $style = new OutputFormatterStyle('red', null, array('blink'));
        $this->assertEquals("\033[31;5mfoo\033[39;25m", $decorator->decorate('foo', $style));

        $style = new OutputFormatterStyle(null, 'white');
        $this->assertEquals("\033[47mfoo\033[49m", $decorator->decorate('foo', $style));
    }

    public function testFormatterWithDecorator()
    {
        $decorator = new OutputFormatterDecorator();
        $formatter = new OutputFormatter($decorator);
        
        $style = new OutputFormatterStyle('green', 'black', array('bold', 'underscore'));
        $formatter->setStyle('bar', $style);

        $this->assertEquals("\033[32;40;1;4mfoo\033[39;49;22;24m", $formatter->format('<bar>foo</bar>'));
    }
}
