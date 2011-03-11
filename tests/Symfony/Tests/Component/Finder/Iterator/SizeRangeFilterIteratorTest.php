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

use Symfony\Component\Finder\Iterator\SizeRangeFilterIterator;
use Symfony\Component\Finder\Comparator\NumberComparator;

require_once __DIR__.'/RealIteratorTestCase.php';

class SizeRangeFilterIteratorTest extends RealIteratorTestCase
{
    /**
     * @dataProvider getAcceptData
     */
    public function testAccept($size, $expected)
    {
        $inner = new InnerSizeIterator(self::$files);

        $iterator = new SizeRangeFilterIterator($inner, $size);

        $this->assertIterator($expected, $iterator);
    }

    public function getAcceptData()
    {
        return array(
            array(array(new NumberComparator('< 1K'), new NumberComparator('> 0.5K')), array(sys_get_temp_dir().'/symfony2_finder/.git', sys_get_temp_dir().'/symfony2_finder/foo', sys_get_temp_dir().'/symfony2_finder/test.php', sys_get_temp_dir().'/symfony2_finder/toto')),
        );
    }
}

class InnerSizeIterator extends \ArrayIterator
{
   public function current()
    {
        return new \SplFileInfo(parent::current());
    }

    public function getFilename()
    {
        return parent::current();
    }

    public function isFile()
    {
        return $this->current()->isFile();
    }

    public function getSize()
    {
        return $this->current()->getSize();
    }
}
