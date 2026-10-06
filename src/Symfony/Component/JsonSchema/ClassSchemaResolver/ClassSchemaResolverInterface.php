<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\ClassSchemaResolver;

use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\DefinitionPolicy\DefinitionParent;

/**
 * Describes a class with an inline schema instead of a definition.
 *
 * @experimental
 */
interface ClassSchemaResolverInterface
{
    /**
     * @param class-string          $class
     * @param DefinitionParent|null $parent the property through which the class is reached, null for the root type
     *
     * @return array<string, mixed>|null null when the class is not supported
     */
    public function resolve(string $class, Configuration $config, ?DefinitionParent $parent = null): ?array;
}
