<?php

namespace Symfony\Component\Process\Exception;

/**
 * This exception is thrown when an executable is not found.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ExecutableNotFoundException extends RuntimeException
{
    private $name;

    public function __construct($name)
    {
        parent::__construct(sprintf('Could not find executable "%s".', $name));

        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }
}