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
use Symfony\Component\Process\ExecutableFinder;

/**
 * @author Chris Smith <chris@cs278.org>
 */
class ExecutableFinderTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('PATH='.($_SERVER['PATH'] ?? $_SERVER['Path']));
    }

    public function testFind()
    {
        if (\ini_get('open_basedir')) {
            $this->markTestSkipped('Cannot test when open_basedir is set');
        }

        putenv('PATH='.\dirname(\PHP_BINARY));

        $finder = new ExecutableFinder();
        $result = $finder->find($this->getPhpBinaryName());

        $this->assertSamePath(\PHP_BINARY, $result);
    }

    public function testFindWithDefault()
    {
        if (\ini_get('open_basedir')) {
            $this->markTestSkipped('Cannot test when open_basedir is set');
        }

        $expected = 'defaultValue';

        putenv('PATH=');

        $finder = new ExecutableFinder();
        $result = $finder->find('foo', $expected);

        $this->assertEquals($expected, $result);
    }

    public function testFindWithNullAsDefault()
    {
        if (\ini_get('open_basedir')) {
            $this->markTestSkipped('Cannot test when open_basedir is set');
        }

        putenv('PATH=');

        $finder = new ExecutableFinder();

        $result = $finder->find('foo');

        $this->assertNull($result);
    }

    public function testFindWithExtraDirs()
    {
        if (\ini_get('open_basedir')) {
            $this->markTestSkipped('Cannot test when open_basedir is set');
        }

        putenv('PATH=');

        $extraDirs = [\dirname(\PHP_BINARY)];

        $finder = new ExecutableFinder();
        $result = $finder->find($this->getPhpBinaryName(), null, $extraDirs);

        $this->assertSamePath(\PHP_BINARY, $result);
    }

    /**
     * @runInSeparateProcess
     */
    public function testFindWithOpenBaseDir()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Cannot run test on windows');
        }

        if (\ini_get('open_basedir')) {
            $this->markTestSkipped('Cannot test when open_basedir is set');
        }

        putenv('PATH='.\dirname(\PHP_BINARY));
        $initialOpenBaseDir = ini_set('open_basedir', \dirname(\PHP_BINARY).\PATH_SEPARATOR.'/');

        try {
            $finder = new ExecutableFinder();
            $result = $finder->find($this->getPhpBinaryName());

            $this->assertSamePath(\PHP_BINARY, $result);
        } finally {
            ini_set('open_basedir', $initialOpenBaseDir);
        }
    }

    public function testFindBatchExecutableOnWindows()
    {
        if (\ini_get('open_basedir')) {
            $this->markTestSkipped('Cannot test when open_basedir is set');
        }
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Can be only tested on windows');
        }

        $target = tempnam(sys_get_temp_dir(), 'example-windows-executable');

        touch($target);
        touch($target.'.BAT');

        $this->assertFalse(is_executable($target));

        putenv('PATH='.sys_get_temp_dir());

        $finder = new ExecutableFinder();
        $result = $finder->find(basename($target), false);

        unlink($target);
        unlink($target.'.BAT');

        $this->assertSamePath($target.'.BAT', $result);
    }

    private function assertSamePath($expected, $tested)
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->assertEquals(strtolower($expected), strtolower($tested));
        } else {
            $this->assertEquals($expected, $tested);
        }
    }

    private function getPhpBinaryName()
    {
        return basename(\PHP_BINARY, '\\' === \DIRECTORY_SEPARATOR ? '.exe' : '');
    }
}
