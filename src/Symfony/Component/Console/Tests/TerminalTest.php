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

use Symfony\Component\Console\Terminal;

class TerminalTest extends \PHPUnit_Framework_TestCase
{
    public function test()
    {
        $terminal = new Terminal();
        $terminal->setWidth(100);
        $terminal->setHeight(50);
        $this->assertSame(100, $terminal->getWidth());
        $this->assertSame(50, $terminal->getHeight());
    }
}
