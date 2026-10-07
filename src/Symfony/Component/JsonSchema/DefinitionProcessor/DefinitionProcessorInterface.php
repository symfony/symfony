<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\DefinitionProcessor;

use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\DefinitionPolicy\DefinitionParent;

/**
 * Post-processes every object definition once its properties are assembled.
 *
 * Processors run in order on each definition, once per definition name, after the definitions
 * of its properties have been built (children first). They run on every definition, before
 * {@see Schema::flatten()} inlines any of them, but not on the inline schemas returned by a
 * ClassSchemaResolverInterface.
 *
 * A processor can only change the definition it receives: wrapping it, adding properties
 * or keywords. Definitions shared by several classes belong to the caller, on the returned Schema.
 *
 * @experimental
 */
interface DefinitionProcessorInterface
{
    /**
     * @param array<string, mixed> $definition
     * @param class-string         $class
     * @param ?DefinitionParent    $parent     null for the root definition
     *
     * @return array<string, mixed>
     */
    public function process(array $definition, string $class, Configuration $config, ?DefinitionParent $parent): array;
}
