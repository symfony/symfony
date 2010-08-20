<?php

namespace Symfony\Component\Validator\Mapping\Loader;

/**
 * Loads multiple xml mapping files
 * @see Symfony\Component\Validator\Mapping\Loader\FilesLoader
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 */
class XmlFilesLoader extends FilesLoader
{
    /**
     * {@inheritDoc}
     */
    public function getFileLoaderInstance($file)
    {
        return new XmlFileLoader($file);
    }
}