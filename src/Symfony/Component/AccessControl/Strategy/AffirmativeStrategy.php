<?php

namespace Symfony\Component\AccessControl\Strategy;

use Symfony\Component\AccessControl\AccessRequest;
use Symfony\Component\AccessControl\AccessDecision;
use Symfony\Component\AccessControl\DecisionVote;
use Symfony\Component\AccessControl\VoterOutcome;

/**
 * A simple “affirmative” strategy:
 *  - If at least one voter grants access, the final decision is granted.
 *  - If all abstain, the final decision depends on the allowIfAllAbstainOrTie property value.
 *  - Otherwise, (i.e. at least one voter denies access) the final decision is denied.
 *
 * @experimental
 */
final readonly class AffirmativeStrategy implements StrategyInterface
{
    public function getName(): string
    {
        return 'affirmative';
    }

    /**
     * @param iterable<VoterOutcome> $votes
     */
    public function evaluate(AccessRequest $accessRequest, iterable $votes): AccessDecision
    {
        $deny = 0;

        foreach ($votes as $vote) {
            if ($vote->decision === DecisionVote::ACCESS_GRANTED) {
                return AccessDecision::grant($accessRequest, $votes, $vote->reason);
            }

            if ($vote->decision === DecisionVote::ACCESS_DENIED) {
                ++$deny;
            }
        }

        if ($deny > 0) {
            return AccessDecision::deny($accessRequest, $votes, 'At least one voter denied access.');
        }

        return AccessDecision::abstain($accessRequest, $votes, 'All voters abstained from voting.');
    }
}
