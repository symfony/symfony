<?php

namespace Symfony\Component\Security\Core\Authorization\Strategy;

abstract class AbstractDecideStrategy
{
    protected $voters;

    protected $allowIfAllAbstainDecisions;

    protected $allowIfEqualGrantedDeniedDecisions;

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
    public function setAllowIfAllAbstainDecisions($allowIfAllAbstainDecisions)
    {
        $this->allowIfAllAbstainDecisions = $allowIfAllAbstainDecisions;
    }

    /**
     * {@inheritdoc}
     */
    public function setAllowIfEqualGrantedDeniedDecisions($allowIfEqualGrantedDeniedDecisions)
    {
        $this->allowIfEqualGrantedDeniedDecisions = $allowIfEqualGrantedDeniedDecisions;
    }
}