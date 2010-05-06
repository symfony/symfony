<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Finder\Iterator;

require_once __DIR__.'/IteratorTestCase.php';

class RealIteratorTestCase extends IteratorTestCase
{
    static protected $files;

    static public function setUpBeforeClass()
    {
        $tmpDir = sys_get_temp_dir().'/symfony2_finder';
        self::$files = array($tmpDir.'/.git', $tmpDir.'/test.py', $tmpDir.'/foo', $tmpDir.'/foo/bar.tmp', $tmpDir.'/test.php', $tmpDir.'/toto');

        if (is_dir($tmpDir))
        {
            self::tearDownAfterClass();
            rmdir($tmpDir);
        }
        mkdir($tmpDir);

        foreach (self::$files as $file)
        {
            if (false !== ($pos = strpos($file, '.')) && '/' !== $file[$pos - 1])
            {
                touch($file);
            }
            else
            {
                mkdir($file);
            }
        }

        file_put_contents($tmpDir.'/test.php', str_repeat(' ', 800));
        file_put_contents($tmpDir.'/test.py', str_repeat(' ', 2000));
    }

    static public function tearDownAfterClass()
    {
        foreach (self::$files as $file)
        {
            if (false !== ($pos = strpos($file, '.')) && '/' !== $file[$pos - 1])
            {
                @unlink($file);
            }
            else
            {
                @rmdir($file);
            }
        }
    }
}
