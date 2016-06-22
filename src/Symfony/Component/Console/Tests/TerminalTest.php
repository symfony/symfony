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
        putenv('COLUMNS=100');
        putenv('LINES=50');
        $terminal = new Terminal();
        $this->assertSame(100, $terminal->getWidth());
        $this->assertSame(50, $terminal->getHeight());

        putenv('COLUMNS=120');
        putenv('LINES=60');
        $terminal = new Terminal();
        $this->assertSame(120, $terminal->getWidth());
        $this->assertSame(60, $terminal->getHeight());
    }
}
