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

use Symfony\Component\JsonSchema\ClassSchemaResolver\ClassSchemaResolverInterface;
use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\DefinitionParent;
use Symfony\Component\JsonSchema\SchemaDocument;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\ObjectType;

class IriReferenceClassSchemaResolver implements ClassSchemaResolverInterface
{
    /**
     * @param class-string $class
     */
    public function __construct(
        private readonly string $class,
    ) {
    }

    /**
     * @param ObjectType|GenericType<ObjectType> $type
     *
     * @return array<string, mixed>|null
     */
    public function resolve(ObjectType|GenericType $type, Configuration $config, SchemaDocument $document, ?DefinitionParent $parent = null): ?array
    {
        $class = ($type instanceof GenericType ? $type->getWrappedType() : $type)->getClassName();
        if (null === $parent || $class !== $this->class) {
            return null;
        }

        return ['type' => 'string', 'format' => 'iri-reference'];
    }
}
