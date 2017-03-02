<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Loader;

use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Config\Exception\FileLoaderLoadException;
use Symfony\Component\Config\Exception\FileLoaderImportCircularReferenceException;
use Symfony\Component\Config\Exception\FileLocatorFileNotFoundException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Glob;

/**
 * FileLoader is the abstract class used by all built-in loaders that are file based.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class FileLoader extends Loader
{
    /**
     * @var array
     */
    protected static $loading = array();

    /**
     * @var FileLocatorInterface
     */
    protected $locator;

    private $currentDir;

    /**
     * Constructor.
     *
     * @param FileLocatorInterface $locator A FileLocatorInterface instance
     */
    public function __construct(FileLocatorInterface $locator)
    {
        $this->locator = $locator;
    }

    /**
     * Sets the current directory.
     *
     * @param string $dir
     */
    public function setCurrentDir($dir)
    {
        $this->currentDir = $dir;
    }

    /**
     * Returns the file locator used by this loader.
     *
     * @return FileLocatorInterface
     */
    public function getLocator()
    {
        return $this->locator;
    }

    /**
     * Imports a resource.
     *
     * @param mixed       $resource       A Resource
     * @param string|null $type           The resource type or null if unknown
     * @param bool        $ignoreErrors   Whether to ignore import errors or not
     * @param string|null $sourceResource The original resource importing the new resource
     *
     * @return mixed
     *
     * @throws FileLoaderLoadException
     * @throws FileLoaderImportCircularReferenceException
     */
    public function import($resource, $type = null, $ignoreErrors = false, $sourceResource = null)
    {
        $ret = array();
        $ct = 0;
        foreach ($this->glob($resource, false, $_, $ignoreErrors) as $resource => $info) {
            ++$ct;
            $ret[] = $this->doImport($resource, $type, $ignoreErrors, $sourceResource);
        }

        return $ct > 1 ? $ret : (isset($ret[0]) ? $ret[0] : null);
    }

    /**
     * @internal
     */
    protected function glob($resource, $recursive, &$prefix = null, $ignoreErrors = false)
    {
        if (strlen($resource) === $i = strcspn($resource, '*?{[')) {
            if (!$recursive) {
                $prefix = null;

                yield $resource => new \SplFileInfo($resource);

                return;
            }
            $prefix = $resource;
            $resource = '';
        } elseif (0 === $i) {
            $prefix = '.';
            $resource = '/'.$resource;
        } else {
            $prefix = dirname(substr($resource, 0, 1 + $i));
            $resource = substr($resource, strlen($prefix));
        }

        try {
            $prefix = $this->locator->locate($prefix, $this->currentDir, true);
        } catch (FileLocatorFileNotFoundException $e) {
            if (!$ignoreErrors) {
                throw $e;
            }

            return;
        }
        $prefix = realpath($prefix) ?: $prefix;

        if (false === strpos($resource, '/**/') && (defined('GLOB_BRACE') || false === strpos($resource, '{'))) {
            foreach (glob($prefix.$resource, defined('GLOB_BRACE') ? GLOB_BRACE : 0) as $path) {
                if ($recursive && is_dir($path)) {
                    $files = iterator_to_array(new \RecursiveIteratorIterator(
                        new \RecursiveCallbackFilterIterator(
                            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS),
                            function (\SplFileInfo $file) { return '.' !== $file->getBasename()[0]; }
                        ),
                        \RecursiveIteratorIterator::LEAVES_ONLY
                    ));
                    usort($files, function (\SplFileInfo $a, \SplFileInfo $b) {
                        return (string) $a > (string) $b ? 1 : -1;
                    });

                    foreach ($files as $path => $info) {
                        if ($info->isFile()) {
                            yield $path => $info;
                        }
                    }
                } elseif (is_file($path)) {
                    yield $path => new \SplFileInfo($path);
                }
            }

            return;
        }

        if (!class_exists(Finder::class)) {
            throw new \LogicException(sprintf('Extended glob pattern "%s" cannot be used as the Finder component is not installed.', $resource));
        }

        $finder = new Finder();
        $regex = Glob::toRegex($resource);
        if ($recursive) {
            $regex = substr_replace($regex, '(/|$)', -2, 1);
        }

        $prefixLen = strlen($prefix);
        foreach ($finder->followLinks()->sortByName()->in($prefix) as $path => $info) {
            if (preg_match($regex, substr($path, $prefixLen)) && $info->isFile()) {
                yield $path => $info;
            }
        }
    }

    private function doImport($resource, $type = null, $ignoreErrors = false, $sourceResource = null)
    {
        try {
            $loader = $this->resolve($resource, $type);

            if ($loader instanceof self && null !== $this->currentDir) {
                $resource = $loader->getLocator()->locate($resource, $this->currentDir, false);
            }

            $resources = is_array($resource) ? $resource : array($resource);
            for ($i = 0; $i < $resourcesCount = count($resources); ++$i) {
                if (isset(self::$loading[$resources[$i]])) {
                    if ($i == $resourcesCount - 1) {
                        throw new FileLoaderImportCircularReferenceException(array_keys(self::$loading));
                    }
                } else {
                    $resource = $resources[$i];
                    break;
                }
            }
            self::$loading[$resource] = true;

            try {
                $ret = $loader->load($resource, $type);
            } finally {
                unset(self::$loading[$resource]);
            }

            return $ret;
        } catch (FileLoaderImportCircularReferenceException $e) {
            throw $e;
        } catch (\Exception $e) {
            if (!$ignoreErrors) {
                // prevent embedded imports from nesting multiple exceptions
                if ($e instanceof FileLoaderLoadException) {
                    throw $e;
                }

                throw new FileLoaderLoadException($resource, $sourceResource, null, $e);
            }
        }
    }
}
