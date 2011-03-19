<?php

namespace Symfony\Component\DependencyInjection\Exception;

/**
 * This exception is thrown when a circular reference is detected.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class CircularReferenceException extends RuntimeException
{
    private $serviceId;
    private $path;

    public function __construct($serviceId, array $path)
    {
        parent::__construct(sprintf('Circular reference detected for service "%s", path: "%s".', $serviceId, implode(' -> ', $path)));

        $this->serviceId = $serviceId;
        $this->path = $path;
    }

    public function getServiceId()
    {
        return $this->serviceId;
    }

    public function getPath()
    {
        return $this->path;
    }
}