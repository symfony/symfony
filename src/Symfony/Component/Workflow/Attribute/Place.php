<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Attribute;

/**
 * Defines a place of a workflow defined with the AsWorkflow attribute.
 *
 * Use it on the constants of the class, whose values are the names of the places, to define places without transitions or to attach metadata to places.
 * Use it on the cases of the string-backed enums used as places to attach metadata to them.
 * Set "initial" to make the place part of the initial marking of the workflows that use it, unless they define their "initialMarking" explicitly.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
#[\Attribute(\Attribute::TARGET_CLASS_CONSTANT)]
final class Place
{
    /**
     * @param array<string, mixed> $metadata The metadata of the place
     * @param bool                 $initial  Whether the place is part of the initial marking
     */
    public function __construct(
        public readonly array $metadata = [],
        public readonly bool $initial = false,
    ) {
    }
}
