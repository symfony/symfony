<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Util;

use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\CallableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\CallableTypeParameterNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ThisTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use Symfony\Component\PropertyInfo\PhpStan\NameScope;
use Symfony\Component\PropertyInfo\Type;

/**
 * Transforms a php doc tag value to a {@link Type} instance.
 *
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 *
 * @internal
 */
final class PhpStanTypeHelper
{
    /**
     * Creates a {@see Type} from a PhpDocTagValueNode type.
     *
     * @return Type[]
     */
    public function getTypes(PhpDocTagValueNode $node, NameScope $nameScope): array
    {
        if ($node instanceof ParamTagValueNode || $node instanceof ReturnTagValueNode || $node instanceof VarTagValueNode) {
            return $this->compressNullableType($this->extractTypes($node->type, $nameScope));
        }

        return [];
    }

    /**
     * Because PhpStan extract null as a separated type when Symfony / PHP compress it in the first available type we
     * need this method to mimic how Symfony want null types.
     *
     * @param Type[] $types
     *
     * @return Type[]
     */
    private function compressNullableType(array $types): array
    {
        $firstTypeIndex = null;
        $nullableTypeIndex = null;

        foreach ($types as $k => $type) {
            if (null === $firstTypeIndex && Type::BUILTIN_TYPE_NULL !== $type->getBuiltinType() && !$type->isNullable()) {
                $firstTypeIndex = $k;
            }

            if (null === $nullableTypeIndex && Type::BUILTIN_TYPE_NULL === $type->getBuiltinType()) {
                $nullableTypeIndex = $k;
            }

            if (null !== $firstTypeIndex && null !== $nullableTypeIndex) {
                break;
            }
        }

        if (null !== $firstTypeIndex && null !== $nullableTypeIndex) {
            $firstType = $types[$firstTypeIndex];
            $types[$firstTypeIndex] = new Type(
                $firstType->getBuiltinType(),
                true,
                $firstType->getClassName(),
                $firstType->isCollection(),
                $firstType->getCollectionKeyTypes(),
                $firstType->getCollectionValueTypes()
            );
            unset($types[$nullableTypeIndex]);
        }

        return array_values($types);
    }

    /**
     * @return Type[]
     */
    private function extractTypes(TypeNode $node, NameScope $nameScope): array
    {
        if ($node instanceof UnionTypeNode) {
            $types = [];
            foreach ($node->types as $type) {
                if ($type instanceof ConstTypeNode) {
                    // It's safer to fall back to other extractors here, as resolving const types correctly is not easy at the moment
                    return [];
                }
                foreach ($this->extractTypes($type, $nameScope) as $subType) {
                    $types[] = $subType;
                }
            }

            return $this->compressNullableType($types);
        }
        if ($node instanceof GenericTypeNode) {
            if ('class-string' === $node->type->name) {
                return [new Type(Type::BUILTIN_TYPE_STRING)];
            }

            [$mainType] = $this->extractTypes($node->type, $nameScope);

            if (Type::BUILTIN_TYPE_INT === $mainType->getBuiltinType()) {
                return [$mainType];
            }

            $collection = $mainType->isCollection() || \is_a($mainType->getClassName(), \Traversable::class, true) || \is_a($mainType->getClassName(), \ArrayAccess::class, true);

            // it's safer to fall back to other extractors if the generic type is too abstract
            if (!$collection && !class_exists($mainType->getClassName())) {
                return [];
            }

            $collectionKeyTypes = $mainType->getCollectionKeyTypes();
            $collectionKeyValues = [];
            if (1 === \count($node->genericTypes)) {
                foreach ($this->extractTypes($node->genericTypes[0], $nameScope) as $subType) {
                    $collectionKeyValues[] = $subType;
                }
            } elseif (2 === \count($node->genericTypes)) {
                foreach ($this->extractTypes($node->genericTypes[0], $nameScope) as $keySubType) {
                    $collectionKeyTypes[] = $keySubType;
                }
                foreach ($this->extractTypes($node->genericTypes[1], $nameScope) as $valueSubType) {
                    $collectionKeyValues[] = $valueSubType;
                }
            }

            return [new Type($mainType->getBuiltinType(), $mainType->isNullable(), $mainType->getClassName(), $collection, $collectionKeyTypes, $collectionKeyValues)];
        }
        if ($node instanceof ArrayShapeNode) {
            return [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true)];
        }
        if ($node instanceof ArrayTypeNode) {
            return [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, [new Type(Type::BUILTIN_TYPE_INT)], $this->extractTypes($node->type, $nameScope))];
        }
        if ($node instanceof CallableTypeNode || $node instanceof CallableTypeParameterNode) {
            return [new Type(Type::BUILTIN_TYPE_CALLABLE)];
        }
        if ($node instanceof NullableTypeNode) {
            $subTypes = $this->extractTypes($node->type, $nameScope);
            if (\count($subTypes) > 1) {
                $subTypes[] = new Type(Type::BUILTIN_TYPE_NULL);

                return $subTypes;
            }

            return [new Type($subTypes[0]->getBuiltinType(), true, $subTypes[0]->getClassName(), $subTypes[0]->isCollection(), $subTypes[0]->getCollectionKeyTypes(), $subTypes[0]->getCollectionValueTypes())];
        }
        if ($node instanceof ThisTypeNode) {
            return [new Type(Type::BUILTIN_TYPE_OBJECT, false, $nameScope->resolveRootClass())];
        }
        if ($node instanceof IdentifierTypeNode) {
            if (\in_array($node->name, Type::$builtinTypes, true)) {
                return [new Type($node->name, false, null, \in_array($node->name, Type::$builtinCollectionTypes, true))];
            }

            return match ($node->name) {
                'integer',
                'positive-int',
                'negative-int' => [new Type(Type::BUILTIN_TYPE_INT)],
                'double' => [new Type(Type::BUILTIN_TYPE_FLOAT)],
                'list',
                'non-empty-list' => [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT))],
                'non-empty-array' => [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true)],
                'mixed' => [], // mixed seems to be ignored in all other extractors
                'parent' => [new Type(Type::BUILTIN_TYPE_OBJECT, false, $node->name)],
                'static',
                'self' => [new Type(Type::BUILTIN_TYPE_OBJECT, false, $nameScope->resolveRootClass())],
                'class-string',
                'html-escaped-string',
                'lowercase-string',
                'non-empty-lowercase-string',
                'non-empty-string',
                'numeric-string',
                'trait-string',
                'interface-string',
                'literal-string' => [new Type(Type::BUILTIN_TYPE_STRING)],
                'void' => [new Type(Type::BUILTIN_TYPE_NULL)],
                'scalar' => [new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_FLOAT), new Type(Type::BUILTIN_TYPE_STRING), new Type(Type::BUILTIN_TYPE_BOOL)],
                'number' => [new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_FLOAT)],
                'numeric' => [new Type(Type::BUILTIN_TYPE_INT), new Type(Type::BUILTIN_TYPE_FLOAT), new Type(Type::BUILTIN_TYPE_STRING)],
                'array-key' => [new Type(Type::BUILTIN_TYPE_STRING), new Type(Type::BUILTIN_TYPE_INT)],
                default => [new Type(Type::BUILTIN_TYPE_OBJECT, false, $nameScope->resolveStringName($node->name))],
            };
        }

        return [];
    }
}
