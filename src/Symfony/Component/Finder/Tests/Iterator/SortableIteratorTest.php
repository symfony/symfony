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
        if (!is_callable($mode)) {
            switch ($mode) {
                case SortableIterator::SORT_BY_ACCESSED_TIME :
                    file_get_contents(self::toAbsolute('.git'));
                    sleep(1);
                    file_get_contents(self::toAbsolute('.bar'));
                    break;
                case SortableIterator::SORT_BY_CHANGED_TIME :
                    file_put_contents(self::toAbsolute('test.php'), 'foo');
                    sleep(1);
                    file_put_contents(self::toAbsolute('test.py'), 'foo');
                    break;
                case SortableIterator::SORT_BY_MODIFIED_TIME :
                    file_put_contents(self::toAbsolute('test.php'), 'foo');
                    sleep(1);
                    file_put_contents(self::toAbsolute('test.py'), 'foo');
                    break;
            }
        }

        $inner = new Iterator(self::$files);

        $iterator = new SortableIterator($inner, $mode);

        $this->assertOrderedIterator($expected, $iterator);
    }

    public function getAcceptData()
    {

        $sortByName = array(
            '.bar',
            '.foo',
            '.foo/.bar',
            '.foo/bar',
            '.git',
            'foo',
            'foo bar',
            'foo/bar.tmp',
            'test.php',
            'test.py',
            'toto',
        );

        $sortByType = array(
            '.foo',
            '.git',
            'foo',
            'toto',
            '.bar',
            '.foo/.bar',
            '.foo/bar',
            'foo bar',
            'foo/bar.tmp',
            'test.php',
            'test.py',
        );

        $customComparison = array(
            '.bar',
            '.foo',
            '.foo/.bar',
            '.foo/bar',
            '.git',
            'foo',
            'foo bar',
            'foo/bar.tmp',
            'test.php',
            'test.py',
            'toto',
        );

        $sortByAccessedTime = array(
            'foo/bar.tmp',
            'test.php',
            'toto',
            'foo bar',
            'foo',
            'test.py',
            '.foo',
            '.foo/.bar',
            '.foo/bar',
            '.git',
            '.bar'
        );

        $sortByChangedTime = array(
            'foo',
            'foo/bar.tmp',
            'toto',
            '.git',
            '.bar',
            '.foo',
            'foo bar',
            '.foo/.bar',
            '.foo/bar',
            'test.php',
            'test.py'
        );

        $sortByModifiedTime = array(
            'foo/bar.tmp',
            'foo',
            'toto',
            '.git',
            '.bar',
            '.foo',
            'foo bar',
            '.foo/.bar',
            '.foo/bar',
            'test.php',
            'test.py'
        );

        return array(
            array(SortableIterator::SORT_BY_NAME, $this->toAbsolute($sortByName)),
            array(SortableIterator::SORT_BY_TYPE, $this->toAbsolute($sortByType)),
            array(SortableIterator::SORT_BY_ACCESSED_TIME, $this->toAbsolute($sortByAccessedTime)),
            array(SortableIterator::SORT_BY_CHANGED_TIME, $this->toAbsolute($sortByChangedTime)),
            array(SortableIterator::SORT_BY_MODIFIED_TIME, $this->toAbsolute($sortByModifiedTime)),
            array(function (\SplFileInfo $a, \SplFileInfo $b) { return strcmp($a->getRealpath(), $b->getRealpath()); }, $this->toAbsolute($customComparison)),
        );
    }
}
