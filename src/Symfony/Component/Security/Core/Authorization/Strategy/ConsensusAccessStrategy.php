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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * ConsensusAccessStrategy grants access if there is consensus of granted
 * against denied responses.
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
 * @author Hugo Hamon <hugo.hamon@sensio.com>
 */
class ConsensusAccessStrategy extends AccessStrategy implements AccessStrategyInterface
{
    private $allowIfEqualGrantedDeniedDecisions;

    /**
     * Constructor.
     *
     * @param VoterInterface[] An array of VoterInterface objects.
     * @param Boolean $allowIfAllAbstainDecisions Allow access if all voters abstain decisions.
     * @param Boolean $allowIfEqualGrantedDeniedDecisions Allow access if denied decisions equals granted decisions.
     */
    public function __construct(array $voters, $allowIfAllAbstainDecisions = false, $allowIfEqualGrantedDeniedDecisions = true)
    {
        parent::__construct($voters, $allowIfAllAbstainDecisions);

        $this->allowIfEqualGrantedDeniedDecisions = (Boolean) $allowIfEqualGrantedDeniedDecisions;
    }

    /**
     * {@inheritdoc}
     */
    public function decide(TokenInterface $token, array $attributes, $object = null)
    {
        $grant = 0;
        $deny = 0;
        $abstain = 0;
        foreach ($this->voters as $voter) {
            $result = $voter->vote($token, $object, $attributes);

            switch ($result) {
                case VoterInterface::ACCESS_GRANTED:
                    ++$grant;

                    break;

                case VoterInterface::ACCESS_DENIED:
                    ++$deny;

                    break;

                default:
                    ++$abstain;

                    break;
            }
        }

        if ($grant > $deny) {
            return true;
        }

        if ($deny > $grant) {
            return false;
        }

        if ($grant == $deny && $grant != 0) {
            return $this->allowIfEqualGrantedDeniedDecisions;
        }

        return $this->allowIfAllAbstainDecisions;
    }
}