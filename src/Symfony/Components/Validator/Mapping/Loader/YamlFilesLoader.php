<?php

namespace Symfony\Components\Validator\Mapping\Loader;

/**
 * Loads multiple yaml mapping files
 * @see Symfony\Components\Validator\Mapping\Loader\FilesLoader
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 */
class YamlFilesLoader extends FilesLoader
{
    /**
     * {@inheritDoc}
     */
    public function getFileLoaderInstance($file)
    {
        return new YamlFileLoader($file);
    }
}