<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Finder\Iterator;

use Symfony\Component\Finder\Iterator\ExcludeDirectoryFilterIterator;

require_once __DIR__.'/RealIteratorTestCase.php';

class ExcludeDirectoryFilterIteratorTest extends RealIteratorTestCase
{
    /**
     * @dataProvider getAcceptData
     */
    public function testAccept($directories, $expected)
    {
        $inner = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(sys_get_temp_dir().'/symfony2_finder', \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);

        $iterator = new ExcludeDirectoryFilterIterator($inner, $directories);

        $this->assertIterator($expected, $iterator);
    }

    public function getAcceptData()
    {
        $tmpDir = sys_get_temp_dir().'/symfony2_finder';

        return array(
            array(array('foo'), array(
                $tmpDir.'/.git',
                $tmpDir.'/test.py',
                $tmpDir.'/test.php',
                $tmpDir.'/toto'
            )),
            array(array('fo'), array(
                $tmpDir.'/.git',
                $tmpDir.'/test.py',
                $tmpDir.'/foo',
                $tmpDir.'/foo/bar.tmp',
                $tmpDir.'/test.php',
                $tmpDir.'/toto'
            )),
        );
    }
}
