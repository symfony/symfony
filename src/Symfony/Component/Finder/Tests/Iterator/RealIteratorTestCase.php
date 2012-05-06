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
    static protected $files;

    static public function setUpBeforeClass()
    {
        $tmpDir = sys_get_temp_dir().'/symfony2_finder';
        self::$files = array(
            $tmpDir.'/.git/',
            $tmpDir.'/.foo/',
            $tmpDir.'/.foo/.bar',
            $tmpDir.'/.bar',
            $tmpDir.'/test.py',
            $tmpDir.'/foo/',
            $tmpDir.'/foo/bar.tmp',
            $tmpDir.'/test.php',
            $tmpDir.'/toto/'
        );

        if (is_dir($tmpDir)) {
            self::tearDownAfterClass();
        } else {
            mkdir($tmpDir);
        }

        foreach (self::$files as $file) {
            if ('/' === $file[strlen($file) - 1]) {
                mkdir($file);
            } else {
                touch($file);
            }
        }

        file_put_contents($tmpDir.'/test.php', str_repeat(' ', 800));
        file_put_contents($tmpDir.'/test.py', str_repeat(' ', 2000));

        touch($tmpDir.'/foo/bar.tmp', strtotime('2005-10-15'));
        touch($tmpDir.'/test.php', strtotime('2005-10-15'));
    }

    static public function tearDownAfterClass()
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
