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
