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
 * Grants access if there is consensus of granted against denied responses.
 *
 * Consensus means majority-rule (ignoring abstains) rather than unanimous
 * agreement (ignoring abstains). If you require unanimity, see
 * UnanimousBased.
 *
 * If there were an equal number of grant and deny votes, the decision will
 * be based on the allowIfEqualGrantedDeniedDecisions property value
 * (defaults to true).
 *
 * If all voters abstained from voting, the decision will be based on the
 * allowIfAllAbstainDecisions property value (defaults to false).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexander M. Turek <me@derrabus.de>
 */
final class ConsensusStrategy implements AccessDecisionStrategyInterface, \Stringable
{
    public function __construct(
        private bool $allowIfAllAbstainDecisions = false,
        private bool $allowIfEqualGrantedDeniedDecisions = true,
    ) {
    }

    public function decide(\Traversable $results): bool
    {
        return $this->getDecision(new \ArrayIterator(array_map(fn ($vote) => new Vote($vote), iterator_to_array($results))))->isGranted();
    }

    public function getDecision(\Traversable $votes): AccessDecision
    {
        $currentVotes = [];
        $grant = 0;
        $deny = 0;

        /** @var VoteInterface $vote */
        foreach ($votes as $vote) {
            $currentVotes[] = $vote;
            if ($vote->isGranted()) {
                ++$grant;
            } elseif ($vote->isDenied()) {
                ++$deny;
            }
        }

        if ($grant > $deny) {
            return new AccessDecision(VoterInterface::ACCESS_GRANTED, $currentVotes);
        }

        if ($deny > $grant) {
            return new AccessDecision(VoterInterface::ACCESS_DENIED, $currentVotes);
        }

        if ($grant > 0) {
            return new AccessDecision($this->allowIfEqualGrantedDeniedDecisions ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED, $currentVotes);
        }

        return new AccessDecision($this->allowIfAllAbstainDecisions ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED, $currentVotes);
    }

    public function __toString(): string
    {
        return 'consensus';
    }
}
