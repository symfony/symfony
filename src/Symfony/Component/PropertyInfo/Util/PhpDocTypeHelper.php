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

use phpDocumentor\Reflection\PseudoTypes\ConstExpression;
use phpDocumentor\Reflection\PseudoTypes\List_;
use phpDocumentor\Reflection\Type as DocType;
use phpDocumentor\Reflection\Types\Array_;
use phpDocumentor\Reflection\Types\Collection;
use phpDocumentor\Reflection\Types\Compound;
use phpDocumentor\Reflection\Types\Null_;
use phpDocumentor\Reflection\Types\Nullable;
use Symfony\Component\PropertyInfo\Type;

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
     *
     * @return Type[]
     */
    public function getTypes(DocType $varType): array
    {
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

            $type = $this->createType($varType, $nullable);
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
            $type = $this->createType($varType, $nullable);
            if (null !== $type) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * Creates a {@see Type} from a PHPDoc type.
     */
    private function createType(DocType $type, bool $nullable, ?string $docType = null): ?Type
    {
        $docType = $docType ?? (string) $type;

        if ($type instanceof Collection) {
            $fqsen = $type->getFqsen();
            if ($fqsen && 'list' === $fqsen->getName() && !class_exists(List_::class, false) && !class_exists((string) $fqsen)) {
                // Workaround for phpdocumentor/type-resolver < 1.6
                return new Type(Type::BUILTIN_TYPE_ARRAY, $nullable, null, true, new Type(Type::BUILTIN_TYPE_INT), $this->getTypes($type->getValueType()));
            }

            [$phpType, $class] = $this->getPhpTypeAndClass((string) $fqsen);

            $keys = $this->getTypes($type->getKeyType());
            $values = $this->getTypes($type->getValueType());

            return new Type($phpType, $nullable, $class, true, $keys, $values);
        }

        // Cannot guess
        if (!$docType || 'mixed' === $docType) {
            return null;
        }

        if (str_ends_with($docType, '[]') && $type instanceof Array_) {
            $collectionKeyTypes = new Type(Type::BUILTIN_TYPE_INT);
            $collectionValueTypes = $this->getTypes($type->getValueType());

            return new Type(Type::BUILTIN_TYPE_ARRAY, $nullable, null, true, $collectionKeyTypes, $collectionValueTypes);
        }

        if ((str_starts_with($docType, 'list<') || str_starts_with($docType, 'array<')) && $type instanceof Array_) {
            // array<value> is converted to x[] which is handled above
            // so it's only necessary to handle array<key, value> here
            $collectionKeyTypes = $this->getTypes($type->getKeyType());
            $collectionValueTypes = $this->getTypes($type->getValueType());

            return new Type(Type::BUILTIN_TYPE_ARRAY, $nullable, null, true, $collectionKeyTypes, $collectionValueTypes);
        }

        $docType = $this->normalizeType($docType);
        [$phpType, $class] = $this->getPhpTypeAndClass($docType);

        if ('array' === $docType) {
            return new Type(Type::BUILTIN_TYPE_ARRAY, $nullable, null, true, null, null);
        }

        return new Type($phpType, $nullable, $class);
    }

    private function normalizeType(string $docType): string
    {
        switch ($docType) {
            case 'integer':
                return 'int';

            case 'boolean':
                return 'bool';

                // real is not part of the PHPDoc standard, so we ignore it
            case 'double':
                return 'float';

            case 'callback':
                return 'callable';

            case 'void':
                return 'null';

            default:
                return $docType;
        }
    }

    private function getPhpTypeAndClass(string $docType): array
    {
        if (\in_array($docType, Type::$builtinTypes)) {
            return [$docType, null];
        }

        if (\in_array($docType, ['parent', 'self', 'static'], true)) {
            return ['object', $docType];
        }

        return ['object', ltrim($docType, '\\')];
    }
}
