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
use Symfony\Component\Finder\Iterator\FileTypeFilterIterator;

class FileTypeFilterIteratorTest extends RealIteratorTestCase
{
    #[DataProvider('getAcceptData')]
    public function testAccept($mode, $expected)
    {
        $inner = new InnerTypeIterator(self::$files);

        $iterator = new FileTypeFilterIterator($inner, $mode);

        $this->assertIterator($expected, $iterator);
    }

    public static function getAcceptData()
    {
        $onlyFiles = [
            'test.py',
            'foo/bar.tmp',
            'test.php',
            '.bar',
            '.foo/.bar',
            '.foo/bar',
            'foo bar',
            'qux/baz_100_1.py',
            'zebulon.php',
            'Zephire.php',
            'qux/baz_1_2.py',
            'qux_0_1.php',
            'qux_1000_1.php',
            'qux_1002_0.php',
            'qux_10_2.php',
            'qux_12_0.php',
            'qux_2_0.php',
        ];

        $onlyDirectories = [
            '.git',
            'foo',
            'qux',
            'toto',
            'toto/foo',
            'toto/.git',
            '.foo',
        ];

        return [
            [FileTypeFilterIterator::ONLY_FILES, self::toAbsolute($onlyFiles)],
            [FileTypeFilterIterator::ONLY_DIRECTORIES, self::toAbsolute($onlyDirectories)],
        ];
    }

    public function testDanglingSymlinkIsNeitherAFileNorADirectory()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('symlinks are not supported on Windows');
        }

        $tmpDir = realpath(sys_get_temp_dir()).'/symfony_finder_dangling_symlink';
        mkdir($tmpDir);
        touch($tmpDir.'/file.txt');
        mkdir($tmpDir.'/dir');
        symlink($tmpDir.'/missing', $tmpDir.'/dangling');

        try {
            $inner = new InnerTypeIterator([$tmpDir.'/file.txt', $tmpDir.'/dir', $tmpDir.'/dangling']);

            $this->assertIterator([$tmpDir.'/file.txt'], new FileTypeFilterIterator($inner, FileTypeFilterIterator::ONLY_FILES));
            $this->assertIterator([$tmpDir.'/dir'], new FileTypeFilterIterator($inner, FileTypeFilterIterator::ONLY_DIRECTORIES));
        } finally {
            unlink($tmpDir.'/dangling');
            unlink($tmpDir.'/file.txt');
            rmdir($tmpDir.'/dir');
            rmdir($tmpDir);
        }
    }
}

class InnerTypeIterator extends \ArrayIterator
{
    public function current(): \SplFileInfo
    {
        return new \SplFileInfo(parent::current());
    }

    public function isFile(): bool
    {
        return $this->current()->isFile();
    }

    public function isDir(): bool
    {
        return $this->current()->isDir();
    }
}
