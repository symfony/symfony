<?php

namespace Symfony\Component\DependencyInjection\Exception;

/**
 * This exception is thrown when the a scope crossing injection is detected.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ScopeCrossingException extends RuntimeException
{
    private $serviceId;

    public function setServiceId($id)
    {
        $this->serviceId = $id;
    }

    public function getServiceId()
    {
        return $this->serviceId;
    }
}