<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Exception;

use Symfony\Component\Filesystem\Exception\ExceptionInterface;

/**
 * Exception class for when a file could not be found.
 * Implements the ExceptionInterface of the FileSystem Component
 *
 * @author Christian Gärtner <christiangaertner.film@googlemail.com>
 */
class FileNotFoundException extends \InvalidArgumentException implements ExceptionInterface
{
    /**
     * @param string     $resource       The resource that could not be imported
     * @param string     $sourceResource The original resource importing the new resource
     * @param integer    $code           The error code
     * @param \Exception $previous       A previous exception
     */
    public function __construct($file, $currentPath = null, $paths = null, $code = null, $previous = null)
    {
        if ($currentPath === null) {
            $message = sprintf('The file "%s" does not exist.', $file);
        } else {
            $message = sprintf('The file "%s" does not exist (in: %s%s).', $file, null !== $currentPath ? $currentPath.', ' : '', implode(', ', $paths));
        }

        parent::__construct($message, $code, $previous);
    }
}
