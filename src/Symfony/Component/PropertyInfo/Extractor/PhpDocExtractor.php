<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Extractor;

use phpDocumentor\Reflection\ClassReflector;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\FileReflector;
use Symfony\Component\PropertyInfo\PropertyDescriptionExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

/**
 * Extracts data using a PHPDoc parser.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PhpDocExtractor implements PropertyDescriptionExtractorInterface, PropertyTypeExtractorInterface
{
    const PROPERTY = 0;
    const ACCESSOR = 1;
    const MUTATOR = 2;

    /**
     * @var FileReflector[]
     */
    private $fileReflectors = array();

    /**
     * @var DocBlock[]
     */
    private $docBlocks = array();

    /**
     * {@inheritdoc}
     */
    public function getShortDescription($class, $property, array $context = array())
    {
        list($docBlock) = $this->getDocBlock($class, $property);
        if (!$docBlock) {
            return;
        }

        $shortDescription = $docBlock->getShortDescription();
        if ($shortDescription) {
            return $shortDescription;
        }

        foreach ($docBlock->getTagsByName('var') as $var) {
            $parsedDescription = $var->getParsedDescription();

            if (isset($parsedDescription[0]) && '' !== $parsedDescription[0]) {
                return $parsedDescription[0];
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getLongDescription($class, $property, array $context = array())
    {
        list($docBlock) = $this->getDocBlock($class, $property);
        if (!$docBlock) {
            return;
        }

        $contents = $docBlock->getLongDescription()->getContents();

        return '' === $contents ? null : $contents;
    }

    /**
     * {@inheritdoc}
     */
    public function getTypes($class, $property, array $context = array())
    {
        list($docBlock, $source, $prefix) = $this->getDocBlock($class, $property);
        if (!$docBlock) {
            return;
        }

        switch ($source) {
            case self::PROPERTY:
                $tag = 'var';
                break;

            case self::ACCESSOR:
                $tag = 'return';
                break;

            case self::MUTATOR:
                $tag = 'param';
                break;
        }

        $types = array();
        foreach ($docBlock->getTagsByName($tag) as $tag) {
            $varTypes = $tag->getTypes();

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
        }

        if (!isset($types[0])) {
            return;
        }

        if (!in_array($prefix, ReflectionExtractor::$arrayMutatorPrefixes)) {
            return $types;
        }

        return array(new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), $types[0]));
    }

    /**
     * Gets the FileReflector associated with the class.
     *
     * @param \ReflectionClass $reflectionClass
     *
     * @return FileReflector|null
     */
    private function getFileReflector(\ReflectionClass $reflectionClass)
    {
        if (!($fileName = $reflectionClass->getFileName()) || 'hh' === pathinfo($fileName, PATHINFO_EXTENSION)) {
            return;
        }

        if (isset($this->fileReflectors[$fileName])) {
            return $this->fileReflectors[$fileName];
        }

        $this->fileReflectors[$fileName] = new FileReflector($fileName);
        $this->fileReflectors[$fileName]->process();

        return $this->fileReflectors[$fileName];
    }

    /**
     * Gets the DocBlock for this property.
     *
     * @param string $class
     * @param string $property
     *
     * @return array
     */
    private function getDocBlock($class, $property)
    {
        $propertyHash = sprintf('%s::%s', $class, $property);

        if (isset($this->docBlocks[$propertyHash])) {
            return $this->docBlocks[$propertyHash];
        }

        $ucFirstProperty = ucfirst($property);

        switch (true) {
            case $docBlock = $this->getDocBlockFromProperty($class, $property):
                $data = array($docBlock, self::PROPERTY, null);
                break;

            case list($docBlock) = $this->getDocBlockFromMethod($class, $ucFirstProperty, self::ACCESSOR):
                $data = array($docBlock, self::ACCESSOR, null);
                break;

            case list($docBlock, $prefix) = $this->getDocBlockFromMethod($class, $ucFirstProperty, self::MUTATOR):
                $data = array($docBlock, self::MUTATOR, $prefix);
                break;

            default:
                $data = array(null, null);
        }

        return $this->docBlocks[$propertyHash] = $data;
    }

    /**
     * Gets the DocBlock from a property.
     *
     * @param string $class
     * @param string $property
     *
     * @return DocBlock|null
     */
    private function getDocBlockFromProperty($class, $property)
    {
        // Use a ReflectionProperty instead of $class to get the parent class if applicable
        try {
            $reflectionProperty = new \ReflectionProperty($class, $property);
        } catch (\ReflectionException $reflectionException) {
            return;
        }

        $reflectionCLass = $reflectionProperty->getDeclaringClass();

        $fileReflector = $this->getFileReflector($reflectionCLass);
        if (!$fileReflector) {
            return;
        }

        foreach ($fileReflector->getClasses() as $classReflector) {
            $className = $this->getClassName($classReflector);

            if ($className === $reflectionCLass->name) {
                foreach ($classReflector->getProperties() as $propertyReflector) {
                    // strip the $ prefix
                    $propertyName = substr($propertyReflector->getName(), 1);

                    if ($propertyName === $property) {
                        return $propertyReflector->getDocBlock();
                    }
                }
            }
        }
    }

    /**
     * Gets DocBlock from accessor or mutator method.
     *
     * @param string $class
     * @param string $ucFirstProperty
     * @param int    $type
     *
     * @return DocBlock|null
     */
    private function getDocBlockFromMethod($class, $ucFirstProperty, $type)
    {
        $prefixes = $type === self::ACCESSOR ? ReflectionExtractor::$accessorPrefixes : ReflectionExtractor::$mutatorPrefixes;

        foreach ($prefixes as $prefix) {
            $methodName = $prefix.$ucFirstProperty;

            try {
                $reflectionMethod = new \ReflectionMethod($class, $methodName);

                if (
                    (self::ACCESSOR === $type && 0 === $reflectionMethod->getNumberOfRequiredParameters()) ||
                    (self::MUTATOR === $type && $reflectionMethod->getNumberOfParameters() >= 1)
                ) {
                    break;
                }
            } catch (\ReflectionException $reflectionException) {
                // Try the next prefix if the method doesn't exist
            }
        }

        if (!isset($reflectionMethod)) {
            return;
        }

        $reflectionClass = $reflectionMethod->getDeclaringClass();
        $fileReflector = $this->getFileReflector($reflectionClass);

        if (!$fileReflector) {
            return;
        }

        foreach ($fileReflector->getClasses() as $classReflector) {
            $className = $this->getClassName($classReflector);

            if ($className === $reflectionClass->name) {
                if ($methodReflector = $classReflector->getMethod($methodName)) {
                    return array($methodReflector->getDocBlock(), $prefix);
                }
            }
        }
    }

    /**
     * Gets the normalized class name (without trailing backslash).
     *
     * @param ClassReflector $classReflector
     *
     * @return string
     */
    private function getClassName(ClassReflector $classReflector)
    {
        $className = $classReflector->getName();
        if ('\\' === $className[0]) {
            return substr($className, 1);
        }

        return $className;
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
