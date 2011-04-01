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

/**
 * Exception class for when a resource cannot be imported.
 *
 * @author Ryan Weaver <ryan@thatsquality.com>
 */
class FileLoaderImportException extends \Exception
{
    /**
     * @param  string    $resource The resource that could not be imported
     * @param  string    $sourceResource The original resource importing the new resource
     * @param  integer   $code     The error code
     * @param  Exception $previous A previous exception
     */
    public function __construct($resource, $sourceResource, $code = null, $previous = null)
    {
        if (null === $sourceResource) {
            $message = sprintf('Cannot import resource "%s".', $this->varToString($resource));
        } else {
            $message = sprintf('Cannot import resource "%s" from "%s".', $this->varToString($resource), $this->varToString($sourceResource));
        }

        parent::__construct($message, $code, $previous);
    }

    private function varToString($var)
    {
        if (is_object($var)) {
            return sprintf('[object](%s)', get_class($var));
        }

        if (is_array($var)) {
            $a = array();
            foreach ($var as $k => $v) {
                $a[] = sprintf('%s => %s', $k, $this->varToString($v));
            }

            return sprintf("[array](%s)", implode(', ', $a));
        }

        if (is_resource($var)) {
            return '[resource]';
        }

        if (null === $var) {
            return 'null';
        }

        return str_replace("\n", '', var_export((string) $var, true));
    }
}