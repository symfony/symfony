<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Iterator\RecursiveDirectoryIterator;

/**
 * Finds assets in the asset mapper.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @final
 */
class AssetMapperRepository
{
    private ?array $absolutePaths = null;

    /**
     * @param string[] $paths Array of assets paths: key is the path, value is the namespace
     *                        (empty string for no namespace)
     */
    public function __construct(
        private readonly array $paths,
        private readonly string $projectRootDir,
        private readonly array $excludedPathPatterns = [],
        private readonly bool $excludeDotFiles = true,
        private readonly bool $debug = true,
    ) {
    }

    /**
     * Given the logical path - styles/app.css - returns the absolute path to the file.
     */
    public function find(string $logicalPath): ?string
    {
        foreach ($this->getDirectories() as $path => $namespace) {
            $localLogicalPath = $logicalPath;
            // if this path has a namespace, only look for files in that namespace
            if ('' !== $namespace) {
                if (!str_starts_with($logicalPath, rtrim($namespace, '/').'/')) {
                    continue;
                }

                $localLogicalPath = substr($logicalPath, \strlen($namespace) + 1);
            }

            $file = rtrim($path, '/').'/'.$localLogicalPath;
            if (is_file($file) && !$this->isExcluded($file)) {
                return realpath($file);
            }
        }

        return null;
    }

    public function findLogicalPath(string $filesystemPath): ?string
    {
        if (!is_file($filesystemPath)) {
            return null;
        }

        $filesystemPath = realpath($filesystemPath);

        if ($this->isExcluded($filesystemPath)) {
            return null;
        }

        foreach ($this->getDirectories() as $path => $namespace) {
            if (!str_starts_with($filesystemPath, $path.\DIRECTORY_SEPARATOR)) {
                continue;
            }

            $logicalPath = substr($filesystemPath, \strlen($path));

            if ('' !== $namespace) {
                $logicalPath = $namespace.'/'.ltrim($logicalPath, '/\\');
            }

            return $this->normalizeLogicalPath($logicalPath);
        }

        return null;
    }

    /**
     * Returns an array of all files in the asset_mapper.
     *
     * Key is the logical path, value is the absolute path.
     *
     * @return string[]
     */
    public function all(): array
    {
        $paths = [];
        foreach ($this->getDirectories() as $path => $namespace) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if (!$file->isFile()) {
                    continue;
                }

                if ($this->isExcluded($file->getPathname())) {
                    continue;
                }

                // avoid potentially exposing PHP files
                if ('php' === $file->getExtension()) {
                    continue;
                }

                /** @var RecursiveDirectoryIterator $innerIterator */
                $innerIterator = $iterator->getInnerIterator();
                $logicalPath = ($namespace ? rtrim($namespace, '/').'/' : '').$innerIterator->getSubPathName();
                $logicalPath = $this->normalizeLogicalPath($logicalPath);
                $paths[$logicalPath] = $file->getPathname();
            }
        }

        return $paths;
    }

    /**
     * @internal
     */
    public function allDirectories(): array
    {
        return $this->getDirectories();
    }

    private function getDirectories(): array
    {
        $filesystem = new Filesystem();
        if (null !== $this->absolutePaths) {
            return $this->absolutePaths;
        }

        $this->absolutePaths = [];
        foreach ($this->paths as $path => $namespace) {
            if ($filesystem->isAbsolutePath($path)) {
                if (!file_exists($path) && $this->debug) {
                    throw new \InvalidArgumentException(\sprintf('The asset mapper directory "%s" does not exist.', $path));
                }
                $this->absolutePaths[realpath($path)] = $namespace;

                continue;
            }

            if (file_exists($this->projectRootDir.'/'.$path)) {
                $this->absolutePaths[realpath($this->projectRootDir.'/'.$path)] = $namespace;

                continue;
            }

            if ($this->debug) {
                throw new \InvalidArgumentException(\sprintf('The asset mapper directory "%s" does not exist.', $path));
            }
        }

        return $this->absolutePaths;
    }

    /**
     * Normalize slashes to / for logical paths.
     */
    private function normalizeLogicalPath(string $logicalPath): string
    {
        return ltrim(str_replace('\\', '/', $logicalPath), '/\\');
    }

    private function isExcluded(string $filesystemPath): bool
    {
        // normalize Windows slashes and remove trailing slashes
        $filesystemPath = rtrim(str_replace('\\', '/', $filesystemPath), '/');

        foreach ($this->excludedPathPatterns as $pattern) {
            if (preg_match($pattern, $filesystemPath)) {
                return true;
            }
        }

        if ($this->excludeDotFiles && str_starts_with(basename($filesystemPath), '.')) {
            return true;
        }

        return false;
    }
}
