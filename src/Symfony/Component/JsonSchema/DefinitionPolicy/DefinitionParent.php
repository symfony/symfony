<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\DefinitionPolicy;

use Symfony\Component\JsonSchema\Configuration;

/**
 * The property through which a nested definition is reached, chained up to the root definition.
 *
 * The configuration is the one in effect for the owning class, before it is narrowed for the property.
 *
 * @experimental
 */
final readonly class DefinitionParent
{
    /**
     * @param class-string $class
     */
    public function __construct(
        public string $class,
        public string $property,
        public Configuration $config,
        public ?self $parent = null,
    ) {
    }
}
