<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

/**
 * Attribute to define a group sequence provider.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class GroupSequenceProvider
{
    /**
     * @param bool $cascadeCurrentGroup Whether the group being stepped through must be cascaded to referenced
     *                                  objects on top of "Default"; see {@see GroupSequence::$cascadeCurrentGroup}
     */
    public function __construct(
        public ?string $provider = null,
        public bool $cascadeCurrentGroup = false,
    ) {
    }
}
