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
            new SortableIterator(new Iterator([]), -255);
            $this->fail('__construct() throws an \InvalidArgumentException exception if the mode is not valid');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\InvalidArgumentException::class, $e, '__construct() throws an \InvalidArgumentException exception if the mode is not valid');
        }
    }

    /**
     * @dataProvider getAcceptData
     */
    public function testAccept($mode, $expected)
    {
        if (!\is_callable($mode)) {
            switch ($mode) {
                case SortableIterator::SORT_BY_ACCESSED_TIME:
                    touch(self::toAbsolute('.git'));
                    sleep(1);
                    touch(self::toAbsolute('.bar'), time());
                    break;
                case SortableIterator::SORT_BY_CHANGED_TIME:
                    sleep(1);
                    file_put_contents(self::toAbsolute('test.php'), 'foo');
                    sleep(1);
                    file_put_contents(self::toAbsolute('test.py'), 'foo');
                    break;
                case SortableIterator::SORT_BY_MODIFIED_TIME:
                    file_put_contents(self::toAbsolute('test.php'), 'foo');
                    sleep(1);
                    file_put_contents(self::toAbsolute('test.py'), 'foo');
                    break;
            }
        }

        $inner = new Iterator(self::$files);

        $iterator = new SortableIterator($inner, $mode);

        if (SortableIterator::SORT_BY_ACCESSED_TIME === $mode
            || SortableIterator::SORT_BY_CHANGED_TIME === $mode
            || SortableIterator::SORT_BY_MODIFIED_TIME === $mode
        ) {
            if ('\\' === \DIRECTORY_SEPARATOR && SortableIterator::SORT_BY_MODIFIED_TIME !== $mode) {
                $this->markTestSkipped('Sorting by atime or ctime is not supported on Windows');
            }
            $this->assertOrderedIteratorForGroups($expected, $iterator);
        } else {
            $this->assertOrderedIterator($expected, $iterator);
        }
    }

    public static function getAcceptData()
    {
        $sortByName = [
            '.bar',
            '.foo',
            '.foo/.bar',
            '.foo/bar',
            '.git',
            'Zephire.php',
            'foo',
            'foo bar',
            'foo/bar.tmp',
            'qux',
            'qux/baz_100_1.py',
            'qux/baz_1_2.py',
            'qux_0_1.php',
            'qux_1000_1.php',
            'qux_1002_0.php',
            'qux_10_2.php',
            'qux_12_0.php',
            'qux_2_0.php',
            'test.php',
            'test.py',
            'toto',
            'toto/.git',
            'zebulon.php',
        ];

        $sortByType = [
            '.foo',
            '.git',
            'foo',
            'qux',
            'toto',
            'toto/.git',
            '.bar',
            '.foo/.bar',
            '.foo/bar',
            'Zephire.php',
            'foo bar',
            'foo/bar.tmp',
            'qux/baz_100_1.py',
            'qux/baz_1_2.py',
            'qux_0_1.php',
            'qux_1000_1.php',
            'qux_1002_0.php',
            'qux_10_2.php',
            'qux_12_0.php',
            'qux_2_0.php',
            'test.php',
            'test.py',
            'zebulon.php',
        ];

        $sortByAccessedTime = [
            // For these two files the access time was set to 2005-10-15
            ['foo/bar.tmp', 'test.php'],
            // These files were created more or less at the same time
            [
                '.git',
                '.foo',
                '.foo/.bar',
                '.foo/bar',
                'test.py',
                'Zephire.php',
                'foo',
                'toto',
                'toto/.git',
                'foo bar',
                'qux',
                'qux/baz_100_1.py',
                'qux/baz_1_2.py',
                'qux_0_1.php',
                'qux_1000_1.php',
                'qux_1002_0.php',
                'qux_10_2.php',
                'qux_12_0.php',
                'qux_2_0.php',
                'zebulon.php',
            ],
            // This file was accessed after sleeping for 1 sec
            ['.bar'],
        ];

        $sortByChangedTime = [
            [
                '.git',
                '.foo',
                '.foo/.bar',
                '.foo/bar',
                '.bar',
                'Zephire.php',
                'foo',
                'foo/bar.tmp',
                'toto',
                'toto/.git',
                'foo bar',
                'qux',
                'qux/baz_100_1.py',
                'qux/baz_1_2.py',
                'qux_0_1.php',
                'qux_1000_1.php',
                'qux_1002_0.php',
                'qux_10_2.php',
                'qux_12_0.php',
                'qux_2_0.php',
                'zebulon.php',
            ],
            ['test.php'],
            ['test.py'],
        ];

        $sortByModifiedTime = [
            [
                '.git',
                '.foo',
                '.foo/.bar',
                '.foo/bar',
                '.bar',
                'Zephire.php',
                'foo',
                'foo/bar.tmp',
                'toto',
                'toto/.git',
                'foo bar',
                'qux',
                'qux/baz_100_1.py',
                'qux/baz_1_2.py',
                'qux_0_1.php',
                'qux_1000_1.php',
                'qux_1002_0.php',
                'qux_10_2.php',
                'qux_12_0.php',
                'qux_2_0.php',
                'zebulon.php',
            ],
            ['test.php'],
            ['test.py'],
        ];

        $sortByNameNatural = [
            '.bar',
            '.foo',
            '.foo/.bar',
            '.foo/bar',
            '.git',
            'Zephire.php',
            'foo',
            'foo/bar.tmp',
            'foo bar',
            'qux',
            'qux/baz_1_2.py',
            'qux/baz_100_1.py',
            'qux_0_1.php',
            'qux_2_0.php',
            'qux_10_2.php',
            'qux_12_0.php',
            'qux_1000_1.php',
            'qux_1002_0.php',
            'test.php',
            'test.py',
            'toto',
            'toto/.git',
            'zebulon.php',
        ];

        $customComparison = [
            '.bar',
            '.foo',
            '.foo/.bar',
            '.foo/bar',
            '.git',
            'Zephire.php',
            'foo',
            'foo bar',
            'foo/bar.tmp',
            'qux',
            'qux/baz_100_1.py',
            'qux/baz_1_2.py',
            'qux_0_1.php',
            'qux_1000_1.php',
            'qux_1002_0.php',
            'qux_10_2.php',
            'qux_12_0.php',
            'qux_2_0.php',
            'test.php',
            'test.py',
            'toto',
            'toto/.git',
            'zebulon.php',
        ];

        return [
            [SortableIterator::SORT_BY_NAME, self::toAbsolute($sortByName)],
            [SortableIterator::SORT_BY_TYPE, self::toAbsolute($sortByType)],
            [SortableIterator::SORT_BY_ACCESSED_TIME, self::toAbsolute($sortByAccessedTime)],
            [SortableIterator::SORT_BY_CHANGED_TIME, self::toAbsolute($sortByChangedTime)],
            [SortableIterator::SORT_BY_MODIFIED_TIME, self::toAbsolute($sortByModifiedTime)],
            [SortableIterator::SORT_BY_NAME_NATURAL, self::toAbsolute($sortByNameNatural)],
            [fn (\SplFileInfo $a, \SplFileInfo $b) => strcmp($a->getRealPath(), $b->getRealPath()), self::toAbsolute($customComparison)],
        ];
    }
}
