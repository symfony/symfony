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
use Symfony\Component\Console\Formatter\NullOutputFormatterStyle;

/**
 * @author Tien Xuan Vo <tien.xuan.vo@gmail.com>
 */
class NullOutputFormatterStyleTest extends TestCase
{
    public function testApply()
    {
        $style = new NullOutputFormatterStyle();

        $this->assertSame('foo', $style->apply('foo'));
    }

    public function testSetForeground()
    {
        $style = new NullOutputFormatterStyle();
        $style->setForeground('black');
        $this->assertSame('foo', $style->apply('foo'));
    }

    public function testSetBackground()
    {
        $style = new NullOutputFormatterStyle();
        $style->setBackground('blue');
        $this->assertSame('foo', $style->apply('foo'));
    }

    public function testOptions()
    {
        $style = new NullOutputFormatterStyle();

        $style->setOptions(['reverse', 'conceal']);
        $this->assertSame('foo', $style->apply('foo'));

        $style->setOption('bold');
        $this->assertSame('foo', $style->apply('foo'));

        $style->unsetOption('reverse');
        $this->assertSame('foo', $style->apply('foo'));
    }
}
