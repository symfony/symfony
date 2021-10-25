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
    /**
     * @var string
     */
    private $tmpDir;

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
    public function testAccept(array $gitIgnoreFiles, array $otherFileNames, array $expectedResult)
    {
        foreach ($gitIgnoreFiles as $path => $content) {
            $this->createFile("{$this->tmpDir}/{$path}", $content);
        }

        $otherFileNames = $this->toAbsolute($otherFileNames);
        foreach ($otherFileNames as $path) {
            $this->createFile($path);
        }

        $inner = new InnerNameIterator($otherFileNames);

        $iterator = new VcsIgnoredFilterIterator($inner, $this->tmpDir);

        $this->assertIterator($this->toAbsolute($expectedResult), $iterator);
    }

    public function getAcceptData(): iterable
    {
        yield 'simple file' => [
            [
                '.gitignore' => 'a.txt',
            ],
            [
                'a.txt',
                'b.txt',
                'dir/a.txt',
            ],
            [
                'b.txt',
            ],
        ];

        yield 'simple file at root' => [
            [
                '.gitignore' => '/a.txt',
            ],
            [
                'a.txt',
                'b.txt',
                'dir/a.txt',
            ],
            [
                'b.txt',
                'dir/a.txt',
            ],
        ];

        yield 'directy' => [
            [
                '.gitignore' => 'dir/',
            ],
            [
                'a.txt',
                'dir/a.txt',
                'dir/b.txt',
            ],
            [
                'a.txt',
            ],
        ];

        yield 'directy matching a file' => [
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

        yield 'directy at root' => [
            [
                '.gitignore' => '/dir/',
            ],
            [
                'dir/a.txt',
                'other/dir/b.txt',
            ],
            [
                'other/dir/b.txt',
            ],
        ];

        yield 'simple file in nested .gitignore' => [
            [
                'nested/.gitignore' => 'a.txt',
            ],
            [
                'a.txt',
                'nested/a.txt',
                'nested/nested/a.txt',
            ],
            [
                'a.txt',
            ],
        ];

        yield 'simple file at root of nested .gitignore' => [
            [
                'nested/.gitignore' => '/a.txt',
            ],
            [
                'a.txt',
                'nested/a.txt',
                'nested/nested/a.txt',
            ],
            [
                'a.txt',
                'nested/nested/a.txt',
            ],
        ];

        yield 'directy in nested .gitignore' => [
            [
                'nested/.gitignore' => 'dir/',
            ],
            [
                'a.txt',
                'dir/a.txt',
                'nested/dir/a.txt',
                'nested/nested/dir/a.txt',
            ],
            [
                'a.txt',
                'dir/a.txt',
            ],
        ];

        yield 'directy matching a file in nested .gitignore' => [
            [
                'nested/.gitignore' => 'dir.txt/',
            ],
            [
                'dir.txt',
                'nested/dir.txt',
            ],
            [
                'dir.txt',
                'nested/dir.txt',
            ],
        ];

        yield 'directy at root of nested .gitignore' => [
            [
                'nested/.gitignore' => '/dir/',
            ],
            [
                'a.txt',
                'dir/a.txt',
                'nested/dir/a.txt',
                'nested/nested/dir/a.txt',
            ],
            [
                'a.txt',
                'dir/a.txt',
                'nested/nested/dir/a.txt',
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

    private function createFile(string $path, string $content = null): void
    {
        $dir = \dirname($path);
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        if (null !== $content) {
            file_put_contents($path, $content);
        } else {
            touch($path);
        }
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
