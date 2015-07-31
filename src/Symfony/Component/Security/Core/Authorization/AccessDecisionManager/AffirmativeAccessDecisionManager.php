<?php

namespace Symfony\Component\Security\Core\Authorization\AccessDecisionManager;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Grants access if any voter returns an affirmative response.
 *
 * If all voters abstained from voting, the decision will be based on the
 * allowIfAllAbstainDecisions property value (defaults to false).
 */
class AffirmativeAccessDecisionManager extends AbstractAccessDecisionManager
{
    private $allowIfAllAbstainDecisions;

    /**
     * DecideAffirmativeStrategy constructor.
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
        $deny = 0;
        foreach ($this->voters as $voter) {
            $result = $voter->vote($token, $object, $attributes);
            switch ($result) {
                case VoterInterface::ACCESS_GRANTED:
                    return true;

                case VoterInterface::ACCESS_DENIED:
                    ++$deny;

                    break;

                default:
                    break;
            }
        }

        if ($deny > 0) {
            return false;
        }

        return $this->allowIfAllAbstainDecisions;
    }
}
