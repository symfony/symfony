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

    public function testFindWithoutSuffix()
    {
        $fixturesDir = __DIR__.\DIRECTORY_SEPARATOR.'Fixtures';
        $name = 'executable_without_suffix';

        $finder = new ExecutableFinder();
        $result = $finder->find($name, null, [$fixturesDir]);

        $this->assertSamePath($fixturesDir.\DIRECTORY_SEPARATOR.$name, $result);
    }

    public function testFindWithAddedSuffixes()
    {
        $fixturesDir = __DIR__.\DIRECTORY_SEPARATOR.'Fixtures';
        $name = 'executable_with_added_suffix';
        $suffix = '.foo';

        $finder = new ExecutableFinder();
        $finder->addSuffix($suffix);

        $result = $finder->find($name, null, [$fixturesDir]);

        $this->assertSamePath($fixturesDir.\DIRECTORY_SEPARATOR.$name.$suffix, $result);
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

    /**
     * @runInSeparateProcess
     */
    public function testFindBatchExecutableOnWindows()
    {
        if (\ini_get('open_basedir')) {
            $this->markTestSkipped('Cannot test when open_basedir is set');
        }
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Can be only tested on windows');
        }

        $tempDir = realpath(sys_get_temp_dir());
        $target = str_replace('.tmp', '_tmp', tempnam($tempDir, 'example-windows-executable'));

        try {
            touch($target);
            touch($target.'.BAT');

            $this->assertFalse(is_executable($target));

            putenv('PATH='.$tempDir);

            $finder = new ExecutableFinder();
            $result = $finder->find(basename($target), false);
        } finally {
            unlink($target);
            unlink($target.'.BAT');
        }

        $this->assertSamePath($target.'.BAT', $result);
    }

    /**
     * @runInSeparateProcess
     */
    public function testEmptyDirInPath()
    {
        putenv(\sprintf('PATH=%s%s', \dirname(\PHP_BINARY), \PATH_SEPARATOR));

        try {
            touch('executable');
            chmod('executable', 0700);

            $finder = new ExecutableFinder();
            $result = $finder->find('executable');

            $this->assertSame(\sprintf('.%sexecutable', \DIRECTORY_SEPARATOR), $result);
        } finally {
            unlink('executable');
        }
    }

    public function testFindBuiltInCommandOnWindows()
    {
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Can be only tested on windows');
        }

        $finder = new ExecutableFinder();
        $this->assertSame('rmdir', strtolower($finder->find('RMDIR')));
        $this->assertSame('cd', strtolower($finder->find('cd')));
        $this->assertSame('move', strtolower($finder->find('MoVe')));
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
