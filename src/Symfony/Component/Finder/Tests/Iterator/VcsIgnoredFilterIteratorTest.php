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

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Iterator\VcsIgnoredFilterIterator;

class VcsIgnoredFilterIteratorTest extends IteratorTestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = realpath(sys_get_temp_dir()).\DIRECTORY_SEPARATOR.'symfony_finder_vcs_ignored';
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    /**
     * @param array<string, string> $gitIgnoreFiles
     *
     * @dataProvider getAcceptData
     */
    public function testAccept(array $gitIgnoreFiles, array $otherFileNames, array $expectedResult, string $baseDir = '')
    {
        $otherFileNames = $this->toAbsolute($otherFileNames);
        foreach ($otherFileNames as $path) {
            if (str_ends_with($path, '/')) {
                mkdir($path);
            } else {
                touch($path);
            }
        }

        foreach ($gitIgnoreFiles as $path => $content) {
            file_put_contents("{$this->tmpDir}/{$path}", $content);
        }

        $inner = new InnerNameIterator($otherFileNames);

        $baseDir = $this->tmpDir.('' !== $baseDir ? '/'.$baseDir : '');
        $iterator = new VcsIgnoredFilterIterator($inner, $baseDir);

        $this->assertIterator($this->toAbsolute($expectedResult), $iterator);
    }

    public static function getAcceptData(): iterable
    {
        yield 'simple file' => [
            [
                '.gitignore' => 'a.txt',
            ],
            [
                'a.txt',
                'b.txt',
                'dir/',
                'dir/a.txt',
            ],
            [
                'b.txt',
                'dir',
            ],
        ];

        yield 'simple file - .gitignore and in() from repository root' => [
            [
                '.gitignore' => 'a.txt',
            ],
            [
                '.git',
                'a.txt',
                'b.txt',
                'dir/',
                'dir/a.txt',
            ],
            [
                '.git',
                'b.txt',
                'dir',
            ],
        ];

        yield 'nested git repositories only consider .gitignore files of the most inner repository' => [
            [
                '.gitignore' => "nested/*\na.txt",
                'nested/.gitignore' => 'c.txt',
                'nested/dir/.gitignore' => 'f.txt',
            ],
            [
                '.git',
                'a.txt',
                'b.txt',
                'nested/',
                'nested/.git',
                'nested/c.txt',
                'nested/d.txt',
                'nested/dir/',
                'nested/dir/e.txt',
                'nested/dir/f.txt',
            ],
            [
                '.git',
                'a.txt',
                'b.txt',
                'nested',
                'nested/.git',
                'nested/d.txt',
                'nested/dir',
                'nested/dir/e.txt',
            ],
            'nested',
        ];

        yield 'simple file at root' => [
            [
                '.gitignore' => '/a.txt',
            ],
            [
                'a.txt',
                'b.txt',
                'dir/',
                'dir/a.txt',
            ],
            [
                'b.txt',
                'dir',
                'dir/a.txt',
            ],
        ];

        yield 'directory' => [
            [
                '.gitignore' => 'dir/',
            ],
            [
                'a.txt',
                'dir/',
                'dir/a.txt',
                'dir/b.txt',
            ],
            [
                'a.txt',
            ],
        ];

        yield 'directory matching a file' => [
            [
                '.gitignore' => 'dir.txt/',
            ],
            [
                'dir.txt',
            ],
            [
                'dir.txt',
            ],
        ];

        yield 'directory at root' => [
            [
                '.gitignore' => '/dir/',
            ],
            [
                'dir/',
                'dir/a.txt',
                'other/',
                'other/dir/',
                'other/dir/b.txt',
            ],
            [
                'other',
                'other/dir',
                'other/dir/b.txt',
            ],
        ];

        yield 'simple file in nested .gitignore' => [
            [
                'nested/.gitignore' => 'a.txt',
            ],
            [
                'a.txt',
                'nested/',
                'nested/a.txt',
                'nested/nested/',
                'nested/nested/a.txt',
            ],
            [
                'a.txt',
                'nested',
                'nested/nested',
            ],
        ];

        yield 'simple file at root of nested .gitignore' => [
            [
                'nested/.gitignore' => '/a.txt',
            ],
            [
                'a.txt',
                'nested/',
                'nested/a.txt',
                'nested/nested/',
                'nested/nested/a.txt',
            ],
            [
                'a.txt',
                'nested',
                'nested/nested',
                'nested/nested/a.txt',
            ],
        ];

        yield 'directory in nested .gitignore' => [
            [
                'nested/.gitignore' => 'dir/',
            ],
            [
                'a.txt',
                'dir/',
                'dir/a.txt',
                'nested/',
                'nested/dir/',
                'nested/dir/a.txt',
                'nested/nested/',
                'nested/nested/dir/',
                'nested/nested/dir/a.txt',
            ],
            [
                'a.txt',
                'dir',
                'dir/a.txt',
                'nested',
                'nested/nested',
            ],
        ];

        yield 'directory matching a file in nested .gitignore' => [
            [
                'nested/.gitignore' => 'dir.txt/',
            ],
            [
                'dir.txt',
                'nested/',
                'nested/dir.txt',
            ],
            [
                'dir.txt',
                'nested',
                'nested/dir.txt',
            ],
        ];

        yield 'directory at root of nested .gitignore' => [
            [
                'nested/.gitignore' => '/dir/',
            ],
            [
                'a.txt',
                'dir/',
                'dir/a.txt',
                'nested/',
                'nested/dir/',
                'nested/dir/a.txt',
                'nested/nested/',
                'nested/nested/dir/',
                'nested/nested/dir/a.txt',
            ],
            [
                'a.txt',
                'dir',
                'dir/a.txt',
                'nested',
                'nested/nested',
                'nested/nested/dir',
                'nested/nested/dir/a.txt',
            ],
        ];

        yield 'negated pattern in nested .gitignore' => [
            [
                '.gitignore' => '*.txt',
                'nested/.gitignore' => "!a.txt\ndir/",
            ],
            [
                'a.txt',
                'b.txt',
                'nested/',
                'nested/a.txt',
                'nested/b.txt',
                'nested/dir/',
                'nested/dir/a.txt',
                'nested/dir/b.txt',
            ],
            [
                'nested',
                'nested/a.txt',
            ],
        ];

        yield 'negated pattern in ignored nested .gitignore' => [
            [
                '.gitignore' => "*.txt\n/nested/",
                'nested/.gitignore' => "!a.txt\ndir/",
            ],
            [
                'a.txt',
                'b.txt',
                'nested/',
                'nested/a.txt',
                'nested/b.txt',
                'nested/dir/',
                'nested/dir/a.txt',
                'nested/dir/b.txt',
            ],
            [],
        ];

        yield 'directory pattern negated in a subdirectory' => [
            [
                '.gitignore' => 'c/',
                'a/.gitignore' => '!c/',
            ],
            [
                'a/',
                'a/b/',
                'a/b/c/',
                'a/b/c/d.txt',
            ],
            [
                'a',
                'a/b',
                'a/b/c',
                'a/b/c/d.txt',
            ],
        ];

        yield 'file included from subdirectory with everything excluded' => [
            [
                '.gitignore' => "/a/**\n!/a/b.txt",
            ],
            [
                'a/',
                'a/a.txt',
                'a/b.txt',
                'a/c.txt',
            ],
            [
                'a/b.txt',
            ],
        ];
    }

    public function testAcceptAtRootDirectory()
    {
        $inner = new InnerNameIterator([__FILE__]);

        $iterator = new VcsIgnoredFilterIterator($inner, '/');

        $this->assertIterator([__FILE__], $iterator);
    }

    private function toAbsolute(array $files): array
    {
        foreach ($files as &$path) {
            $path = "{$this->tmpDir}/{$path}";
        }

        return $files;
    }

    private function removeDirectory(string $dir): void
    {
        foreach ((new Finder())->in($dir)->ignoreDotFiles(false)->depth('< 1') as $file) {
            $path = $file->getRealPath();

            if ($file->isDir()) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
