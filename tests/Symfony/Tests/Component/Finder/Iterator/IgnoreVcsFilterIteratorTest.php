<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Finder\Iterator;

use Symfony\Component\Finder\Iterator\IgnoreVcsFilterIterator;

require_once __DIR__.'/RealIteratorTestCase.php';

class IgnoreVcsFilterIteratorTest extends RealIteratorTestCase
{
    public function testAccept()
    {
        $inner = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(sys_get_temp_dir().'/symfony2_finder', \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        //$inner = new Iterator(array('/.git/test.php', '/foo/test.py', '/bar/foo.php'));

        $iterator = new IgnoreVcsFilterIterator($inner);
        $tmpDir = sys_get_temp_dir().'/symfony2_finder';

        $this->assertIterator(array(
            $tmpDir.DIRECTORY_SEPARATOR.'test.py',
            $tmpDir.DIRECTORY_SEPARATOR.'foo',
            $tmpDir.DIRECTORY_SEPARATOR.'foo'.DIRECTORY_SEPARATOR.'bar.tmp',
            $tmpDir.DIRECTORY_SEPARATOR.'test.php',
            $tmpDir.DIRECTORY_SEPARATOR.'toto'
        ), $iterator);
    }
}
