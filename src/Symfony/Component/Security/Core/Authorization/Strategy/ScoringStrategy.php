<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization\Strategy;

use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoteInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Grants access if the sum of vote results is greater than 0.
 *
 * If all voters abstained from voting, the decision will be based on the
 * allowIfAllAbstainDecisions property value (defaults to false).
 *
 * @author Roman JOLY <eltharin18@outlook.fr>
 */
final class ScoringStrategy implements AccessDecisionStrategyInterface, \Stringable
{
    public function __construct(
        private bool $allowIfAllAbstainDecisions = false,
    ) {
    }

    public function decide(\Traversable $results): bool
    {
        return $this->getDecision(new \ArrayIterator(array_map(fn ($vote) => new Vote($vote), iterator_to_array($results))))->isGranted();
    }

    public function getDecision(\Traversable $votes): AccessDecision
    {
        $currentVotes = [];
        $score = 0;

        /** @var VoteInterface $vote */
        foreach ($votes as $vote) {
            $currentVotes[] = $vote;
            $score += $vote->getAccess();
        }

        if ($score > 0) {
            return new AccessDecision(VoterInterface::ACCESS_GRANTED, $currentVotes, 'score = '.$score);
        }

        if ($score < 0) {
            return new AccessDecision(VoterInterface::ACCESS_DENIED, $currentVotes, 'score = '.$score);
        }

        return new AccessDecision($this->allowIfAllAbstainDecisions ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED, $currentVotes);
    }

    public function __toString(): string
    {
        return 'scoring';
    }
}
