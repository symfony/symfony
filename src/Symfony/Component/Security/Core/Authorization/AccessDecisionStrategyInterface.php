<?php

namespace Symfony\Component\Security\Core\Authorization;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * AccessDecisionStrategyInterface contains the strategy to make access decissions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface AccessDecisionStrategyInterface
{
    /**
     * Configures the voters.
     *
     * @param VoterInterface[] $voters An array of VoterInterface instances
     */
    public function setVoters(array $voters);

    /**
     * Set whether to grant access if all voters abstained or not.
     *
     * @param bool $allowIfAllAbstainDecisions
     */
    public function setAllowIfAllAbstainDecisions($allowIfAllAbstainDecisions);

    /**
     * Set whether to grant access if result are equals.
     *
     * @param bool $allowIfEqualGrantedDeniedDecisions
     */
    public function setAllowIfEqualGrantedDeniedDecisions($allowIfEqualGrantedDeniedDecisions);

    /**
     * Decides whether the access is possible or not.
     *
     * @param TokenInterface $token
     * @param array          $attributes
     * @param null           $object
     *
     * @return true if this decision strategy decides that the access can be made
     */
    public function decide(TokenInterface $token, array $attributes, $object = null);
}