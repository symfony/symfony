<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer;

use Symfony\Component\JsonStreamer\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
trait PhpGeneratorTrait
{
    /**
     * @var array<class-string, string|null>
     */
    private array $valueObjectTransformerIdCache = [];

    /**
     * @param class-string $className
     */
    private function getValueObjectTransformerId(string $className): ?string
    {
        if (\array_key_exists($className, $this->valueObjectTransformerIdCache)) {
            return $this->valueObjectTransformerIdCache[$className];
        }

        if ($this->transformers->has($className)) {
            return $this->valueObjectTransformerIdCache[$className] = $className;
        }

        $reflection = new \ReflectionClass($className);
        if (($parent = $reflection->getParentClass()) && $id = $this->getValueObjectTransformerId($parent->getName())) {
            return $this->valueObjectTransformerIdCache[$className] = $id;
        }

        foreach ($reflection->getInterfaceNames() as $interface) {
            if ($id = $this->getValueObjectTransformerId($interface)) {
                return $this->valueObjectTransformerIdCache[$className] = $id;
            }
        }

        return $this->valueObjectTransformerIdCache[$className] = null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function line(string $line, array $context): string
    {
        return str_repeat('    ', $context['indentation_level']).$line."\n";
    }

    private static function createIterableType(ObjectType|GenericType $type): CollectionType
    {
        $variableTypes = $type instanceof GenericType ? $type->getVariableTypes() : [];
        $iterableType = match (\count($variableTypes)) {
            0 => Type::iterable(),
            1 => Type::iterable($variableTypes[0]),
            default => Type::iterable($variableTypes[1], $variableTypes[0]),
        };

        $keyType = $iterableType->getCollectionKeyType();
        foreach ($keyType instanceof UnionType ? $keyType->getTypes() : [$keyType] as $t) {
            if (!$t instanceof BuiltinType || !$t->isIdentifiedBy(TypeIdentifier::INT, TypeIdentifier::STRING)) {
                throw new UnsupportedException(\sprintf('"%s" keys are not supported, only "int" and "string" keys are.', (string) $keyType));
            }
        }

        return $iterableType;
    }
}
