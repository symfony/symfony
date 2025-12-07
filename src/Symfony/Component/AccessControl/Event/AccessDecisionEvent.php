<?php

namespace Symfony\Component\AccessControl\Event;

use Symfony\Component\AccessControl\AccessRequest;
use Symfony\Component\AccessControl\AccessDecision;
use Symfony\Component\AccessControl\VoterOutcome;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @experimental
 */
final class AccessDecisionEvent extends Event
{
    public function __construct(
        public readonly AccessRequest  $accessRequest,
        public readonly AccessDecision $accessDecision
    ) {
    }
}
