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
 * @experimental
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
        private readonly string $projectRootDir
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
            if (file_exists($file)) {
                return $file;
            }
        }

        return null;
    }

    public function findLogicalPath(string $filesystemPath): ?string
    {
        foreach ($this->getDirectories() as $path => $namespace) {
            if (!str_starts_with($filesystemPath, $path)) {
                continue;
            }

            $logicalPath = substr($filesystemPath, \strlen($path));
            if ('' !== $namespace) {
                $logicalPath = $namespace.'/'.$logicalPath;
            }

            return ltrim($logicalPath, '/');
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
                if (!$file->isFile()) {
                    continue;
                }

                /** @var RecursiveDirectoryIterator $innerIterator */
                $innerIterator = $iterator->getInnerIterator();
                $logicalPath = ($namespace ? rtrim($namespace, '/').'/' : '').$innerIterator->getSubPathName();
                $paths[$logicalPath] = $file->getPathname();
            }
        }

        return $paths;
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
                if (!file_exists($path)) {
                    throw new \InvalidArgumentException(sprintf('The asset mapper directory "%s" does not exist.', $path));
                }
                $this->absolutePaths[$path] = $namespace;

                continue;
            }

            if (file_exists($this->projectRootDir.'/'.$path)) {
                $this->absolutePaths[$this->projectRootDir.'/'.$path] = $namespace;

                continue;
            }

            throw new \InvalidArgumentException(sprintf('The asset mapper directory "%s" does not exist.', $path));
        }

        return $this->absolutePaths;
    }
}
