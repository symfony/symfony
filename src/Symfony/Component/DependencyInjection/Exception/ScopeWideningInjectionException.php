<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Component\DependencyInjection\Exception;

/**
 * Thrown when a scope widening injection is detected.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ScopeWideningInjectionException extends RuntimeException
{
    private $sourceServiceId;
    private $sourceScope;
    private $destServiceId;
    private $destScope;

    public function __construct($sourceServiceId, $sourceScope, $destServiceId, $destScope)
    {
        parent::__construct(sprintf(
            'Scope Widening Injection detected: The definition "%s" references the service "%s" which belongs to a narrower scope. '
           .'Generally, it is safer to either move "%s" to scope "%s" or alternatively rely on the provider pattern by injecting the container itself, and requesting the service "%s" each time it is needed. '
           .'In rare, special cases however that might not be necessary, then you can set the reference to strict=false to get rid of this error.',
           $sourceServiceId,
           $destServiceId,
           $sourceServiceId,
           $destScope,
           $destServiceId
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
