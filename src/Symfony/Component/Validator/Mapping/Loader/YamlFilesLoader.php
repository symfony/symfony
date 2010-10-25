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
 * Loads multiple yaml mapping files
 *
 * @see    Symfony\Component\Validator\Mapping\Loader\FilesLoader
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