<?php

namespace Symfony\Component\Routing\Loader;

use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Config\Resource\DirectoryResource;

class DirectoryLoader extends FileLoader
{
    private $currentDir;

    /**
     * @param mixed  $file The resource
     * @param string $type The resource type
     */
    public function load($file, $type = null)
    {
        $path = $this->locator->locate($file);

        $collection = new RouteCollection();
        $collection->addResource(new DirectoryResource($path));

        foreach (scandir($path) as $dir) {
            if ($dir[0] !== '.') {
                $this->setCurrentDir($path);

                $subType = is_dir("$path/$dir") ? 'directory' : null;
                $subCollection = $this->import("$path/$dir", $subType, false, $path);
                $collection->addCollection($subCollection);
            }
        }

        return $collection;
    }

    /**
     * Store here as well because FileLoader::currentDir is private
     */
    public function setCurrentDir($currentDir)
    {
        $this->currentDir = $currentDir;

        parent::setCurrentDir($currentDir);
    }

    /**
     * Returns true if this class supports the given resource.
     *
     * @param mixed  $resource A resource
     * @param string $type     The resource type
     *
     * @return bool true if this class supports the given resource, false otherwise
     */
    public function supports($resource, $type = null)
    {
        try {
            $path = $this->locator->locate($resource, $this->currentDir);
        } catch (\Exception $e) {
            return false;
        }

        return is_string($resource) && 'directory' === $type;
    }
}
