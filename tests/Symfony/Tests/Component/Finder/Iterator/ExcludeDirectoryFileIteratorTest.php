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
                $tmpDir.DIRECTORY_SEPARATOR.'.git',
                $tmpDir.DIRECTORY_SEPARATOR.'test.py',
                $tmpDir.DIRECTORY_SEPARATOR.'test.php',
                $tmpDir.DIRECTORY_SEPARATOR.'toto'
            )),
            array(array('fo'), array(
                $tmpDir.DIRECTORY_SEPARATOR.'.git',
                $tmpDir.DIRECTORY_SEPARATOR.'test.py',
                $tmpDir.DIRECTORY_SEPARATOR.'foo',
                $tmpDir.DIRECTORY_SEPARATOR.'foo'.DIRECTORY_SEPARATOR.'bar.tmp',
                $tmpDir.DIRECTORY_SEPARATOR.'test.php',
                $tmpDir.DIRECTORY_SEPARATOR.'toto'
            )),
        );
    }
}
