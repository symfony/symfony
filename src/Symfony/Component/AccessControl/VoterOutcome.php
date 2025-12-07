<?php

namespace Symfony\Component\AccessControl;

/**
 * @experimental
 */
final readonly class VoterOutcome
{
    public function __construct(
        public DecisionVote $decision,
        public ?string $reason = null,
        public int|float $weight = 1,
    ) {
    }

    public static function grant(?string $reason = null, int|float $weight = 1): self
    {
        return new self(DecisionVote::ACCESS_GRANTED, $reason, $weight);
    }

    public static function deny(?string $reason = null, int|float $weight = 1): self
    {
        return new self(DecisionVote::ACCESS_DENIED, $reason, $weight);
    }

    public static function abstain(?string $reason = null, int|float $weight = 1): self
    {
        return new self(DecisionVote::ACCESS_ABSTAIN, $reason, $weight);
    }
}
