<?php

namespace Symfony\Component\DependencyInjection\Exception;

/**
 * This exception is thrown when the a scope crossing injection is detected.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ScopeCrossingInjectionException extends RuntimeException
{
    private $sourceServiceId;
    private $sourceScope;
    private $destServiceId;
    private $destScope;

    public function __construct($sourceServiceId, $sourceScope, $destServiceId, $destScope)
    {
        parent::__construct(sprintf(
            'Scope Crossing Injection detected: The definition "%s" references the service "%s" which belongs to another scope hierarchy. '
           .'This service might not be available consistently. Generally, it is safer to either move the definition "%s" to scope "%s", or '
           .'declare "%s" as a child scope of "%s". If you can be sure that the other scope is always active, you can set the reference to strict=false to get rid of this error.',
           $sourceServiceId,
           $destServiceId,
           $sourceServiceId,
           $destScope,
           $sourceScope,
           $destScope
        ));

        $this->sourceServiceId = $sourceServiceId;
        $this->sourceScope = $sourceScope;
        $this->destServiceId = $destServiceId;
        $this->destScope = $destScope;
    }

    public function getSourceServiceId()
    {
        return $this->sourceServiceId;
    }

    public function getSourceScope()
    {
        return $this->sourceScope;
    }

    public function getDestServiceId()
    {
        return $this->destServiceId;
    }

    public function getDestScope()
    {
        return $this->destScope;
    }
}