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
use Symfony\Component\TypeInfo\Type;

class ListOfClassSchemaResolver implements ClassSchemaResolverInterface
{
    /**
     * @param class-string $class
     * @param class-string $itemClass
     */
    public function __construct(
        private readonly string $class,
        private readonly string $itemClass,
    ) {
    }

    public function resolve(string $class, Configuration $config, SchemaDocument $document, ?DefinitionParent $parent = null): ?array
    {
        return $this->class === $class ? ['type' => 'array', 'items' => $document->describe(Type::object($this->itemClass), $config, $parent)] : null;
    }
}
