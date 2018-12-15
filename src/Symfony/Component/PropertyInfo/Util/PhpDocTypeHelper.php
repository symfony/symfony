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

use phpDocumentor\Reflection\Type as DocType;
use phpDocumentor\Reflection\Types\Compound;
use phpDocumentor\Reflection\Types\Null_;
use phpDocumentor\Reflection\Types\Nullable;
use Symfony\Component\PropertyInfo\Type;

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
    public function getTypes(DocType $varType)
    {
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

            $type = $this->createType((string) $varType, $nullable);
            if (null !== $type) {
                $types[] = $type;
            }

            return $types;
        }

        $varTypes = [];
        for ($typeIndex = 0; $varType->has($typeIndex); ++$typeIndex) {
            $varTypes[] = (string) $varType->get($typeIndex);
        }

        // If null is present, all types are nullable
        $nullKey = array_search(Type::BUILTIN_TYPE_NULL, $varTypes);
        $nullable = $nullable || false !== $nullKey;

        // Remove the null type from the type if other types are defined
        if ($nullable && false !== $nullKey && \count($varTypes) > 1) {
            unset($varTypes[$nullKey]);
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
     *
     * @param string $docType
     * @param bool   $nullable
     *
     * @return Type|null
     */
    private function createType($docType, $nullable)
    {
        // Cannot guess
        if (!$docType || 'mixed' === $docType) {
            return null;
        }

        if ('[]' === substr($docType, -2)) {
            if ('mixed[]' === $docType) {
                $collectionKeyType = null;
                $collectionValueType = null;
            } else {
                $collectionKeyType = new Type(Type::BUILTIN_TYPE_INT);
                $collectionValueType = $this->createType(substr($docType, 0, -2), $nullable);
            }

            return new Type(Type::BUILTIN_TYPE_ARRAY, $nullable, null, true, $collectionKeyType, $collectionValueType);
        }

        $docType = $this->normalizeType($docType);
        list($phpType, $class) = $this->getPhpTypeAndClass($docType);

        if ('array' === $docType) {
            return new Type(Type::BUILTIN_TYPE_ARRAY, $nullable, null, true, null, null);
        }

        return new Type($phpType, $nullable, $class);
    }

    /**
     * Normalizes the type.
     *
     * @param string $docType
     *
     * @return string
     */
    private function normalizeType($docType)
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

    /**
     * Gets an array containing the PHP type and the class.
     *
     * @param string $docType
     *
     * @return array
     */
    private function getPhpTypeAndClass($docType)
    {
        if (\in_array($docType, Type::$builtinTypes)) {
            return [$docType, null];
        }

        return ['object', substr($docType, 1)];
    }
}
