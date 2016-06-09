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
    public function testGetDimensions()
    {
        $terminal = new Terminal();
        $dimensions = $terminal->getDimensions();
        $this->assertCount(2, $dimensions);

        $terminal->setDimensions(100, 50);
        $this->assertSame(array(100, 50), $terminal->getDimensions());
        $this->assertSame(100, $terminal->getWidth());
        $this->assertSame(50, $terminal->getHeight());
    }
}
