<?php

namespace Symfony\Component\Validator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Validator\Mapping\ClassMetadata;

class GroupChain
{
    protected $groups = array();
    protected $groupSequences = array();

    public function addGroup($group)
    {
        $this->groups[$group] = $group;
    }

    public function addGroupSequence(array $groups)
    {
        if (count($groups) == 0) {
            throw new \InvalidArgumentException('A group sequence must contain at least one group');
        }

        if (!in_array($groups, $this->groupSequences, true)) {
            $this->groupSequences[] = $groups;
        }
    }

    public function getGroups()
    {
        return $this->groups;
    }

    public function getGroupSequences()
    {
        return $this->groupSequences;
    }
}