<?php

namespace Symfony\Component\Security\Core\Authorization\Strategy;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionStrategyInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Grants access if only grant (or abstain) votes were received.
 *
 * If all voters abstained from voting, the decision will be based on the
 * allowIfAllAbstainDecisions property value (defaults to false).
 */
class DecideUnanimousStrategy extends AbstractDecideStrategy implements AccessDecisionManagerInterface
{
    private $allowIfAllAbstainDecisions;

    /**
     * DecideUnanimousStrategy constructor.
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
        $grant = 0;
        foreach ($attributes as $attribute) {
            foreach ($this->voters as $voter) {
                $result = $voter->vote($token, $object, array($attribute));

                switch ($result) {
                    case VoterInterface::ACCESS_GRANTED:
                        ++$grant;

                        break;

                    case VoterInterface::ACCESS_DENIED:
                        return false;

                    default:
                        break;
                }
            }
        }

        // no deny votes
        if ($grant > 0) {
            return true;
        }

        return $this->allowIfAllAbstainDecisions;
    }

}