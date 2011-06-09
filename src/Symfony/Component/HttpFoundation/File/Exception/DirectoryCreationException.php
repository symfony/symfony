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
 * Thrown when a directory fails to be created
 *
 * @author Victor Berchet <victor@suumit.com>
 */

class DirectoryCreationException extends FileException
{
    public function __construct($directory, $code = 0, $previous = null) 
    {
        parent::__construct(sprintf('Failing to create the directory "%s"', $directory), $code, $previous);
    }
}