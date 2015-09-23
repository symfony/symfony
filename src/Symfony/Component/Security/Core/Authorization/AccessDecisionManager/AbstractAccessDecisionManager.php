<?php

namespace Symfony\Component\Security\Core\Authorization\AccessDecisionManager;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;

abstract class AbstractAccessDecisionManager implements AccessDecisionManagerInterface
{
    protected $voters;

    /**
     * {@inheritdoc}
     */
    public function setVoters(array $voters)
    {
        $this->voters = $voters;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsAttribute($attribute)
    {
        foreach ($this->voters as $voter) {
            if ($voter->supportsAttribute($attribute)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass($class)
    {
        foreach ($this->voters as $voter) {
            if ($voter->supportsClass($class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    abstract public function decide(TokenInterface $token, array $attributes, $object = null);
}
