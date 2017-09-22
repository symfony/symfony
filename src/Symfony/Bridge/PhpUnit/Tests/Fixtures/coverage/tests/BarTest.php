<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpUnitCoverageTest\Tests;

use PHPUnit\Framework\TestCase;

class BarTest extends TestCase
{
    public function testBar()
    {
        if (!class_exists('PhpUnitCoverageTest\Foo')) {
            $this->markTestSkipped('This test is not part of the main Symfony test suite. It\'s here to test the CoverageListener.');
        }

        $foo = new \PhpUnitCoverageTest\Foo();
        $bar = new \PhpUnitCoverageTest\Bar($foo);

        $this->assertSame('bar', $bar->barZ());
    }
}
