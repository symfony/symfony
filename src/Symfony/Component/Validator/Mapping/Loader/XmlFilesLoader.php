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

trigger_deprecation('symfony/validator', '7.4', 'XML configuration format is deprecated, use YAML or attributes instead.');

/**
 * Loads validation metadata from a list of XML files.
 *
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see FilesLoader
 * @deprecated since Symfony 7.4, use another loader instead
 */
class XmlFilesLoader extends FilesLoader
{
    public function getFileLoaderInstance(string $file): LoaderInterface
    {
        return new XmlFileLoader($file);
    }
}
