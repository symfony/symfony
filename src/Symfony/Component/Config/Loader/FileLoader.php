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

    private $currentResource;
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
    public function setCurrentDir($dir) //deprecate
    {
        $this->currentDir = $dir;
    }

    /**
     * Sets the current resource being loaded.
     *
     * @param string $resource
     */
    public function setCurrentResource($resource)
    {
        $this->currentResource = $this->locate($resource);
        $this->currentDir = '/' === substr($this->currentResource, -1) || is_dir($this->currentResource) ? rtrim($this->currentResource, '/') : dirname($this->currentResource);
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
     * {@inheritdoc}
     *
     * @throws FileLoaderLoadException
     * @throws FileLoaderImportCircularReferenceException
     */
    public function import($resource, $type = null, $ignoreErrors = false, $sourceResource = null)  // deprecate $sourceResource
    {
        $currentResource = $sourceResource ?: $this->currentResource;
        $currentDir = $this->currentDir;

        try {
            $loader = $this->resolve($resource, $type);

            if ($loader instanceof self && $this !== $loader) {
                $resource = $loader->getLocator()->locate($resource, $currentDir, false);
            } else {
                $resource = $this->locate($resource, $currentDir, false);
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
                $this->setCurrentResource($resource);
                $ret = $loader->load($resource, $type);
            } finally {
                unset(self::$loading[$resource]);
                $this->setCurrentResource($currentResource);
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

                throw new FileLoaderLoadException($resource, $currentResource, null, $e);
            }
        }
    }

    protected function locate($file, $currentDir = null, $first = true)
    {
        return $this->locator->locate($file, $currentDir ?: $this->currentDir, $first);
    }
}
