<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Mapping;

use Symfony\Component\TypeInfo\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\IntersectionType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;

/**
 * Enhances properties metadata based on properties' generic type.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class GenericTypePropertyMetadataLoader implements PropertyMetadataLoaderInterface
{
    public function __construct(
        private PropertyMetadataLoaderInterface $decorated,
        private TypeContextFactory $typeContextFactory,
    ) {
    }

    public function load(string $className, array $options = [], array $context = []): array
    {
        $result = $this->decorated->load($className, $options, $context);
        $variableTypes = $this->getClassVariableTypes($className, $context['original_type']);

        foreach ($result as &$metadata) {
            $type = $metadata->getType();

            if (isset($variableTypes[(string) $type])) {
                $metadata = $metadata->withType($this->replaceVariableTypes($type, $variableTypes));
            }
        }

        return $result;
    }

    /**
     * @param class-string $className
     *
     * @return array<string, Type>
     */
    private function getClassVariableTypes(string $className, Type $type): array
    {
        $findTypeWithClassName = static function (string $className, Type $type) use (&$findTypeWithClassName): ?Type {
            if ($type instanceof UnionType || $type instanceof IntersectionType) {
                foreach ($type->getTypes() as $t) {
                    if (null !== $classType = $findTypeWithClassName($className, $t)) {
                        return $classType;
                    }
                }

                return null;
            }

            while ($type instanceof WrappingTypeInterface) {
                $baseType = $type;

                if ($type instanceof GenericType) {
                    foreach ($type->getVariableTypes() as $t) {
                        if (null !== $classType = $findTypeWithClassName($className, $t)) {
                            return $classType;
                        }
                    }
                }

                $type = $type->getWrappedType();

                if ($type instanceof ObjectType && $type->getClassName() === $className) {
                    return $baseType;
                }
            }

            return null;
        };

        if (null === $classType = $findTypeWithClassName($className, $type)) {
            return [];
        }

        $variableTypes = $classType instanceof GenericType ? $classType->getVariableTypes() : [];
        $templates = $this->typeContextFactory->createFromClassName($className)->templates;

        if (\count($templates) !== \count($variableTypes)) {
            throw new InvalidArgumentException(\sprintf('Given %d variable types in "%s", but %d templates are defined in "%2$s".', \count($variableTypes), $className, \count($templates)));
        }

        $templates = array_keys($templates);
        $classVariableTypes = [];

        foreach ($variableTypes as $i => $variableType) {
            $classVariableTypes[$templates[$i]] = $variableType;
        }

        return $classVariableTypes;
    }

    /**
     * @param array<string, Type> $variableTypes
     */
    private function replaceVariableTypes(Type $type, array $variableTypes): Type
    {
        if (isset($variableTypes[(string) $type])) {
            return $variableTypes[(string) $type];
        }

        if ($type instanceof UnionType) {
            return new UnionType(...array_map(fn (Type $t): Type => $this->replaceVariableTypes($t, $variableTypes), $type->getTypes()));
        }

        if ($type instanceof IntersectionType) {
            return new IntersectionType(...array_map(fn (Type $t): Type => $this->replaceVariableTypes($t, $variableTypes), $type->getTypes()));
        }

        if ($type instanceof CollectionType) {
            return new CollectionType($this->replaceVariableTypes($type->getWrappedType(), $variableTypes), $type->isList());
        }

        if ($type instanceof GenericType) {
            return new GenericType(
                $this->replaceVariableTypes($type->getWrappedType(), $variableTypes),
                ...array_map(fn (Type $t): Type => $this->replaceVariableTypes($t, $variableTypes), $type->getVariableTypes()),
            );
        }

        return $type;
    }
}
