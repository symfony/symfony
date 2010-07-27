<?php

namespace Symfony\Components\Validator\Mapping\Loader;

/**
 * Creates mapping loaders for array of files.
 *
 * Abstract class, used by
 * @see Symfony\Components\Validator\Mapping\Loader\YamlFileLoader
 * @see Symfony\Components\Validator\Mapping\Loader\XmlFileLoader
 *
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 */
abstract class FilesLoader extends LoaderChain
{
    /**
     * Array of mapping files
     * @param array $paths
     */
    public function __construct(array $paths)
    {
        parent::__construct($this->getFileLoaders($paths));
    }

    /**
     * Array of mapping files
     * @param array $paths
     * @return array - array of metadata loaders
     */
    protected function getFileLoaders($paths)
    {
        $loaders = array();
        foreach ($paths as $path)  {
            $loaders[] = $this->getFileLoaderInstance($path);
        }
        return $loaders;
    }

    /**
     * Takes mapping file path
     * @param string $file
     * @return LoaderInterface
     */
    abstract protected function getFileLoaderInstance($file);
}