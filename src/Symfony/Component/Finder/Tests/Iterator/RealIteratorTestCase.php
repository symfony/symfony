<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Tests\Iterator;

abstract class RealIteratorTestCase extends IteratorTestCase
{
    protected static $tmpDir;
    protected static $files;

    public static function setUpBeforeClass(): void
    {
        self::$tmpDir = realpath(sys_get_temp_dir()).\DIRECTORY_SEPARATOR.'symfony_finder';

        self::$files = [
            '.git/',
            '.foo/',
            '.foo/.bar',
            '.foo/bar',
            '.bar',
            'test.py',
            'foo/',
            'foo/bar.tmp',
            'test.php',
            'toto/',
            'toto/foo/',
            'toto/.git/',
            'foo bar',
            'qux_0_1.php',
            'qux_2_0.php',
            'qux_10_2.php',
            'qux_12_0.php',
            'qux_1000_1.php',
            'qux_1002_0.php',
            'qux/',
            'qux/baz_1_2.py',
            'qux/baz_100_1.py',
        ];

        self::$files = self::toAbsolute(self::$files);

        if (is_dir(self::$tmpDir)) {
            self::tearDownAfterClass();
        } else {
            mkdir(self::$tmpDir);
        }

        foreach (self::$files as $file) {
            if (\DIRECTORY_SEPARATOR === $file[\strlen($file) - 1]) {
                mkdir($file);
            } else {
                touch($file);
            }
        }

        file_put_contents(self::toAbsolute('test.php'), str_repeat(' ', 800));
        file_put_contents(self::toAbsolute('test.py'), str_repeat(' ', 2000));

        touch(self::toAbsolute('foo/bar.tmp'), strtotime('2005-10-15'));
        touch(self::toAbsolute('test.php'), strtotime('2005-10-15'));
    }

    public static function tearDownAfterClass(): void
    {
        $paths = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::$tmpDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($paths as $path) {
            if ($path->isDir()) {
                if ($path->isLink()) {
                    @unlink($path);
                } else {
                    @rmdir($path);
                }
            } else {
                @unlink($path);
            }
        }
    }

    protected static function toAbsolute($files = null)
    {
        /*
         * Without the call to setUpBeforeClass() property can be null.
         */
        if (!self::$tmpDir) {
            self::$tmpDir = realpath(sys_get_temp_dir()).\DIRECTORY_SEPARATOR.'symfony_finder';
        }

        if (\is_array($files)) {
            $f = [];
            foreach ($files as $file) {
                if (\is_array($file)) {
                    $f[] = self::toAbsolute($file);
                } else {
                    $f[] = self::$tmpDir.\DIRECTORY_SEPARATOR.str_replace('/', \DIRECTORY_SEPARATOR, $file);
                }
            }

            return $f;
        }

        if (\is_string($files)) {
            return self::$tmpDir.\DIRECTORY_SEPARATOR.str_replace('/', \DIRECTORY_SEPARATOR, $files);
        }

        return self::$tmpDir;
    }

    protected static function toAbsoluteFixtures($files)
    {
        $f = [];
        foreach ($files as $file) {
            $f[] = realpath(__DIR__.\DIRECTORY_SEPARATOR.'..'.\DIRECTORY_SEPARATOR.'Fixtures'.\DIRECTORY_SEPARATOR.$file);
        }

        return $f;
    }
}
