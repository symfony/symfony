<?php

namespace Symfony\Component\AccessControl;

/**
 * @experimental
 */
interface AccessControlManagerInterface
{
    public function decide(AccessRequest $accessRequest, ?string $strategy = null): AccessDecision;
}
