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
        $inner = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->getAbsolutePath(''), \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);

        $iterator = new DepthRangeFilterIterator($inner, $minDepth, $maxDepth);

        $actual = array_keys(iterator_to_array($iterator));
        sort($expected);
        sort($actual);
        $this->assertEquals($expected, $actual);
    }

    public function getAcceptData()
    {
        return array(
            array(0, 0, array($this->getAbsolutePath('/.git'), $this->getAbsolutePath('/test.py'), $this->getAbsolutePath('/foo'), $this->getAbsolutePath('/test.php'), $this->getAbsolutePath('/toto'), $this->getAbsolutePath('/.foo'), $this->getAbsolutePath('/.bar'), $this->getAbsolutePath('/foo bar'))),
            array(0, 1, array($this->getAbsolutePath('/.git'), $this->getAbsolutePath('/test.py'), $this->getAbsolutePath('/foo'), $this->getAbsolutePath('/foo/bar.tmp'), $this->getAbsolutePath('/test.php'), $this->getAbsolutePath('/toto'), $this->getAbsolutePath('/.foo'), $this->getAbsolutePath('/.foo/.bar'), $this->getAbsolutePath('/.bar'), $this->getAbsolutePath('/foo bar'), $this->getAbsolutePath('/.foo/bar'))),
            array(2, INF, array()),
            array(1, INF, array($this->getAbsolutePath('/foo/bar.tmp'), $this->getAbsolutePath('/.foo/.bar'), $this->getAbsolutePath('/.foo/bar'))),
            array(1, 1, array($this->getAbsolutePath('/foo/bar.tmp'), $this->getAbsolutePath('/.foo/.bar'), $this->getAbsolutePath('/.foo/bar'))),
        );
    }

    protected function getAbsolutePath($path)
    {
        return sys_get_temp_dir().'/symfony2_finder'.str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
