<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\File\Exception;

/**
 * Thrown when a directory was not found
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class DirectoryNotFoundException extends FileException
{
    /**
     * Constructor.
     *
     * @param string $path  The path to the file that was not found
     */
    public function __construct($path, $code = 0, \Exception $previous = null)
    {
        parent::__construct(sprintf('The directory "%s" does not exist', $path), $code, $previous);
    }
}