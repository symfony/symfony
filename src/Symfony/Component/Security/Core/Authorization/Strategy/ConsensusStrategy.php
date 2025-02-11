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
final class ConsensusStrategy implements AccessDecisionVoteObjectStrategyInterface, \Stringable
{
    public function __construct(
        private bool $allowIfAllAbstainDecisions = false,
        private bool $allowIfEqualGrantedDeniedDecisions = true,
    ) {
    }

    public function decide(\Traversable $results, ?AccessDecision &$accessDecision = null): bool
    {
        $grant = 0;
        $deny = 0;
        $allVotes = [];

        foreach ($results as $result) {
            $allVotes[] = $result;
            if ($result instanceof VoteInterface) {
                $result = $result->getAccess();
            }

            if (VoterInterface::ACCESS_GRANTED === $result) {
                ++$grant;
            } elseif (VoterInterface::ACCESS_DENIED === $result) {
                ++$deny;
            }
        }

        if ($grant > $deny) {
            $accessDecision = new AccessDecision(true, $allVotes);

            return $accessDecision->getAccess();
        }

        if ($deny > $grant) {
            $accessDecision = new AccessDecision(false, $allVotes);

            return $accessDecision->getAccess();
        }

        if ($grant > 0) {
            $accessDecision = new AccessDecision($this->allowIfEqualGrantedDeniedDecisions, $allVotes);

            return $accessDecision->getAccess();
        }

        $accessDecision = new AccessDecision($this->allowIfAllAbstainDecisions, $allVotes);

        return $accessDecision->getAccess();
    }

    public function __toString(): string
    {
        return 'consensus';
    }
}
