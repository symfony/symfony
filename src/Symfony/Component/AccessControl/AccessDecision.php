<?php

namespace Symfony\Component\AccessControl;

/**
 * @experimental
 */
readonly class AccessDecision
{
    /**
     * @param iterable<VoterOutcome> $votes
     */
    public function __construct(
        public AccessRequest $accessRequest,
        public DecisionVote  $decision,
        public iterable      $votes,
        public ?string       $reason = null,
    ) {
    }

    /**
     * @param iterable<VoterOutcome> $votes
     */
    public static function grant(AccessRequest $accessRequest, iterable $votes, ?string $reason = null): self
    {
        return new self($accessRequest, DecisionVote::ACCESS_GRANTED, $votes, $reason);
    }

    /**
     * @param iterable<VoterOutcome> $votes
     */
    public static function deny(AccessRequest $accessRequest, iterable $votes, ?string $reason = null): self
    {
        return new self($accessRequest, DecisionVote::ACCESS_DENIED, $votes, $reason);
    }

    public static function abstain(AccessRequest $accessRequest, iterable $votes, ?string $reason = null): self
    {
        return new self($accessRequest, DecisionVote::ACCESS_ABSTAIN, $votes, $reason);
    }
}
