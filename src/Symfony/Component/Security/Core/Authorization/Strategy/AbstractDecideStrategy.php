<?php

namespace Symfony\Component\Security\Core\Authorization\Strategy;

abstract class AbstractDecideStrategy
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
}