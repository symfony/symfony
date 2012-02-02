<?php

namespace Symfony\Tests\Component\Validator\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\GroupSequenceProviderInterface;

/**
 * @Assert\GroupSequenceProvider
 */
class GroupSequenceProviderEntity implements GroupSequenceProviderInterface
{
    public $firstName;
    public $lastName;

    protected $groups = array();

    public function setGroups($groups)
    {
        $this->groups = $groups;
    }

    public function getGroupSequence()
    {
        return $this->groups;
    }
}
