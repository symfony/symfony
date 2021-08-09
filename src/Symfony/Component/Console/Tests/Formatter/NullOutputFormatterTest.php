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
use Symfony\Component\Console\Formatter\NullOutputFormatter;
use Symfony\Component\Console\Formatter\NullOutputFormatterStyle;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

/**
 * @author Tien Xuan Vo <tien.xuan.vo@gmail.com>
 */
class NullOutputFormatterTest extends TestCase
{
    public function testFormat()
    {
        $formatter = new NullOutputFormatter();

        $this->assertNull($formatter->format('this message will be destroyed'));
    }

    public function testGetStyle()
    {
        $formatter = new NullOutputFormatter();
        $this->assertInstanceof(NullOutputFormatterStyle::class, $style = $formatter->getStyle('null'));
        $this->assertSame($style, $formatter->getStyle('null'));
    }

    public function testSetStyle()
    {
        $formatter = new NullOutputFormatter();
        $style = new OutputFormatterStyle();
        $formatter->setStyle('null', $style);
        $this->assertNotSame($style, $formatter->getStyle('null'));
    }

    public function testHasStyle()
    {
        $formatter = new NullOutputFormatter();
        $this->assertFalse($formatter->hasStyle('null'));
    }

    public function testIsDecorated()
    {
        $formatter = new NullOutputFormatter();
        $this->assertFalse($formatter->isDecorated());
    }

    public function testSetDecorated()
    {
        $formatter = new NullOutputFormatter();
        $formatter->setDecorated(true);
        $this->assertFalse($formatter->isDecorated());
    }
}
