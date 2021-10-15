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
        $this->baseDir = $baseDir;

        parent::__construct($iterator);
    }

    public function accept(): bool
    {
        $file = $this->current();

        $fileRealPath = $file->getRealPath();
        if ($file->isDir()) {
            $fileRealPath .= '/';
        }

        $parentDirectory = $fileRealPath;

        do {
            $parentDirectory = \dirname($parentDirectory);
            $relativeFilePath = substr($fileRealPath, \strlen($parentDirectory) + 1);

            $regex = $this->readGitignoreFile("{$parentDirectory}/.gitignore");

            if (null !== $regex && preg_match($regex, $relativeFilePath)) {
                return false;
            }
        } while ($parentDirectory !== $this->baseDir);

        return true;
    }

    private function readGitignoreFile(string $path): ?string
    {
        if (\array_key_exists($path, $this->gitignoreFilesCache)) {
            return $this->gitignoreFilesCache[$path];
        }

        if (!file_exists($path)) {
            return null;
        }

        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException("The \"ignoreVCSIgnored\" option cannot be used by the Finder as the \"{$path}\" file is not readable.");
        }

        return $this->gitignoreFilesCache[$path] = Gitignore::toRegex(file_get_contents($path));
    }
}
