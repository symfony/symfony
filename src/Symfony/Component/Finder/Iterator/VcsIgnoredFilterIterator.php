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
     * @var array<string, array{0: string, 1: string}|null>
     */
    private $gitignoreFilesCache = [];

    /**
     * @var array<string, bool>
     */
    private $ignoredPathsCache = [];

    public function __construct(\Iterator $iterator, string $baseDir)
    {
        $this->baseDir = $this->normalizePath($baseDir);

        parent::__construct($iterator);
    }

    public function accept(): bool
    {
        $file = $this->current();

        $fileRealPath = $this->normalizePath($file->getRealPath());

        return !$this->isIgnored($fileRealPath);
    }

    private function isIgnored(string $fileRealPath): bool
    {
        if (is_dir($fileRealPath) && !str_ends_with($fileRealPath, '/')) {
            $fileRealPath .= '/';
        }

        if (isset($this->ignoredPathsCache[$fileRealPath])) {
            return $this->ignoredPathsCache[$fileRealPath];
        }

        $ignored = false;

        foreach ($this->parentsDirectoryDownward($fileRealPath) as $parentDirectory) {
            if ($this->isIgnored($parentDirectory)) {
                $ignored = true;

                // rules in ignored directories are ignored, no need to check further.
                break;
            }

            $fileRelativePath = substr($fileRealPath, \strlen($parentDirectory) + 1);

            if (null === $regexps = $this->readGitignoreFile("{$parentDirectory}/.gitignore")) {
                continue;
            }

            [$exclusionRegex, $inclusionRegex] = $regexps;

            if (preg_match($exclusionRegex, $fileRelativePath)) {
                $ignored = true;

                continue;
            }

            if (preg_match($inclusionRegex, $fileRelativePath)) {
                $ignored = false;
            }
        }

        return $this->ignoredPathsCache[$fileRealPath] = $ignored;
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

    /**
     * @return array{0: string, 1: string}|null
     */
    private function readGitignoreFile(string $path): ?array
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

        $gitignoreFileContent = file_get_contents($path);

        return $this->gitignoreFilesCache[$path] = [
            Gitignore::toRegex($gitignoreFileContent),
            Gitignore::toRegexMatchingNegatedPatterns($gitignoreFileContent),
        ];
    }

    private function normalizePath(string $path): string
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            return str_replace('\\', '/', $path);
        }

        return $path;
    }
}
