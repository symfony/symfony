<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Config\Exception;

/**
 * Exception class for when a circular reference is detected when importing resources.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class FileLoaderImportCircularReferenceException extends FileLoaderLoadException
{
    public function __construct(array $resources, int $code = null, \Exception $previous = null)
    {
        $message = sprintf('Circular reference detected in "%s" ("%s" > "%s").', $this->varToString($resources[0]), implode('" > "', $resources), $resources[0]);

        \Exception::__construct($message, $code, $previous);
    }
}
