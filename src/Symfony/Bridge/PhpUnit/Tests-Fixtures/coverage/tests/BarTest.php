<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use PHPUnit\Framework\TestCase;

class BarTest extends TestCase
{
    public function testBar()
    {
        $foo = new Foo();
        $bar = new Bar($foo);

        $this->assertSame('bar', $bar->barZ());
    }
}
