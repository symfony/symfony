<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

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