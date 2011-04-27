<?php

namespace Symfony\Component\Process\Exception;

/**
 * This exception is thrown if the supposed executable was found, but the
 * file permissions are insufficient to execute it.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class FileNotExecutableException extends RuntimeException
{
    public function __construct($path)
    {
        parent::__construct(sprintf('The found file "%s" is not executable.', $path));
    }
}