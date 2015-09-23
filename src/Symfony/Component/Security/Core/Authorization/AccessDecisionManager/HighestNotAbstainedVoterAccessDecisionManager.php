<?php

namespace Symfony\Component\Security\Core\Authorization\AccessDecisionManager;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Grants access depending on the first not abstained voter decission.
 *
 * If all voters abstain from voting, the decission will be base on the allowIfAllAbstainDecisionsProperty
 */
class HighestNotAbstainedVoterAccessDecisionManager extends AbstractAccessDecisionManager
{
    private $allowIfAllAbstainDecisions;

    /**
     * DecideHighestNotAbstainedVoterStrategy constructor.
     *
     * @param $allowIfAllAbstainDecisions
     */
    public function __construct($allowIfAllAbstainDecisions)
    {
        $this->allowIfAllAbstainDecisions = $allowIfAllAbstainDecisions;
    }

    /**
     * {@inheritdoc}
     */
    public function decide(TokenInterface $token, array $attributes, $object = null)
    {
        foreach ($this->voters as $voter) {
            $result = $voter->vote($token, $object, $attributes);
            switch ($result) {
                case VoterInterface::ACCESS_GRANTED:
                    return true;
                case VoterInterface::ACCESS_DENIED:
                    return false;
                default:
                    break;
            }
        }

        return $this->allowIfAllAbstainDecisions;
    }
}
