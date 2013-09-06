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
 * Loads multiple xml mapping files
 *
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 *
 * @see    Symfony\Component\Validator\Mapping\Loader\FilesLoader
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
