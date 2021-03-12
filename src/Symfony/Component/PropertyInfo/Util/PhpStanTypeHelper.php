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
            return $this->extractTypes($node->type, $nameScope);
        }

        return [];
    }

    /**
     * @return Type[]
     */
    private function extractTypes(TypeNode $node, NameScope $nameScope): array
    {
        if ($node instanceof UnionTypeNode) {
            $types = [];
            foreach ($node->types as $type) {
                foreach ($this->extractTypes($type, $nameScope) as $subType) {
                    $types[] = $subType;
                }
            }

            return $types;
        } elseif ($node instanceof GenericTypeNode) {
            $mainTypes = $this->extractTypes($node->type, $nameScope);

            $collectionKeyTypes = [];
            $collectionKeyValues = [];
            if (1 === \count($node->genericTypes)) {
                $collectionKeyTypes[] = new Type(Type::BUILTIN_TYPE_INT);
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

            return [new Type($mainTypes[0]->getBuiltinType(), $mainTypes[0]->isNullable(), $mainTypes[0]->getClassName(), true, $collectionKeyTypes, $collectionKeyValues)];
        } elseif ($node instanceof ArrayShapeNode) {
            return [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true)];
        } elseif ($node instanceof ArrayTypeNode) {
            return [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, [new Type(Type::BUILTIN_TYPE_INT)], $this->extractTypes($node->type, $nameScope))];
        } elseif ($node instanceof CallableTypeNode || $node instanceof CallableTypeParameterNode) {
            return [new Type(Type::BUILTIN_TYPE_CALLABLE)];
        } elseif ($node instanceof NullableTypeNode) {
            $subTypes = $this->extractTypes($node->type, $nameScope);
            if (\count($subTypes) > 1) {
                $subTypes[] = new Type(Type::BUILTIN_TYPE_NULL);

                return $subTypes;
            }

            return [new Type($subTypes[0]->getBuiltinType(), true, $subTypes[0]->getClassName(), $subTypes[0]->isCollection(), $subTypes[0]->getCollectionKeyTypes(), $subTypes[0]->getCollectionValueTypes())];
        } elseif ($node instanceof ThisTypeNode) {
            return [new Type(Type::BUILTIN_TYPE_OBJECT, false, $nameScope->resolveRootClass())];
        } elseif ($node instanceof IdentifierTypeNode) {
            if (\in_array($node->name, Type::$builtinTypes)) {
                return [new Type($node->name, false, null, \in_array($node->name, Type::$builtinCollectionTypes))];
            } elseif ('mixed' === $node->name) {
                return []; // mixed seems to be ignored in all other extractors
            }

            if ('parent' === $node->name) {
                return [new Type(Type::BUILTIN_TYPE_OBJECT, false, $node->name)];
            } elseif ('static' === $node->name) {
                return [new Type(Type::BUILTIN_TYPE_OBJECT, false, $nameScope->resolveRootClass())];
            }

            return [new Type(Type::BUILTIN_TYPE_OBJECT, false, $nameScope->resolveStringName($node->name))];
        }

        return [];
    }
}
