<?php

namespace Symfony\Component\AccessControl\Strategy;

use Symfony\Component\AccessControl\AccessRequest;
use Symfony\Component\AccessControl\AccessDecision;
use Symfony\Component\AccessControl\VoterOutcome;

/**
 * @experimental
 */
interface StrategyInterface
{
    public function getName(): string;

    /**
     * @param iterable<VoterOutcome> $votes
     */
    public function evaluate(AccessRequest $accessRequest, iterable $votes): AccessDecision;
}
