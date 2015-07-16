<?php

namespace Symfony\Component\Security\Core\Authorization\Strategy;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
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
 */
class DecideConsensusStrategy extends AbstractDecideStrategy implements AccessDecisionManagerInterface
{
    private $allowIfEqualGrantedDeniedDecisions;

    private $allowIfAllAbstainDecisions;

    /**
     * DecideConsensusStrategy constructor.
     *
     * @param $allowIfEqualGrantedDeniedDecisions
     * @param $allowIfAllAbstainDecisions
     */
    public function __construct($allowIfEqualGrantedDeniedDecisions, $allowIfAllAbstainDecisions)
    {
        $this->allowIfEqualGrantedDeniedDecisions = $allowIfEqualGrantedDeniedDecisions;
        $this->allowIfAllAbstainDecisions = $allowIfAllAbstainDecisions;
    }

    /**
     * {@inheritdoc}
     */
    public function decide(TokenInterface $token, array $attributes, $object = null)
    {
        $grant = 0;
        $deny = 0;
        foreach ($this->voters as $voter) {
            $result = $voter->vote($token, $object, $attributes);

            switch ($result) {
                case VoterInterface::ACCESS_GRANTED:
                    ++$grant;

                    break;
                case VoterInterface::ACCESS_DENIED:
                    ++$deny;

                    break;
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

}
