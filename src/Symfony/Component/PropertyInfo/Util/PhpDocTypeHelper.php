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
use phpDocumentor\Reflection\PseudoTypes\List_;
use phpDocumentor\Reflection\Type as DocType;
use phpDocumentor\Reflection\Types\Array_;
use phpDocumentor\Reflection\Types\Collection;
use phpDocumentor\Reflection\Types\Compound;
use phpDocumentor\Reflection\Types\Integer;
use phpDocumentor\Reflection\Types\Null_;
use phpDocumentor\Reflection\Types\Nullable;
use phpDocumentor\Reflection\Types\String_;
use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\TypeInfo\Type;
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
            if (null !== $t = $this->createType($varType)) {
                $unionTypes[] = $t;
            }
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

        if ($type instanceof Collection) {
            $fqsen = $type->getFqsen();
            if ($fqsen && 'list' === $fqsen->getName() && !class_exists(List_::class, false) && !class_exists((string) $fqsen)) {
                // Workaround for phpdocumentor/type-resolver < 1.6
                return new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, $nullable, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), $this->getTypes($type->getValueType()));
            }

            [$phpType, $class] = $this->getPhpTypeAndClass((string) $fqsen);

            $collection = is_a($class, \Traversable::class, true) || is_a($class, \ArrayAccess::class, true);

            // it's safer to fall back to other extractors if the generic type is too abstract
            if (!$collection && !class_exists($class)) {
                return null;
            }

            $keys = $this->getTypes($type->getKeyType());
            $values = $this->getTypes($type->getValueType());

            return new LegacyType($phpType, $nullable, $class, $collection, $keys, $values);
        }

        // Cannot guess
        if (!$docType || 'mixed' === $docType) {
            return null;
        }

        if (str_ends_with($docType, '[]') && $type instanceof Array_) {
            $collectionKeyTypes = new LegacyType(LegacyType::BUILTIN_TYPE_INT);
            $collectionValueTypes = $this->getTypes($type->getValueType());

            return new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, $nullable, null, true, $collectionKeyTypes, $collectionValueTypes);
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

        if ($docType instanceof Collection) {
            $fqsen = $docType->getFqsen();
            if ($fqsen && 'list' === $fqsen->getName() && !class_exists(List_::class, false) && !class_exists((string) $fqsen)) {
                // Workaround for phpdocumentor/type-resolver < 1.6
                return Type::list($this->getType($docType->getValueType()));
            }

            [$phpType, $class] = $this->getPhpTypeAndClass((string) $fqsen);

            $variableTypes = [];

            if (null !== $valueType = $this->getType($docType->getValueType())) {
                $variableTypes[] = $valueType;
            }

            if (null !== $keyType = $this->getType($docType->getKeyType())) {
                $variableTypes[] = $keyType;
            }

            $type = null !== $class ? Type::object($class) : Type::builtin($phpType);

            return Type::collection($type, ...$variableTypes);
        }

        if (!$docTypeString) {
            return null;
        }

        if (str_ends_with($docTypeString, '[]') && $docType instanceof Array_) {
            return Type::list($this->getType($docType->getValueType()));
        }

        if (str_starts_with($docTypeString, 'list<') && $docType instanceof Array_) {
            $collectionValueType = $this->getType($docType->getValueType());

            return Type::list($collectionValueType);
        }

        if (str_starts_with($docTypeString, 'array<') && $docType instanceof Array_) {
            // array<value> is converted to x[] which is handled above
            // so it's only necessary to handle array<key, value> here
            $collectionKeyType = $this->getType($docType->getKeyType());
            $collectionValueType = $this->getType($docType->getValueType());

            return Type::array($collectionValueType, $collectionKeyType);
        }

        if ($docType instanceof PseudoType) {
            if ($docType->underlyingType() instanceof Integer) {
                return Type::int();
            } elseif ($docType->underlyingType() instanceof String_) {
                return Type::string();
            }
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

        return null !== $class ? Type::object($class) : Type::builtin($phpType);
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
