<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Process;

use Symfony\Component\Process\PhpExecutableFinder;

/**
 * @author Robert Schönthal <seroscho@googlemail.com>
 */
class PhpExecutableFinderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * tests find() with the env var PHP_PATH
     */
    public function testFindWithPHP_PATH()
    {
        $f = new PhpExecutableFinder();

        $current = $f->find();

        //not executable PHP_PATH
        putenv('PHP_PATH=/not/executable/php');
        $this->assertFalse($f->find(), '::find() returns false for not executable php');

        //executable PHP_PATH
        putenv('PHP_PATH='.$current);
        $this->assertEquals($f->find(), $current, '::find() returns the executable php');
    }

    /**
     * tests find() with default executable
     */
    public function testFindWithSuffix()
    {
        putenv('PHP_PATH=');
        putenv('PHP_PEAR_PHP_BIN=');
        $f = new PhpExecutableFinder();

        $current = $f->find();

        //TODO maybe php executable is custom or even windows
        if (false === strstr(PHP_OS, 'WIN')) {
            $this->assertEquals($current, PHP_BINDIR.DIRECTORY_SEPARATOR.'php', '::find() returns the executable php with suffixes');
        }
    }

    /**
     * tests find() with env var PHP_BINDIR
     */
    public function testFindWithPHP_PEAR_PHP_BIN()
    {
        //TODO the code for suffixes in PHP_BINDIR always catches, so the rest cant be tested
        //maybe remove the code or move the PHP_PEAR_PHP_BIN code above

        $this->markTestIncomplete();

        $f = new PhpExecutableFinder();

        $current = $f->find();

        //not executable PHP_PEAR_PHP_BIN
        putenv('PHP_PEAR_PHP_BIN=/not/executable/php');
        $this->assertFalse($f->find(), '::find() returns false for not executable php');

        //executable PHP_PEAR_PHP_BIN
        putenv('PHP_PEAR_PHP_BIN='.$current);
        $this->assertEquals($f->find(), $current, '::find() returns the executable php');
    }
}
