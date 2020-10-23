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
     * tests findByName() with the constant PHP_BINARY.
     */
    public function testFindByName()
    {
        $f = new PhpExecutableFinder();

        $current = \PHP_BINARY;
        $name = basename($current);

        $this->assertEquals($current, $f->findByName($name), '::findByName() returns the executable PHP');
    }

    /**
     * tests tryNames() with the constant PHP_BINARY.
     */
    public function testTryNames()
    {
        $f = new PhpExecutableFinder();

        $current = \PHP_BINARY;
        $real = basename($current);

        $this->assertEquals($current, $f->tryNames(['php-foo', $real]), '::tryNames() returns the executable PHP');
    }

    /**
     * tests findByVersion() with the constant PHP_BINARY.
     */
    public function testFindByVersion()
    {
        $f = new PhpExecutableFinder();

        $version = \PHP_MAJOR_VERSION . '.' . \PHP_MINOR_VERSION;
        $dir = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . uniqid('symfony', true);
        $file = $dir . \DIRECTORY_SEPARATOR . 'php' . $version;

        mkdir($dir);
        copy(\PHP_BINARY, $file);
        chmod($file, 0777);

        $this->assertEquals($file, $f->findByVersion($version, [$dir]), '::findByVersion() returns the executable PHP');

        unlink($file);
        rmdir($dir);
    }

    /**
     * tests tryVersions() with the constant PHP_BINARY.
     */
    public function testTryVersions()
    {
        $f = new PhpExecutableFinder();

        $version = \PHP_MAJOR_VERSION . '.' . \PHP_MINOR_VERSION;
        $versionWrong = \PHP_MAJOR_VERSION . '.' . (\PHP_MINOR_VERSION + 1);
        $dir = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . uniqid('symfony', true);
        $file = $dir . \DIRECTORY_SEPARATOR . 'php' . $version;

        mkdir($dir);
        copy(\PHP_BINARY, $file);
        chmod($file, 0777);

        $this->assertEquals($file, $f->tryVersions([$versionWrong, $version], [$dir]), '::tryVersions() returns the executable PHP');

        unlink($file);
        rmdir($dir);
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
        $phpBinaryEnv = \PHP_BINARY;
        putenv('PHP_BINARY=/usr/local/php/bin/php-invalid');

        $this->assertFalse($f->find(), '::find() returns false because of not exist file');
        $this->assertFalse($f->find(false), '::find(false) returns false because of not exist file');

        putenv('PHP_BINARY='.$phpBinaryEnv);
    }
}
