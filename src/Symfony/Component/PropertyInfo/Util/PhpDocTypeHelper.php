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
     * @return Type
     */
    public function getTypes(DocType $varType)
    {
        $types = array();
        $nullable = false;

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

        $varTypes = array();
        for ($typeIndex = 0; $varType->has($typeIndex); ++$typeIndex) {
            $varTypes[] = (string) $varType->get($typeIndex);
        }

        // If null is present, all types are nullable
        $nullKey = array_search(Type::BUILTIN_TYPE_NULL, $varTypes);
        $nullable = false !== $nullKey;

        // Remove the null type from the type if other types are defined
        if ($nullable && count($varTypes) > 1) {
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
            return;
        }

        if ($collection = '[]' === substr($docType, -2)) {
            $docType = substr($docType, 0, -2);
        }

        $docType = $this->normalizeType($docType);
        list($phpType, $class) = $this->getPhpTypeAndClass($docType);

        $array = 'array' === $docType;

        if ($collection || $array) {
            if ($array || 'mixed' === $docType) {
                $collectionKeyType = null;
                $collectionValueType = null;
            } else {
                $collectionKeyType = new Type(Type::BUILTIN_TYPE_INT);
                $collectionValueType = new Type($phpType, false, $class);
            }

            return new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, $collectionKeyType, $collectionValueType);
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
        if (in_array($docType, Type::$builtinTypes)) {
            return array($docType, null);
        }

        return array('object', substr($docType, 1));
    }
}
