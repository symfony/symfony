<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping\Loader;

/**
 * Creates mapping loaders for array of files.
 *
 * Abstract class, used by
 *
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 *
 * @see    Symfony\Component\Validator\Mapping\Loader\YamlFileLoader
 * @see    Symfony\Component\Validator\Mapping\Loader\XmlFileLoader
 *
 * @since v2.0.0
 */
abstract class FilesLoader extends LoaderChain
{
    /**
     * Array of mapping files.
     *
     * @param array $paths Array of file paths
     *
     * @since v2.0.0
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
     * @return LoaderInterface[] Array of metadata loaders
     *
     * @since v2.0.0
     */
    protected function getFileLoaders($paths)
    {
        $loaders = array();
        foreach ($paths as $path) {
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
     *
     * @since v2.0.0
     */
    abstract protected function getFileLoaderInstance($file);
}
