<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Process\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Process\PhpExecutableFinder;

/**
 * @author Robert Schönthal <seroscho@googlemail.com>
 */
class PhpExecutableFinderTest extends TestCase
{
    /**
     * tests find() with the constant PHP_BINARY.
     */
    public function testFind()
    {
        $f = new PhpExecutableFinder();

        $current = PHP_BINARY;
        $args = 'phpdbg' === PHP_SAPI ? ' -qrr' : '';

        $this->assertEquals($current.$args, $f->find(), '::find() returns the executable PHP');
        $this->assertEquals($current, $f->find(false), '::find() returns the executable PHP');
    }

    /**
     * tests find() with the env var PHP_PATH.
     */
    public function testFindArguments()
    {
        $f = new PhpExecutableFinder();

        if ('phpdbg' === PHP_SAPI) {
            $this->assertEquals($f->findArguments(), array('-qrr'), '::findArguments() returns phpdbg arguments');
        } else {
            $this->assertEquals($f->findArguments(), array(), '::findArguments() returns no arguments');
        }
    }
}
