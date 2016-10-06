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

use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use phpDocumentor\Reflection\Types\Compound;
use phpDocumentor\Reflection\Types\ContextFactory;
use phpDocumentor\Reflection\Types\Null_;
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
     * @var DocBlock[]
     */
    private $docBlocks = array();

    /**
     * @var DocBlockFactory
     */
    private $docBlockFactory;

    /**
     * @var ContextFactory
     */
    private $contextFactory;

    public function __construct(DocBlockFactoryInterface $docBlockFactory = null)
    {
        $this->docBlockFactory = $docBlockFactory ?: DocBlockFactory::createInstance();
        $this->contextFactory = new ContextFactory();
    }

    /**
     * {@inheritdoc}
     */
    public function getShortDescription($class, $property, array $context = array())
    {
        /** @var $docBlock DocBlock */
        list($docBlock) = $this->getDocBlock($class, $property);
        if (!$docBlock) {
            return;
        }

        $shortDescription = $docBlock->getSummary();

        if (!empty($shortDescription)) {
            return $shortDescription;
        }

        foreach ($docBlock->getTagsByName('var') as $var) {
            $varDescription = $var->getDescription()->render();

            if (!empty($varDescription)) {
                return $varDescription;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getLongDescription($class, $property, array $context = array())
    {
        /** @var $docBlock DocBlock */
        list($docBlock) = $this->getDocBlock($class, $property);
        if (!$docBlock) {
            return;
        }

        $contents = $docBlock->getDescription()->render();

        return '' === $contents ? null : $contents;
    }

    /**
     * {@inheritdoc}
     */
    public function getTypes($class, $property, array $context = array())
    {
        /** @var $docBlock DocBlock */
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
        /** @var DocBlock\Tags\Var_|DocBlock\Tags\Return_|DocBlock\Tags\Param $tag */
        foreach ($docBlock->getTagsByName($tag) as $tag) {
            $varType = $tag->getType();
            $nullable = false;

            if (!$varType instanceof Compound) {
                if ($varType instanceof Null_) {
                    $nullable = true;
                }

                $type = $this->createType((string) $varType, $nullable);

                if (null !== $type) {
                    $types[] = $type;
                }

                continue;
            }

            $typeIndex = 0;
            $varTypes = array();
            while ($varType->has($typeIndex)) {
                $varTypes[] = (string) $varType->get($typeIndex);
                ++$typeIndex;
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

        try {
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
                    $data = array(null, null, null);
            }
        } catch (\InvalidArgumentException $e) {
            $data = array(null, null, null);
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
        } catch (\ReflectionException $e) {
            return;
        }

        return $this->docBlockFactory->create($reflectionProperty, $this->contextFactory->createFromReflector($reflectionProperty));
    }

    /**
     * Gets DocBlock from accessor or mutator method.
     *
     * @param string $class
     * @param string $ucFirstProperty
     * @param int    $type
     *
     * @return array
     */
    private function getDocBlockFromMethod($class, $ucFirstProperty, $type)
    {
        $prefixes = $type === self::ACCESSOR ? ReflectionExtractor::$accessorPrefixes : ReflectionExtractor::$mutatorPrefixes;
        $prefix = null;

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
            } catch (\ReflectionException $e) {
                // Try the next prefix if the method doesn't exist
            }
        }

        if (!isset($reflectionMethod)) {
            return;
        }

        return array($this->docBlockFactory->create($reflectionMethod, $this->contextFactory->createFromReflector($reflectionMethod)), $prefix);
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
