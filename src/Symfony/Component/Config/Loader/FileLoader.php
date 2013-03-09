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
    protected static $loading = array();

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

    public function setCurrentDir($dir)
    {
        $this->currentDir = $dir;
    }

    public function getLocator()
    {
        return $this->locator;
    }

    /**
     * Imports a resource.
     *
     * @param mixed   $resource       A Resource
     * @param string  $type           The resource type
     * @param Boolean $ignoreErrors   Whether to ignore import errors or not
     * @param string  $sourceResource The original resource importing the new resource
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

            if ($loader instanceof FileLoader && null !== $this->currentDir) {
                $resource = $this->locator->locate($resource, $this->currentDir);
            }

            if (isset(self::$loading[$resource])) {
                throw new FileLoaderImportCircularReferenceException(array_keys(self::$loading));
            }
            self::$loading[$resource] = true;

            $ret = $loader->load($resource, $type);

            unset(self::$loading[$resource]);

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
