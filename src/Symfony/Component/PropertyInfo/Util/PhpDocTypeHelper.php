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
            return Type::list($this->getType($docType->getValueType()));
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

        return match (true) {
            'array' === $docTypeString => Type::array(),
            null === $class => Type::builtin($phpType),
            $docType instanceof LegacyScalar || $docType instanceof Scalar => Type::object('scalar'),
            $docType instanceof PseudoType => match (true) {
                $docType->underlyingType() instanceof Integer => Type::int(),
                $docType->underlyingType() instanceof String_ => Type::string(),
                default => null, // It's safer to fall back to other extractors here, as resolving pseudo types correctly is not easy at the moment
            },
            default => Type::object($class),
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
