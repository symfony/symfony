<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware\Configuration;

use Symfony\Component\Messenger\EnvelopeItemInterface;
use Symfony\Component\Validator\Constraints\GroupSequence;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 *
 * @experimental in 4.1
 */
final class ValidationConfiguration implements EnvelopeItemInterface
{
    private $groups;

    /**
     * @param string[]|GroupSequence $groups
     */
    public function __construct($groups)
    {
        $this->groups = $groups;
    }

    public function getGroups()
    {
        return $this->groups;
    }

    public function serialize()
    {
        $isGroupSequence = $this->groups instanceof GroupSequence;

        return serialize(array(
            'groups' => $isGroupSequence ? $this->groups->groups : $this->groups,
            'is_group_sequence' => $isGroupSequence,
        ));
    }

    public function unserialize($serialized)
    {
        list(
            'groups' => $groups,
            'is_group_sequence' => $isGroupSequence
        ) = unserialize($serialized, array('allowed_classes' => false));

        $this->__construct($isGroupSequence ? new GroupSequence($groups) : $groups);
    }
}
