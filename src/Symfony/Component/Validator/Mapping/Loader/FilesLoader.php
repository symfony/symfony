<?php

namespace Symfony\Component\Validator\Mapping\Loader;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Creates mapping loaders for array of files.
 *
 * Abstract class, used by
 *
 * @see    Symfony\Component\Validator\Mapping\Loader\YamlFileLoader
 * @see    Symfony\Component\Validator\Mapping\Loader\XmlFileLoader
 *
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 */
abstract class FilesLoader extends LoaderChain
{
    /**
     * Array of mapping files.
     *
     * @param array $paths Array of file paths
     */
    public function __construct(array $paths)
    {
        parent::__construct($this->getFileLoaders($paths));
    }

    /**
     * Array of mapping files.
     *
     * @param array $paths Array of file paths
     *
     * @return array Array of metadata loaders
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
     * Takes mapping file path.
     *
     * @param string $file
     *
     * @return LoaderInterface
     */
    abstract protected function getFileLoaderInstance($file);
}