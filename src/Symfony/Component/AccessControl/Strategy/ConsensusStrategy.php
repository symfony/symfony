<?php

namespace Symfony\Component\AccessControl\Strategy;

use Symfony\Component\AccessControl\AccessRequest;
use Symfony\Component\AccessControl\AccessDecision;
use Symfony\Component\AccessControl\DecisionVote;
use Symfony\Component\AccessControl\VoterOutcome;

/**
 * A majority of “GRANT” votes leads to granted; more “DENIED” votes leads to deny.
 * Abstentions are not counted.
 * If there is a tie, the decision depends on the allowIfAllAbstainOrTie property value.
 * The vote weight is taken into account.
 *
 * @experimental
 */
final readonly class ConsensusStrategy implements StrategyInterface
{
    public function getName(): string
    {
        return 'consensus';
    }

    /**
     * @param iterable<VoterOutcome> $votes
     */
    public function evaluate(AccessRequest $accessRequest, iterable $votes): AccessDecision
    {
        $grantCount = 0;
        $denyCount  = 0;

        foreach ($votes as $vote) {
            if ($vote->decision === DecisionVote::ACCESS_GRANTED) {
                $grantCount += $vote->weight;
            } elseif ($vote->decision === DecisionVote::ACCESS_DENIED) {
                $denyCount += $vote->weight;
            }
        }

        if ($denyCount > $grantCount) {
            return AccessDecision::deny($accessRequest, $votes, 'A majority of voters denied access.');
        }

        if ($grantCount > $denyCount) {
            return AccessDecision::grant($accessRequest, $votes, 'A majority of voters granted access.');
        }

        return AccessDecision::abstain($accessRequest, $votes, $grantCount === 0 ? 'All voters abstained from voting.' : 'There is a tie.');
    }
}
