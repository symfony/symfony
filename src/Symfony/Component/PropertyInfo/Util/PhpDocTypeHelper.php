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
use Symfony\Component\TypeInfo\BackwardCompatibilityHelper;
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
     * @deprecated since Symfony 7.1, use "getType" instead
     *
     * @return LegacyType[]
     */
    public function getTypes(DocType $varType): array
    {
        trigger_deprecation('symfony/property-info', '7.1', 'The "%s()" method is deprecated, use "%s::getType()" instead.', __METHOD__, self::class);

        return BackwardCompatibilityHelper::convertTypeToLegacyTypes($this->getType($varType)) ?? [];
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

            return $this->createType($varType, $nullable);
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
            $t = $this->createType($varType, $nullable);
            if (null !== $t) {
                $unionTypes[] = $t;
            }
        }

        $type = 1 === \count($unionTypes) ? $unionTypes[0] : Type::union(...$unionTypes);

        return $nullable ? Type::nullable($type) : $type;
    }

    /**
     * Creates a {@see Type} from a PHPDoc type.
     */
    private function createType(DocType $type, bool $nullable, ?string $docType = null): ?Type
    {
        $docType ??= (string) $type;

        if ($type instanceof Collection) {
            $fqsen = $type->getFqsen();
            if ($fqsen && 'list' === $fqsen->getName() && !class_exists(List_::class, false) && !class_exists((string) $fqsen)) {
                // Workaround for phpdocumentor/type-resolver < 1.6
                return Type::list($this->getType($type->getValueType()));
            }

            [$phpType, $class] = $this->getPhpTypeAndClass((string) $fqsen);

            $variableTypes = [];

            if (null !== $valueType = $this->getType($type->getValueType())) {
                $variableTypes[] = $valueType;
            }

            if (null !== $keyType = $this->getType($type->getKeyType())) {
                $variableTypes[] = $keyType;
            }

            $t = null !== $class ? Type::object($class) : Type::builtin($phpType);
            $t = Type::collection($t, ...$variableTypes);

            return $nullable ? Type::nullable($t) : $t;
        }

        // Cannot guess
        if (!$docType) {
            return null;
        }

        if (str_ends_with($docType, '[]') && $type instanceof Array_) {
            return Type::list($this->getType($type->getValueType()));
        }

        if (str_starts_with($docType, 'list<') && $type instanceof Array_) {
            $collectionValueType = $this->getType($type->getValueType());

            $t = Type::list($collectionValueType);

            return $nullable ? Type::nullable($t) : $t;
        }

        if (str_starts_with($docType, 'array<') && $type instanceof Array_) {
            // array<value> is converted to x[] which is handled above
            // so it's only necessary to handle array<key, value> here
            $collectionKeyType = $this->getType($type->getKeyType());
            $collectionValueType = $this->getType($type->getValueType());

            $t = Type::array($collectionValueType, $collectionKeyType);

            return $nullable ? Type::nullable($t) : $t;
        }

        if ($type instanceof PseudoType) {
            if ($type->underlyingType() instanceof Integer) {
                return $nullable ? Type::nullable(Type::int()) : Type::int();
            } elseif ($type->underlyingType() instanceof String_) {
                return $nullable ? Type::nullable(Type::string()) : Type::string();
            }
        }

        $docType = $this->normalizeType($docType);

        [$phpType, $class] = $this->getPhpTypeAndClass($docType);

        if ('array' === $docType) {
            return $nullable ? Type::nullable(Type::array()) : Type::array();
        }

        $t = null !== $class ? Type::object($class) : Type::builtin($phpType);

        return $nullable ? Type::nullable($t) : $t;
    }

    private function normalizeType(string $docType): string
    {
        return match ($docType) {
            'integer' => TypeIdentifier::INT->value,
            'boolean' => TypeIdentifier::BOOL->value,
            // real is not part of the PHPDoc standard, so we ignore it
            'double' => TypeIdentifier::FLOAT->value,
            'callback' => TypeIdentifier::CALLABLE->value,
            'void' => TypeIdentifier::NULL->value,
            default => $docType,
        };
    }

    private function getPhpTypeAndClass(string $docType): array
    {
        if (\in_array($docType, TypeIdentifier::values(), true)) {
            return [$docType, null];
        }

        if (\in_array($docType, ['parent', 'self', 'static'], true)) {
            return [TypeIdentifier::OBJECT->value, $docType];
        }

        return [TypeIdentifier::OBJECT->value, ltrim($docType, '\\')];
    }
}
