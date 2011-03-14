<?php

namespace Symfony\Component\DependencyInjection\Exception;

/**
 * Thrown when a scope widening injection is detected.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ScopeWideningException extends RuntimeException
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