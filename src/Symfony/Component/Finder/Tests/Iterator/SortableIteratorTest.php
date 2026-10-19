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

use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('getAcceptData')]
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

    #[DataProvider('provideStableSortModes')]
    public function testSortingPreservesKeysAndEqualValueOrder(int $mode, bool $reverse, array $expectedKeys)
    {
        foreach ([\SplFileInfo::class, \Symfony\Component\Finder\SplFileInfo::class] as $class) {
            $files = [];
            foreach (['lower' => 'a.php', 'number10' => 'file10.txt', 'upper' => 'A.php', 'number2' => 'file2.txt', 'b' => 'b.php'] as $key => $path) {
                $files[$key] = \SplFileInfo::class === $class ? new $class(self::toAbsolute($path)) : new $class(self::toAbsolute($path), '', $path);
            }
            $iterator = new SortableIterator(new \ArrayIterator($files), $mode, $reverse);
            $sorted = iterator_to_array($iterator);
            $this->assertSame($expectedKeys, array_keys($sorted));
            foreach ($sorted as $key => $file) {
                $this->assertSame($files[$key], $file);
            }
        }
    }

    public static function provideStableSortModes(): iterable
    {
        yield 'name' => [SortableIterator::SORT_BY_NAME, false, ['upper', 'lower', 'b', 'number10', 'number2']];
        yield 'name reversed' => [SortableIterator::SORT_BY_NAME, true, ['number2', 'number10', 'b', 'lower', 'upper']];
        yield 'natural' => [SortableIterator::SORT_BY_NAME_NATURAL, false, ['upper', 'lower', 'b', 'number2', 'number10']];
        yield 'natural reversed' => [SortableIterator::SORT_BY_NAME_NATURAL, true, ['number10', 'number2', 'b', 'lower', 'upper']];
        yield 'case insensitive' => [SortableIterator::SORT_BY_NAME_CASE_INSENSITIVE, false, ['lower', 'upper', 'b', 'number10', 'number2']];
        yield 'case insensitive reversed' => [SortableIterator::SORT_BY_NAME_CASE_INSENSITIVE, true, ['number2', 'number10', 'b', 'lower', 'upper']];
        yield 'natural case insensitive' => [SortableIterator::SORT_BY_NAME_NATURAL_CASE_INSENSITIVE, false, ['lower', 'upper', 'b', 'number2', 'number10']];
        yield 'natural case insensitive reversed' => [SortableIterator::SORT_BY_NAME_NATURAL_CASE_INSENSITIVE, true, ['number10', 'number2', 'b', 'lower', 'upper']];
        yield 'extension' => [SortableIterator::SORT_BY_EXTENSION, false, ['lower', 'upper', 'b', 'number10', 'number2']];
        yield 'extension reversed' => [SortableIterator::SORT_BY_EXTENSION, true, ['number10', 'number2', 'lower', 'upper', 'b']];
    }

    public function testSortingPreservesRepeatedIteratorKeys()
    {
        $a = new \SplFileInfo(self::toAbsolute('a.php'));
        $b = new \SplFileInfo(self::toAbsolute('b.php'));
        $files = static function () use ($a, $b) {
            yield 'same' => $b;
            yield 'same' => $a;
            yield 'same' => $a;
        };
        $sorted = [];
        foreach (new SortableIterator($files(), SortableIterator::SORT_BY_NAME) as $key => $file) {
            $sorted[] = [$key, $file];
        }
        $this->assertSame([['same', $a], ['same', $a], ['same', $b]], $sorted);
    }

    #[DataProvider('provideKeyedSortModes')]
    public function testSortKeyIsReadOncePerFileAndTraversal(int $mode)
    {
        $calls = new \ArrayObject();
        $files = [];
        foreach (['c.php', 'a.txt', 'e.js', 'b.php', 'd.txt', 'f.js'] as $name) {
            $files[] = new class(self::toAbsolute($name), $calls) extends \SplFileInfo {
                public function __construct(string $path, private \ArrayObject $calls)
                {
                    parent::__construct($path);
                }

                public function getRealPath(): string|false
                {
                    $this->calls[] = $this->getFilename();

                    return parent::getRealPath();
                }

                public function getExtension(): string
                {
                    $this->calls[] = $this->getFilename();

                    return parent::getExtension();
                }
            };
        }
        $iterator = new SortableIterator(new \ArrayIterator($files), $mode);

        iterator_to_array($iterator);
        $counts = array_count_values($calls->getArrayCopy());
        ksort($counts);
        $this->assertSame(['a.txt' => 1, 'b.php' => 1, 'c.php' => 1, 'd.txt' => 1, 'e.js' => 1, 'f.js' => 1], $counts);

        iterator_to_array($iterator);
        $counts = array_count_values($calls->getArrayCopy());
        ksort($counts);
        $this->assertSame(['a.txt' => 2, 'b.php' => 2, 'c.php' => 2, 'd.txt' => 2, 'e.js' => 2, 'f.js' => 2], $counts);
    }

    public static function provideKeyedSortModes(): iterable
    {
        yield 'name' => [SortableIterator::SORT_BY_NAME];
        yield 'natural' => [SortableIterator::SORT_BY_NAME_NATURAL];
        yield 'case insensitive' => [SortableIterator::SORT_BY_NAME_CASE_INSENSITIVE];
        yield 'natural case insensitive' => [SortableIterator::SORT_BY_NAME_NATURAL_CASE_INSENSITIVE];
        yield 'type' => [SortableIterator::SORT_BY_TYPE];
        yield 'extension' => [SortableIterator::SORT_BY_EXTENSION];
    }

    public function testRepeatedIterationReadsCurrentSymlinkTarget()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('symlinks are not supported on Windows');
        }

        $a = self::toAbsolute('sort-a');
        $b = self::toAbsolute('sort-b');
        $c = self::toAbsolute('sort-c');
        $link = self::toAbsolute('sort-link');
        touch($a);
        touch($b);
        touch($c);
        symlink($a, $link);
        $files = ['link' => new \SplFileInfo($link), 'middle' => new \SplFileInfo($b)];
        $iterator = new SortableIterator(new \ArrayIterator($files), SortableIterator::SORT_BY_NAME);
        $this->assertSame(['link', 'middle'], array_keys(iterator_to_array($iterator)));

        unlink($link);
        symlink($c, $link);
        clearstatcache(true);
        $this->assertSame(['middle', 'link'], array_keys(iterator_to_array($iterator)));
    }

    public function testSortingObservesSymlinkChangedByInputIterator()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('symlinks are not supported on Windows');
        }

        $a = self::toAbsolute('sort-iterator-a');
        $b = self::toAbsolute('sort-iterator-b');
        $c = self::toAbsolute('sort-iterator-c');
        $link = self::toAbsolute('sort-iterator-link');
        touch($a);
        touch($b);
        touch($c);
        symlink($a, $link);
        $files = static function () use ($link, $b, $c) {
            yield 'link' => new \SplFileInfo($link);
            unlink($link);
            symlink($c, $link);
            clearstatcache(true);
            yield 'middle' => new \SplFileInfo($b);
        };
        $iterator = new SortableIterator($files(), SortableIterator::SORT_BY_NAME);
        $this->assertSame(['middle', 'link'], array_keys(iterator_to_array($iterator)));
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
            'toto/foo',
            'zebulon.php',
        ];

        $sortByType = [
            '.foo',
            '.git',
            'foo',
            'qux',
            'toto',
            'toto/.git',
            'toto/foo',
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
                'toto/foo',
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
                'toto/foo',
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
                'toto/foo',
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
            'toto/foo',
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
            'toto/foo',
            'zebulon.php',
        ];

        return [
            [SortableIterator::SORT_BY_NAME, self::toAbsolute($sortByName)],
            [SortableIterator::SORT_BY_TYPE, self::toAbsolute($sortByType)],
            [SortableIterator::SORT_BY_ACCESSED_TIME, self::toAbsolute($sortByAccessedTime)],
            [SortableIterator::SORT_BY_CHANGED_TIME, self::toAbsolute($sortByChangedTime)],
            [SortableIterator::SORT_BY_MODIFIED_TIME, self::toAbsolute($sortByModifiedTime)],
            [SortableIterator::SORT_BY_NAME_NATURAL, self::toAbsolute($sortByNameNatural)],
            [static fn (\SplFileInfo $a, \SplFileInfo $b) => strcmp($a->getRealPath(), $b->getRealPath()), self::toAbsolute($customComparison)],
        ];
    }
}
