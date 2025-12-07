<?php

namespace Symfony\Component\AccessControl\Strategy;

use Symfony\Component\AccessControl\AccessRequest;
use Symfony\Component\AccessControl\AccessDecision;
use Symfony\Component\AccessControl\DecisionVote;
use Symfony\Component\AccessControl\VoterOutcome;

/**
 * A simple “unanimous” strategy:
 *  - If at least one voter denies, the final decision is denied.
 *  - If all abstain, the final decision depends on the allowIfAllAbstainDecisions property value.
 *  - Otherwise, (i.e. at least one voter grants access) the final decision is granted.
 *
 * @experimental
 */
final readonly class UnanimousStrategy implements StrategyInterface
{
    public function getName(): string
    {
        return 'unanimous';
    }

    /**
     * @param iterable<VoterOutcome> $votes
     */
    public function evaluate(AccessRequest $accessRequest, iterable $votes): AccessDecision
    {
        $grant = 0;

        foreach ($votes as $vote) {
            if ($vote->decision === DecisionVote::ACCESS_DENIED) {
                return AccessDecision::deny($accessRequest, $votes, $vote->reason);
            }

            if ($vote->decision === DecisionVote::ACCESS_GRANTED) {
                ++$grant;
            }
        }

        if ($grant > 0) {
            return AccessDecision::grant($accessRequest, $votes, 'All non-abstaining voters granted access.');
        }

        return AccessDecision::abstain($accessRequest, $votes, 'All voters abstained from voting.');
    }
}
