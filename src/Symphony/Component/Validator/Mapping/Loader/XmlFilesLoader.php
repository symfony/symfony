<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Mapping\Loader;

/**
 * Loads validation metadata from a list of XML files.
 *
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see FilesLoader
 */
class XmlFilesLoader extends FilesLoader
{
    /**
     * {@inheritdoc}
     */
    public function getFileLoaderInstance($file)
    {
        return new XmlFileLoader($file);
    }
}
