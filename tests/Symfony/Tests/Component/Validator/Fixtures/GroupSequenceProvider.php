<?php

namespace Symfony\Tests\Component\Validator\Fixtures;

use Symfony\Component\Validator\GroupSequenceProviderInterface;

class GroupSequenceProvider implements GroupSequenceProviderInterface
{
    protected $groups = array();

    public function setGroups($groups)
    {
        $this->groups = $groups;
    }

    public function getValidationGroups($object)
    {
        return $this->groups;
    }
}
