<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\PhpExecutableFinder;

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

        $current = \PHP_BINARY;
        $args = 'phpdbg' === \PHP_SAPI ? ' -qrr' : '';

        $this->assertEquals($current.$args, $f->find(), '::find() returns the executable PHP');
        $this->assertEquals($current, $f->find(false), '::find() returns the executable PHP');
    }

    /**
     * tests find() with the env var PHP_PATH.
     */
    public function testFindArguments()
    {
        $f = new PhpExecutableFinder();

        if ('phpdbg' === \PHP_SAPI) {
            $this->assertEquals(['-qrr'], $f->findArguments(), '::findArguments() returns phpdbg arguments');
        } else {
            $this->assertEquals([], $f->findArguments(), '::findArguments() returns no arguments');
        }
    }

    public function testNotExitsBinaryFile()
    {
        $f = new PhpExecutableFinder();

        $originalPhpBinary = getenv('PHP_BINARY');

        try {
            putenv('PHP_BINARY=/usr/local/php/bin/php-invalid');

            $this->assertFalse($f->find(), '::find() returns false because of not exist file');
            $this->assertFalse($f->find(false), '::find(false) returns false because of not exist file');
        } finally {
            putenv('PHP_BINARY='.$originalPhpBinary);
        }
    }

    public function testFindWithExecutableDirectory()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Directories are not executable on Windows');
        }

        $originalPhpBinary = getenv('PHP_BINARY');

        try {
            $executableDirectoryPath = sys_get_temp_dir().'/PhpExecutableFinderTest_testFindWithExecutableDirectory';
            @mkdir($executableDirectoryPath);
            $this->assertTrue(is_executable($executableDirectoryPath));
            putenv('PHP_BINARY='.$executableDirectoryPath);

            $this->assertFalse((new PhpExecutableFinder())->find());
        } finally {
            putenv('PHP_BINARY='.$originalPhpBinary);
        }
    }
}
