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
    protected static $files;
    protected static $tmpDir;

    public static function setUpBeforeClass()
    {
        self::$tmpDir = sys_get_temp_dir().'/symfony2_finder';
        self::$files = array(
            self::$tmpDir.'/.git/',
            self::$tmpDir.'/.foo/',
            self::$tmpDir.'/.foo/.bar',
            self::$tmpDir.'/.bar',
            self::$tmpDir.'/test.py',
            self::$tmpDir.'/foo/',
            self::$tmpDir.'/foo/bar.tmp',
            self::$tmpDir.'/test.php',
            self::$tmpDir.'/toto/',
            self::$tmpDir.'/foo bar',
        );

        if (is_dir(self::$tmpDir)) {
            self::tearDownAfterClass();
        } else {
            mkdir(self::$tmpDir);
        }

        foreach (self::$files as $file) {
            if ('/' === $file[strlen($file) - 1]) {
                mkdir($file);
            } else {
                touch($file);
            }
        }

        file_put_contents(self::$tmpDir.'/test.php', str_repeat(' ', 800));
        file_put_contents(self::$tmpDir.'/test.py', str_repeat(' ', 2000));

        touch(self::$tmpDir.'/foo/bar.tmp', strtotime('2005-10-15'));
        touch(self::$tmpDir.'/test.php', strtotime('2005-10-15'));
    }

    public static function tearDownAfterClass()
    {
        foreach (array_reverse(self::$files) as $file) {
            if ('/' === $file[strlen($file) - 1]) {
                @rmdir($file);
            } else {
                @unlink($file);
            }
        }
    }
}
