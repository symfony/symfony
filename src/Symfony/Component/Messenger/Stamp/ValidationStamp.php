<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

use Symfony\Component\Validator\Constraints\GroupSequence;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
final class ValidationStamp implements StampInterface
{
    private array|GroupSequence $groups;

    /**
     * @param string[]|GroupSequence $groups
     */
    public function __construct(array|GroupSequence $groups)
    {
        $this->groups = $groups;
    }

    public function getGroups(): array|GroupSequence
    {
        return $this->groups;
    }
}
