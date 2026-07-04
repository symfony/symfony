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

use phpDocumentor\Reflection\PseudoType;
use phpDocumentor\Reflection\PseudoTypes\ConstExpression;
use phpDocumentor\Reflection\PseudoTypes\Generic;
use phpDocumentor\Reflection\PseudoTypes\List_;
use phpDocumentor\Reflection\PseudoTypes\Scalar;
use phpDocumentor\Reflection\Type as DocType;
use phpDocumentor\Reflection\Types\Array_;
use phpDocumentor\Reflection\Types\Collection;
use phpDocumentor\Reflection\Types\Compound;
use phpDocumentor\Reflection\Types\Integer;
use phpDocumentor\Reflection\Types\Mixed_;
use phpDocumentor\Reflection\Types\Null_;
use phpDocumentor\Reflection\Types\Nullable;
use phpDocumentor\Reflection\Types\Scalar as LegacyScalar;
use phpDocumentor\Reflection\Types\String_;
use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\TypeIdentifier;

// Workaround for phpdocumentor/type-resolver < 1.6
// We trigger the autoloader here, so we don't need to trigger it inside the loop later.
class_exists(List_::class);

/**
 * Transforms a php doc type to a {@link Type} instance.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Guilhem N. <egetick@gmail.com>
 */
final class PhpDocTypeHelper
{
    /**
     * Creates a {@see LegacyType} from a PHPDoc type.
     *
     * @deprecated since Symfony 7.3, use "getType" instead
     *
     * @return LegacyType[]
     */
    public function getTypes(DocType $varType): array
    {
        trigger_deprecation('symfony/property-info', '7.3', 'The "%s()" method is deprecated, use "%s::getType()" instead.', __METHOD__, self::class);

        if ($varType instanceof ConstExpression) {
            // It's safer to fall back to other extractors here, as resolving const types correctly is not easy at the moment
            return [];
        }

        $types = [];
        $nullable = false;

        if ($varType instanceof Nullable) {
            $nullable = true;
            $varType = $varType->getActualType();
        }

        if ($varType instanceof LegacyScalar || $varType instanceof Scalar) {
            return [
                new LegacyType(LegacyType::BUILTIN_TYPE_BOOL),
                new LegacyType(LegacyType::BUILTIN_TYPE_FLOAT),
                new LegacyType(LegacyType::BUILTIN_TYPE_INT),
                new LegacyType(LegacyType::BUILTIN_TYPE_STRING),
            ];
        }

        if (!$varType instanceof Compound) {
            if ($varType instanceof Null_) {
                $nullable = true;
            }

            $type = $this->createLegacyType($varType, $nullable);
            if (null !== $type) {
                $types[] = $type;
            }

            return $types;
        }

        $varTypes = [];
        for ($typeIndex = 0; $varType->has($typeIndex); ++$typeIndex) {
            $type = $varType->get($typeIndex);

            if ($type instanceof Mixed_) {
                return [];
            }

            if ($type instanceof ConstExpression) {
                // It's safer to fall back to other extractors here, as resolving const types correctly is not easy at the moment
                return [];
            }

            // If null is present, all types are nullable
            if ($type instanceof Null_) {
                $nullable = true;
                continue;
            }

            if ($type instanceof Nullable) {
                $nullable = true;
                $type = $type->getActualType();
            }

            $varTypes[] = $type;
        }

        foreach ($varTypes as $varType) {
            $type = $this->createLegacyType($varType, $nullable);
            if (null !== $type) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * Creates a {@see Type} from a PHPDoc type.
     */
    public function getType(DocType $varType): ?Type
    {
        if ($varType instanceof ConstExpression) {
            // It's safer to fall back to other extractors here, as resolving const types correctly is not easy at the moment
            return null;
        }

        $nullable = false;

        if ($varType instanceof Nullable) {
            $nullable = true;
            $varType = $varType->getActualType();
        }

        if (!$varType instanceof Compound) {
            if ($varType instanceof Null_) {
                $nullable = true;
            }

            $type = $this->createType($varType);

            return $nullable ? Type::nullable($type) : $type;
        }

        $varTypes = [];
        for ($typeIndex = 0; $varType->has($typeIndex); ++$typeIndex) {
            $type = $varType->get($typeIndex);

            if ($type instanceof ConstExpression) {
                // It's safer to fall back to other extractors here, as resolving const types correctly is not easy at the moment
                return null;
            }

            // If null is present, all types are nullable
            if ($type instanceof Null_) {
                $nullable = true;
                continue;
            }

            if ($type instanceof Nullable) {
                $nullable = true;
                $type = $type->getActualType();
            }

            $varTypes[] = $type;
        }

        $unionTypes = [];
        foreach ($varTypes as $varType) {
            if (!$t = $this->createType($varType)) {
                continue;
            }

            if ($t instanceof BuiltinType && TypeIdentifier::MIXED === $t->getTypeIdentifier()) {
                return Type::mixed();
            }

            $unionTypes[] = $t;
        }

        if (!$unionTypes) {
            return null;
        }

        $type = 1 === \count($unionTypes) ? $unionTypes[0] : Type::union(...$unionTypes);

        return $nullable ? Type::nullable($type) : $type;
    }

    /**
     * Creates a {@see LegacyType} from a PHPDoc type.
     */
    private function createLegacyType(DocType $type, bool $nullable): ?LegacyType
    {
        $docType = (string) $type;

        if ('mixed[]' === $docType) {
            $docType = 'array';
        } elseif ('array' !== $docType && $type instanceof Array_ && $this->hasNoExplicitKeyType($type)) {
            $docType = \sprintf('%s[]', $type->getValueType());
        }

        if ($type instanceof Collection || $type instanceof Generic) {
            $fqsen = $type->getFqsen();
            if ($type instanceof Collection && $fqsen && 'list' === $fqsen->getName() && !class_exists(List_::class, false) && !class_exists((string) $fqsen)) {
                // Workaround for phpdocumentor/type-resolver < 1.6
                return new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, $nullable, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), $this->getTypes($type->getValueType()));
            }

            [$phpType, $class] = $this->getPhpTypeAndClass((string) $fqsen);

            $collection = is_a($class, \Traversable::class, true) || is_a($class, \ArrayAccess::class, true);

            // it's safer to fall back to other extractors if the generic type is too abstract
            if (!$collection && !class_exists($class, false) && !interface_exists($class, false)) {
                return null;
            }

            if ($type instanceof Generic) {
                $genericTypes = $type->getTypes();

                if (null === $valueType = $genericTypes[1] ?? null) {
                    $keyType = new Compound([new String_(), new Integer()]);
                    $valueType = $genericTypes[0] ?? new Mixed_();
                } else {
                    $keyType = $genericTypes[0];
                }
            } else {
                $keyType = $type->getKeyType();
                $valueType = $type->getValueType();
            }

            $keys = $this->getTypes($keyType);
            $values = $this->getTypes($valueType);

            return new LegacyType($phpType, $nullable, $class, $collection, $keys, $values);
        }

        // Cannot guess
        if (!$docType || 'mixed' === $docType) {
            return null;
        }

        if (str_ends_with($docType, '[]') && $type instanceof Array_) {
            $collectionValueTypes = $this->getTypes($type->getValueType());

            return new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, $nullable, null, true, null, $collectionValueTypes);
        }

        if ((str_starts_with($docType, 'list<') || str_starts_with($docType, 'array<')) && $type instanceof Array_) {
            // array<value> is converted to x[] which is handled above
            // so it's only necessary to handle array<key, value> here
            $collectionKeyTypes = $this->getTypes($type->getKeyType());
            $collectionValueTypes = $this->getTypes($type->getValueType());

            return new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, $nullable, null, true, $collectionKeyTypes, $collectionValueTypes);
        }

        if ($type instanceof PseudoType) {
            if ($type->underlyingType() instanceof Integer) {
                return new LegacyType(LegacyType::BUILTIN_TYPE_INT, $nullable, null);
            } elseif ($type->underlyingType() instanceof String_) {
                return new LegacyType(LegacyType::BUILTIN_TYPE_STRING, $nullable, null);
            }
        }

        $docType = $this->normalizeType($docType);
        [$phpType, $class] = $this->getPhpTypeAndClass($docType);

        if ('array' === $docType) {
            return new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, $nullable, null, true, null, null);
        }

        return new LegacyType($phpType, $nullable, $class);
    }

    /**
     * Creates a {@see Type} from a PHPDoc type.
     */
    private function createType(DocType $docType): ?Type
    {
        $docTypeString = (string) $docType;

        if ('mixed[]' === $docTypeString) {
            $docTypeString = 'array';
        }

        if ($docType instanceof Generic) {
            $fqsen = $docType->getFqsen();

            [$phpType, $class] = $this->getPhpTypeAndClass((string) $fqsen);

            $collection = is_a($class, \Traversable::class, true) || is_a($class, \ArrayAccess::class, true);

            // it's safer to fall back to other extractors if the generic type is too abstract
            if (!$collection && !class_exists($class, false) && !interface_exists($class, false)) {
                return null;
            }

            $genericTypes = $docType->getTypes();
            $type = null !== $class ? Type::object($class) : Type::builtin($phpType);

            if ($collection) {
                if (null === $valueType = $genericTypes[1] ?? null) {
                    $keyType = null;
                    $valueType = $genericTypes[0] ?? null;
                } else {
                    $keyType = $genericTypes[0] ?? null;
                }

                $value = $valueType ? $this->getType($valueType) : null;
                $key = $keyType ? $this->getType($keyType) : null;

                return Type::collection($type, $value, $key);
            }

            $variableTypes = array_map(fn ($t) => $this->getType($t), $genericTypes);

            return Type::generic($type, ...array_filter($variableTypes));
        }

        if ($docType instanceof Collection) {
            $fqsen = $docType->getFqsen();
            if ($fqsen && 'list' === $fqsen->getName() && !class_exists(List_::class, false) && !class_exists((string) $fqsen)) {
                // Workaround for phpdocumentor/type-resolver < 1.6
                return Type::list($this->getType($docType->getValueType()));
            }

            [$phpType, $class] = $this->getPhpTypeAndClass((string) $fqsen);

            $collection = is_a($class, \Traversable::class, true) || is_a($class, \ArrayAccess::class, true);

            // it's safer to fall back to other extractors if the generic type is too abstract
            if (!$collection && !class_exists($class, false) && !interface_exists($class, false)) {
                return null;
            }

            $type = null !== $class ? Type::object($class) : Type::builtin($phpType);

            if ($collection) {
                $value = $this->getType($docType->getValueType());
                $key = $this->getType($docType->getKeyType());

                return Type::collection($type, $value, $key);
            }

            $variableTypes = [];

            if (!$this->hasNoExplicitKeyType($docType) && null !== $keyType = $this->getType($docType->getKeyType())) {
                $variableTypes[] = $keyType;
            }

            if (null !== $valueType = $this->getType($docType->getValueType())) {
                $variableTypes[] = $valueType;
            }

            return Type::generic($type, ...$variableTypes);
        }

        if (!$docTypeString) {
            return null;
        }

        if ($docType instanceof Array_ && $this->hasNoExplicitKeyType($docType) && str_starts_with($docTypeString, 'array<')) {
            return Type::array($this->getType($docType->getValueType()));
        }

        if (str_ends_with($docTypeString, '[]') && $docType instanceof Array_) {
            return Type::array($this->getType($docType->getValueType()));
        }

        if (str_starts_with($docTypeString, 'list<') && $docType instanceof Array_) {
            $collectionValueType = $this->getType($docType->getValueType());

            return Type::list($collectionValueType);
        }

        if (str_starts_with($docTypeString, 'array<') && $docType instanceof Array_) {
            // array<value> is handled above
            // so it's only necessary to handle array<key, value> here
            $collectionKeyType = $this->getType($docType->getKeyType());
            $collectionValueType = $this->getType($docType->getValueType());

            return Type::array($collectionValueType, $collectionKeyType);
        }

        $docTypeString = match ($docTypeString) {
            'integer' => 'int',
            'boolean' => 'bool',
            // real is not part of the PHPDoc standard, so we ignore it
            'double' => 'float',
            'callback' => 'callable',
            'void' => 'null',
            default => $docTypeString,
        };

        [$phpType, $class] = $this->getPhpTypeAndClass($docTypeString);

        if ('array' === $docTypeString) {
            return Type::array();
        }

        if (null === $class) {
            return Type::builtin($phpType);
        }

        if ($docType instanceof LegacyScalar || $docType instanceof Scalar) {
            return Type::object('scalar');
        }

        if ($docType instanceof PseudoType) {
            if ($docType->underlyingType() instanceof Integer) {
                return Type::int();
            }

            if ($docType->underlyingType() instanceof String_) {
                return Type::string();
            }

            // It's safer to fall back to other extractors here, as resolving pseudo types correctly is not easy at the moment
            return null;
        }

        return Type::object($class);
    }

    private function normalizeType(string $docType): string
    {
        return match ($docType) {
            'integer' => 'int',
            'boolean' => 'bool',
            // real is not part of the PHPDoc standard, so we ignore it
            'double' => 'float',
            'callback' => 'callable',
            'void' => 'null',
            default => $docType,
        };
    }

    private function hasNoExplicitKeyType(Array_|Collection $type): bool
    {
        if (method_exists($type, 'getOriginalKeyType')) {
            return null === $type->getOriginalKeyType();
        }

        // Workaround for phpdocumentor/reflection-docblock < 6
        // "getOriginalKeyType()" doesn't exist, so we check if key type is Compound(string, int) which is the default.
        return $type->getKeyType() instanceof Compound;
    }

    private function getPhpTypeAndClass(string $docType): array
    {
        if (\in_array($docType, TypeIdentifier::values(), true)) {
            return [$docType, null];
        }

        if (\in_array($docType, ['parent', 'self', 'static'], true)) {
            return ['object', $docType];
        }

        return ['object', ltrim($docType, '\\')];
    }
}
