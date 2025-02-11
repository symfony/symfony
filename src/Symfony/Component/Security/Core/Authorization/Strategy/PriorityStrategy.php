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
use Symfony\Component\Security\Core\Authorization\Voter\VoteInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Grant or deny access depending on the first voter that does not abstain.
 * The priority of voters can be used to overrule a decision.
 *
 * If all voters abstained from voting, the decision will be based on the
 * allowIfAllAbstainDecisions property value (defaults to false).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexander M. Turek <me@derrabus.de>
 */
final class PriorityStrategy implements AccessDecisionVoteObjectStrategyInterface, \Stringable
{
    public function __construct(
        private bool $allowIfAllAbstainDecisions = false,
    ) {
    }

    public function decide(\Traversable $results, ?AccessDecision &$accessDecision = null): bool
    {
        $allVotes = [];

        foreach ($results as $result) {
            $allVotes[] = $result;
            if ($result instanceof VoteInterface) {
                $result = $result->getAccess();
            }

            if (VoterInterface::ACCESS_GRANTED === $result) {
                $accessDecision = new AccessDecision(true, $allVotes);

                return $accessDecision->getAccess();
            }

            if (VoterInterface::ACCESS_DENIED === $result) {
                $accessDecision = new AccessDecision(false, $allVotes);

                return $accessDecision->getAccess();
            }
        }

        $accessDecision = new AccessDecision($this->allowIfAllAbstainDecisions, $allVotes);

        return $accessDecision->getAccess();
    }

    public function __toString(): string
    {
        return 'priority';
    }
}
