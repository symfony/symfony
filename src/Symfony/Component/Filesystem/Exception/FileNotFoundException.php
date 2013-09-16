<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Filesystem\Exception;

/**
 * Exception class thrown when a file couldn't be found
 *
 * @author Christian Gärtner <christiangaertner.film@googlemail.com>
 */
class FileNotFoundException extends IOException
{
    public function __construct($path, $message = null, $code = 0, \Exception $previous = null)
    {
        if ($message === null) {
            $message = sprintf('File "%s" could not be found', $path);
        }

        $this->setPath($path);

        parent::__construct($message, $code, $previous);
    }
}
