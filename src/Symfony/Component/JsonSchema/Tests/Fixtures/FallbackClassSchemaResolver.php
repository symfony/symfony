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
use Symfony\Component\JsonSchema\Exception\InvalidArgumentException;
use Symfony\Component\JsonSchema\SchemaDocument;
use Symfony\Component\TypeInfo\Type;

class FallbackClassSchemaResolver implements ClassSchemaResolverInterface
{
    /**
     * @param class-string $class
     * @param class-string $describedClass
     */
    public function __construct(
        private readonly string $class,
        private readonly string $describedClass,
    ) {
    }

    public function resolve(string $class, Configuration $config, SchemaDocument $document, ?DefinitionParent $parent = null): ?array
    {
        if ($this->class !== $class) {
            return null;
        }

        try {
            return $document->describe(Type::object($this->describedClass), $config, $parent);
        } catch (InvalidArgumentException) {
            return ['type' => 'object'];
        }
    }
}
