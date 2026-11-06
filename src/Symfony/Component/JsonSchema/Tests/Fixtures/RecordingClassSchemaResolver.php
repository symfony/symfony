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

class RecordingClassSchemaResolver implements ClassSchemaResolverInterface
{
    /**
     * @var list<array{ObjectType|GenericType<ObjectType>, ?DefinitionParent}>
     */
    public array $calls = [];

    /**
     * @var list<Configuration>
     */
    public array $configs = [];

    /**
     * @param ObjectType|GenericType<ObjectType> $type
     *
     * @return array<string, mixed>|null
     */
    public function resolve(ObjectType|GenericType $type, Configuration $config, SchemaDocument $document, ?DefinitionParent $parent = null): ?array
    {
        $this->calls[] = [$type, $parent];
        $this->configs[] = $config;

        return null;
    }
}
