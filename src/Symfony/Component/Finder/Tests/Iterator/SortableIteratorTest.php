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

use Symfony\Component\Finder\Iterator\SortableIterator;

class SortableIteratorTest extends RealIteratorTestCase
{
    public function testConstructor()
    {
        try {
            new SortableIterator(new Iterator(array()), 'foobar');
            $this->fail('__construct() throws an \InvalidArgumentException exception if the mode is not valid');
        } catch (\Exception $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e, '__construct() throws an \InvalidArgumentException exception if the mode is not valid');
        }
    }

    /**
     * @dataProvider getAcceptData
     */
    public function testAccept($mode, $expected)
    {
        $inner = new Iterator(self::$files);

        $iterator = new SortableIterator($inner, $mode);

        $this->assertOrderedIterator($expected, $iterator);
    }

    public function getAcceptData()
    {
        return array(
            array(SortableIterator::SORT_BY_NAME, array(sys_get_temp_dir().'/symfony2_finder/.bar', sys_get_temp_dir().'/symfony2_finder/.foo', sys_get_temp_dir().'/symfony2_finder/.foo/.bar', sys_get_temp_dir().'/symfony2_finder/.git', sys_get_temp_dir().'/symfony2_finder/foo', sys_get_temp_dir().'/symfony2_finder/foo/bar.tmp', sys_get_temp_dir().'/symfony2_finder/test.php', sys_get_temp_dir().'/symfony2_finder/test.py', sys_get_temp_dir().'/symfony2_finder/toto')),
            array(SortableIterator::SORT_BY_TYPE, array(sys_get_temp_dir().'/symfony2_finder/.foo', sys_get_temp_dir().'/symfony2_finder/.git', sys_get_temp_dir().'/symfony2_finder/foo', sys_get_temp_dir().'/symfony2_finder/toto', sys_get_temp_dir().'/symfony2_finder/.bar', sys_get_temp_dir().'/symfony2_finder/.foo/.bar', sys_get_temp_dir().'/symfony2_finder/foo/bar.tmp', sys_get_temp_dir().'/symfony2_finder/test.php', sys_get_temp_dir().'/symfony2_finder/test.py')),
            array(function (\SplFileInfo $a, \SplFileInfo $b) { return strcmp($a->getRealpath(), $b->getRealpath()); }, array(sys_get_temp_dir().'/symfony2_finder/.bar', sys_get_temp_dir().'/symfony2_finder/.foo', sys_get_temp_dir().'/symfony2_finder/.foo/.bar', sys_get_temp_dir().'/symfony2_finder/.git', sys_get_temp_dir().'/symfony2_finder/foo', sys_get_temp_dir().'/symfony2_finder/foo/bar.tmp', sys_get_temp_dir().'/symfony2_finder/test.php', sys_get_temp_dir().'/symfony2_finder/test.py', sys_get_temp_dir().'/symfony2_finder/toto')),
        );
    }
}
