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
    private bool $allowIfAllAbstainDecisions;
    private bool $allowIfEqualGrantedDeniedDecisions;

    public function __construct(bool $allowIfAllAbstainDecisions = false, bool $allowIfEqualGrantedDeniedDecisions = true)
    {
        $this->allowIfAllAbstainDecisions = $allowIfAllAbstainDecisions;
        $this->allowIfEqualGrantedDeniedDecisions = $allowIfEqualGrantedDeniedDecisions;
    }

    public function decide(\Traversable $results): bool
    {
        $grant = 0;
        $deny = 0;
        foreach ($results as $result) {
            if (VoterInterface::ACCESS_GRANTED === $result) {
                ++$grant;
            } elseif (VoterInterface::ACCESS_DENIED === $result) {
                ++$deny;
            }
        }

        if ($grant > $deny) {
            return true;
        }

        if ($deny > $grant) {
            return false;
        }

        if ($grant > 0) {
            return $this->allowIfEqualGrantedDeniedDecisions;
        }

        return $this->allowIfAllAbstainDecisions;
    }

    public function __toString(): string
    {
        return 'consensus';
    }
}
