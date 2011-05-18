<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The DirectoryLoader loads all files within a directory and will pass
 * each file to the DelegatingLoader.
 *
 * @author Kai-Arne Watermann <kaiwatermann@gmail.com>
 */
class DirectoryLoader extends Loader
{
    protected $locator;

    protected $container;

    /**
     * Constructor.
     *
     * @param ContainerBuilder $container A ContainerBuilder instance
     * @param FileLocator      $locator   A FileLocator instance
     */
    public function __construct(ContainerBuilder $container, FileLocator $locator)
    {
        $this->locator   = $locator;
        $this->container = $container;
    }

    /**
     * Loads all files within the directory.
     *
     * @param mixed  $resource The resource
     * @param string $type The resource type
     */
    public function load($resource, $type = null)
    {
        $path = $this->locator->locate($resource);
        $directory = new \DirectoryIterator($path);
        
        $loader = new DelegatingLoader($this->resolver);
        
        foreach ($directory as $item) {
            // Skip "." and ".." directory items
            if ($item->isDot()) continue;
            
            if ($item->isDir()) {   
                // Subdirectory
                $loader->load($item->getPathname() . DIRECTORY_SEPARATOR);
            } elseif ($item->isFile()) {
                // File
                $loader->load($item->getPathname());
            }
        }
    }

    /**
     * Returns true if this class supports the given resource.
     *
     * @param mixed  $resource A resource
     * @param string $type     The resource type
     *
     * @return Boolean true if this class supports the given resource, false otherwise
     */
    public function supports($resource, $type = null)
    {
        // Return false if resource is not of type string
        if (!is_string($resource)) return false;
        
        // Check if last character of resource string equals to directory separator
        $lastChar = substr($resource, -1, 1);
        $isDirectory = ($lastChar == '/' || $lastChar == DIRECTORY_SEPARATOR);

        return $isDirectory;
    }
}
