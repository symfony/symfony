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

use Symfony\Components\Finder\Iterator\DepthRangeFilterIterator;
use Symfony\Components\Finder\Comparator\NumberComparator;

require_once __DIR__.'/RealIteratorTestCase.php';

class DepthRangeFilterIteratorTest extends RealIteratorTestCase
{
    /**
     * @dataProvider getAcceptData
     */
    public function testAccept($size, $expected)
    {
        $inner = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(sys_get_temp_dir().'/symfony2_finder', \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);

        $iterator = new DepthRangeFilterIterator($inner, $size);

        $actual = array_keys(iterator_to_array($iterator));
        sort($expected);
        sort($actual);
        $this->assertEquals($expected, $actual);
    }

    public function getAcceptData()
    {
        return array(
            array(array(new NumberComparator('< 1')), array(sys_get_temp_dir().'/symfony2_finder/.git', sys_get_temp_dir().'/symfony2_finder/test.py', sys_get_temp_dir().'/symfony2_finder/foo', sys_get_temp_dir().'/symfony2_finder/test.php', sys_get_temp_dir().'/symfony2_finder/toto')),
            array(array(new NumberComparator('<= 1')), array(sys_get_temp_dir().'/symfony2_finder/.git', sys_get_temp_dir().'/symfony2_finder/test.py', sys_get_temp_dir().'/symfony2_finder/foo', sys_get_temp_dir().'/symfony2_finder/foo/bar.tmp', sys_get_temp_dir().'/symfony2_finder/test.php', sys_get_temp_dir().'/symfony2_finder/toto')),
            array(array(new NumberComparator('> 1')), array()),
            array(array(new NumberComparator('>= 1')), array(sys_get_temp_dir().'/symfony2_finder/foo/bar.tmp')),
            array(array(new NumberComparator('1')), array(sys_get_temp_dir().'/symfony2_finder/foo/bar.tmp')),
        );
    }
}
