<?php

namespace Symfony\Component\AccessControl\Event;

use Symfony\Component\AccessControl\AccessRequest;
use Symfony\Component\AccessControl\VoterInterface;
use Symfony\Component\AccessControl\VoterOutcome;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @experimental
 */
final class VoteEvent extends Event
{
    public function __construct(
        public readonly VoterInterface $voter,
        public readonly AccessRequest  $accessRequest,
        public readonly VoterOutcome   $voterOutcome
    ) {
    }
}
