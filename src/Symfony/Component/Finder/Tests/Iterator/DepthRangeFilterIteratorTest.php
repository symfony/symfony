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

use Symfony\Component\Finder\Iterator\DepthRangeFilterIterator;

class DepthRangeFilterIteratorTest extends RealIteratorTestCase
{
    /**
     * @dataProvider getAcceptData
     */
    public function testAccept($minDepth, $maxDepth, $expected)
    {
        $inner = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->toAbsolute(), \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);

        $iterator = new DepthRangeFilterIterator($inner, $minDepth, $maxDepth);

        $actual = array_keys(iterator_to_array($iterator));
        sort($expected);
        sort($actual);
        $this->assertEquals($expected, $actual);
    }

    public function getAcceptData()
    {
        $lessThan1 = [
            '.git',
            'test.py',
            'foo',
            'test.php',
            'toto',
            '.foo',
            '.bar',
            'foo bar',
            'qux',
            'qux_0_1.php',
            'qux_1000_1.php',
            'qux_1002_0.php',
            'qux_10_2.php',
            'qux_12_0.php',
            'qux_2_0.php',
        ];

        $lessThanOrEqualTo1 = [
            '.git',
            'test.py',
            'foo',
            'foo/bar.tmp',
            'test.php',
            'toto',
            'toto/.git',
            '.foo',
            '.foo/.bar',
            '.bar',
            'foo bar',
            '.foo/bar',
            'qux',
            'qux/baz_100_1.py',
            'qux/baz_1_2.py',
            'qux_0_1.php',
            'qux_1000_1.php',
            'qux_1002_0.php',
            'qux_10_2.php',
            'qux_12_0.php',
            'qux_2_0.php',
        ];

        $graterThanOrEqualTo1 = [
            'toto/.git',
            'foo/bar.tmp',
            '.foo/.bar',
            '.foo/bar',
            'qux/baz_100_1.py',
            'qux/baz_1_2.py',
        ];

        $equalTo1 = [
            'toto/.git',
            'foo/bar.tmp',
            '.foo/.bar',
            '.foo/bar',
            'qux/baz_100_1.py',
            'qux/baz_1_2.py',
        ];

        return [
            [0, 0, $this->toAbsolute($lessThan1)],
            [0, 1, $this->toAbsolute($lessThanOrEqualTo1)],
            [2, PHP_INT_MAX, []],
            [1, PHP_INT_MAX, $this->toAbsolute($graterThanOrEqualTo1)],
            [1, 1, $this->toAbsolute($equalTo1)],
        ];
    }
}
