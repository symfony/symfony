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
 * Defines the metadata of a place of a workflow defined with the AsWorkflow attribute.
 *
 * Use it on the cases of a string-backed enum whose cases are used as places,
 * or in the "places" argument of the AsWorkflow attribute.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
#[\Attribute(\Attribute::TARGET_CLASS_CONSTANT)]
final class Place
{
    /**
     * @param \BackedEnum|string|null $name     The name of the place; must be omitted when the attribute is used on an enum case, whose value is then used
     * @param array<string, mixed>    $metadata The metadata of the place
     */
    public function __construct(
        public readonly \BackedEnum|string|null $name = null,
        public readonly array $metadata = [],
    ) {
    }
}
