<?php

namespace Symfony\Component\Console\Exception;

/**
 * Wrap a PHP 7 Error into an exception.
 *
 * @internal This class is used internally by Application and should never be used in user-land
 */
final class ErrorException extends \Exception
{
    private $error;

    public function __construct(\Error $error)
    {
        $this->error = $error;
    }

    /**
     * @return \Error
     */
    public function getError()
    {
        return $this->error;
    }
}
