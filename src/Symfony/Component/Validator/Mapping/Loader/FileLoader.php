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

use Symfony\Component\Validator\Exception\MappingException;

abstract class FileLoader extends AbstractLoader
{
    protected $file;

    /**
     * Constructor.
     *
     * @param string $file The mapping file to load
     *
     * @throws MappingException if the mapping file does not exist
     * @throws MappingException if the mapping file is not readable
     */
    public function __construct($file)
    {
        if (!is_file($file)) {
            throw new MappingException(sprintf('The mapping file %s does not exist', $file));
        }

        if (!is_readable($file)) {
            throw new MappingException(sprintf('The mapping file %s is not readable', $file));
        }

        $this->file = $file;
    }
}
