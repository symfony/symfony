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

/**
 * @extends \FilterIterator<string, \SplFileInfo>
 */
final class VcsIgnoredFilterIterator extends \FilterIterator
{
    private string $searchDir;
    private string $realSearchDir;
    private string $baseDir;
    private ?string $gitInfoExcludeFile = null;

    /**
     * @var array<string, list<array{0: string, 1: bool, 2: bool}>|null>
     */
    private array $gitignoreFilesCache = [];

    /**
     * @var array<string, bool>
     */
    private array $ignoredPathsCache = [];

    /**
     * @param \Iterator<string, \SplFileInfo> $iterator
     */
    public function __construct(\Iterator $iterator, string $baseDir)
    {
        $this->searchDir = rtrim($this->normalizePath($baseDir), '/').'/';
        $this->baseDir = $this->normalizePath(realpath($baseDir) ?: $baseDir);
        $this->realSearchDir = rtrim($this->baseDir, '/').'/';

        foreach ([$this->baseDir, ...$this->parentDirectoriesUpwards($this->baseDir)] as $directory) {
            if (@file_exists("{$directory}/.git")) {
                $this->baseDir = $directory;
                $this->gitInfoExcludeFile = $this->getGitInfoExcludePath();
                break;
            }
        }

        parent::__construct($iterator);
    }

    public function accept(): bool
    {
        $path = $this->normalizePath($this->current()->getPathname());

        // git matches a symlink by its own path, so only the search directory is resolved
        if (str_starts_with($path, $this->searchDir)) {
            $path = $this->realSearchDir.substr($path, \strlen($this->searchDir));
        }

        return !$this->isIgnored($path);
    }

    private function isIgnored(string $path): bool
    {
        if (isset($this->ignoredPathsCache[$path])) {
            return $this->ignoredPathsCache[$path];
        }

        $ignored = false;
        $parentDirectories = $this->parentDirectoriesDownwards($path);

        if ($parentDirectories && $this->baseDir !== $parentDirectory = $parentDirectories[\count($parentDirectories) - 1]) {
            // paths inside an ignored directory are ignored and cannot be re-included
            $ignored = $this->isIgnored($parentDirectory);
        }

        if (!$ignored) {
            $isDir = !is_link($path) && is_dir($path);
            $gitignoreFiles = [];

            foreach (array_reverse($parentDirectories) as $parentDirectory) {
                $gitignoreFiles["{$parentDirectory}/.gitignore"] = $parentDirectory;
            }

            if (null !== $this->gitInfoExcludeFile && $this->baseDir === ($parentDirectories[0] ?? null)) {
                $gitignoreFiles[$this->gitInfoExcludeFile] = $this->baseDir;
            }

            // the last matching rule of the deepest .gitignore file decides, and the exclude file of the repository comes after the root one
            foreach ($gitignoreFiles as $gitignoreFile => $parentDirectory) {
                if (null === $rules = $this->readGitignoreFile($gitignoreFile)) {
                    continue;
                }

                $fileRelativePath = substr($path, \strlen($parentDirectory) + 1);

                foreach ($rules as [$regex, $isNegated, $isDirOnly]) {
                    if ($isDirOnly && !$isDir) {
                        continue;
                    }

                    if (preg_match($regex, $fileRelativePath, $matches) && $matches[0] === $fileRelativePath) {
                        $ignored = !$isNegated;

                        break 2;
                    }
                }
            }
        }

        return $this->ignoredPathsCache[$path] = $ignored;
    }

    /**
     * @return list<string>
     */
    private function parentDirectoriesUpwards(string $from): array
    {
        $parentDirectories = [];

        $parentDirectory = $from;

        while (true) {
            $newParentDirectory = \dirname($parentDirectory);

            // dirname('/') = '/'
            if ($newParentDirectory === $parentDirectory) {
                break;
            }

            $parentDirectories[] = $parentDirectory = $newParentDirectory;
        }

        return $parentDirectories;
    }

    private function parentDirectoriesUpTo(string $from, string $upTo): array
    {
        return array_filter(
            $this->parentDirectoriesUpwards($from),
            static fn (string $directory): bool => str_starts_with($directory, $upTo)
        );
    }

    /**
     * @return list<string>
     */
    private function parentDirectoriesDownwards(string $fileRealPath): array
    {
        return array_reverse(
            $this->parentDirectoriesUpTo($fileRealPath, $this->baseDir)
        );
    }

    private function getGitInfoExcludePath(): ?string
    {
        $gitDir = "{$this->baseDir}/.git";

        // worktrees and submodules have a .git file that points to their git directory, which can be outside of open_basedir
        if (!is_dir($gitDir)) {
            if (!preg_match('~^gitdir:\s*(.+)~i', (string) @file_get_contents($gitDir), $matches)) {
                return null;
            }

            $gitDir = $this->resolveGitPath(trim($matches[1]), $this->baseDir);

            // the git directory of a worktree points to the common directory, where the exclude file is
            if (false !== $commonDir = @file_get_contents("{$gitDir}/commondir")) {
                $gitDir = $this->resolveGitPath(trim($commonDir), $gitDir);
            }
        }

        return @is_file("{$gitDir}/info/exclude") ? "{$gitDir}/info/exclude" : null;
    }

    private function resolveGitPath(string $path, string $from): string
    {
        return preg_match('~^(?:[a-zA-Z]:)?[/\\\\]~', $path) ? $path : "{$from}/{$path}";
    }

    /**
     * Returns the rules of a .gitignore file, last one first, as [regex, isNegated, isDirOnly] tuples.
     *
     * @return list<array{0: string, 1: bool, 2: bool}>|null
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

        $rules = [];

        foreach (preg_split('~\r\n?|\n~', file_get_contents($path)) as $line) {
            // only a line starting with "#" is a comment, and only trailing spaces are stripped
            if (str_starts_with($line, '#')) {
                continue;
            }

            $line = preg_replace('~(?<!\\\\) +$~', '', $line);

            if ($isNegated = str_starts_with($line, '!')) {
                $line = substr($line, 1);
            }

            if ($isDirOnly = str_ends_with($line, '/')) {
                $line = substr($line, 0, -1);
            }

            if ('' === $line) {
                continue;
            }

            if (str_starts_with($line, '#')) {
                // the leading "!" is already stripped, so a "#" here starts a pattern, not a comment
                $line = '\\'.$line;
            }

            $rules[] = [Gitignore::toRegex($line), $isNegated, $isDirOnly];
        }

        return $this->gitignoreFilesCache[$path] = array_reverse($rules);
    }

    private function normalizePath(string $path): string
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            return str_replace('\\', '/', $path);
        }

        return $path;
    }
}
