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

use Symfony\Components\Finder\Iterator\IgnoreVcsFilterIterator;

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
            $tmpDir.'/test.py',
            $tmpDir.'/foo',
            $tmpDir.'/foo/bar.tmp',
            $tmpDir.'/test.php',
            $tmpDir.'/toto'
        ), $iterator);
    }
}
