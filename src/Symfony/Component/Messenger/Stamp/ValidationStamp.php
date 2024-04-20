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
    /**
     * @param string[]|GroupSequence $groups
     */
    public function __construct(
        private array|GroupSequence $groups,
    ) {
    }

    /** @return string[]|GroupSequence */
    public function getGroups(): array|GroupSequence
    {
        return $this->groups;
    }
}
