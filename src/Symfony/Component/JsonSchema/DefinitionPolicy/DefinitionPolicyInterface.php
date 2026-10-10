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
 * Names the definition describing a class under a given configuration.
 *
 * The name is the identity of a definition: it is called every time a class is reached
 * and a definition is only built for the first occurrence of a name, with that occurrence's
 * parent. Anything that changes the shape of the definition (groups, attributes, a format,
 * the owning property...) must therefore be part of the name. The generator throws when two different
 * shapes (class or shape-relevant configuration) get the same name; a difference coming only from the
 * parent cannot be detected and the first occurrence wins.
 *
 * The name is reserved before the class properties are visited, which is what stops
 * self-referencing classes from recursing forever.
 *
 * @experimental
 */
interface DefinitionPolicyInterface
{
    /**
     * @param class-string      $class
     * @param ?DefinitionParent $parent null for the root definition
     */
    public function nameFor(string $class, Configuration $config, ?DefinitionParent $parent = null): string;
}
