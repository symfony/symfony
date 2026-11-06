<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures;

use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\DefinitionParent;
use Symfony\Component\JsonSchema\DefinitionProcessor\DefinitionProcessorInterface;
use Symfony\Component\JsonSchema\SchemaDocument;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\ObjectType;

class IdentifierDefinitionProcessor implements DefinitionProcessorInterface
{
    /**
     * @param ObjectType|GenericType<ObjectType> $type
     * @param array<string, mixed> $definition
     *
     * @return array<string, mixed>
     */
    public function process(array $definition, ObjectType|GenericType $type, Configuration $config, SchemaDocument $document, ?DefinitionParent $parent): array
    {
        $definition['properties'] = ['@id' => ['type' => 'string'], ...$definition['properties'] ?? []];

        return $definition;
    }
}
