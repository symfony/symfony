<?php

namespace Symfony\Component\Security\Core\Authorization;

/**
 * If a Voter implements this interface, AccessDecisionManager will call
 * setAccessDecisionManager on the voter before calling vote().
 *
 * This allows Voters to get access to the AccessDecisionManager so that
 * they can check the result of other voters internally.
 */
interface AccessDecisionManagerAwareInterface
{
    /**
     * This method is called on a voter that implements this interface
     * before calling vote().
     *
     * @param AccessDecisionManagerInterface $decisionManager
     */
    public function setAccessDecisionManager(AccessDecisionManagerInterface $decisionManager);
}
