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
use Symfony\Component\JsonSchema\DefinitionParent;
use Symfony\Component\JsonSchema\SchemaDocument;

/**
 * Names the definition describing a class under a given configuration.
 *
 * The name is the identity of a definition: it is called every time a class is reached
 * and a definition is only built for the first occurrence of a name, with that occurrence's
 * parent. Anything that changes the shape of the definition (groups, attributes, a format,
 * the owning property...) must therefore be part of the name. The generator throws when two different
 * shapes (class or shape-relevant configuration) get the same name in a document; a difference coming only
 * from the parent cannot be detected and the first occurrence wins.
 *
 * A policy that needs state for a document, for example to give a name to the first class asking for it,
 * can keep it in a WeakMap indexed by the document.
 *
 * @experimental
 */
interface DefinitionPolicyInterface
{
    /**
     * @param class-string      $class
     * @param ?DefinitionParent $parent null for the root definition
     */
    public function nameFor(string $class, Configuration $config, SchemaDocument $document, ?DefinitionParent $parent = null): string;
}
