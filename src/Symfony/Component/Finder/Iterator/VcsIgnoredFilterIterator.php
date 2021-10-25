<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Iterator;

use Symfony\Component\Finder\Gitignore;

final class VcsIgnoredFilterIterator extends \FilterIterator
{
    /**
     * @var string
     */
    private $baseDir;

    /**
     * @var array<string, string|null>
     */
    private $gitignoreFilesCache = [];

    public function __construct(\Iterator $iterator, string $baseDir)
    {
        $this->baseDir = $this->normalizePath($baseDir);

        parent::__construct($iterator);
    }

    public function accept(): bool
    {
        $file = $this->current();

        $fileRealPath = $this->normalizePath($file->getRealPath());
        if ($file->isDir() && !str_ends_with($fileRealPath, '/')) {
            $fileRealPath .= '/';
        }

        foreach ($this->parentsDirectoryDownward($fileRealPath) as $parentDirectory) {
            $fileRelativePath = substr($fileRealPath, \strlen($parentDirectory) + 1);

            $regex = $this->readGitignoreFile("{$parentDirectory}/.gitignore");

            if (null !== $regex && preg_match($regex, $fileRelativePath)) {
                return false;
            }

            if (0 !== strpos($parentDirectory, $this->baseDir)) {
                break;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function parentsDirectoryDownward(string $fileRealPath): array
    {
        $parentDirectories = [];

        $parentDirectory = $fileRealPath;

        while (true) {
            $newParentDirectory = \dirname($parentDirectory);

            // dirname('/') = '/'
            if ($newParentDirectory === $parentDirectory) {
                break;
            }

            $parentDirectory = $newParentDirectory;

            if (0 !== strpos($parentDirectory, $this->baseDir)) {
                break;
            }

            $parentDirectories[] = $parentDirectory;
        }

        return array_reverse($parentDirectories);
    }

    private function readGitignoreFile(string $path): ?string
    {
        if (\array_key_exists($path, $this->gitignoreFilesCache)) {
            return $this->gitignoreFilesCache[$path];
        }

        if (!file_exists($path)) {
            return $this->gitignoreFilesCache[$path] = null;
        }

        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException("The \"ignoreVCSIgnored\" option cannot be used by the Finder as the \"{$path}\" file is not readable.");
        }

        return $this->gitignoreFilesCache[$path] = Gitignore::toRegex(file_get_contents($path));
    }

    private function normalizePath(string $path): string
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            return str_replace('\\', '/', $path);
        }

        return $path;
    }
}
