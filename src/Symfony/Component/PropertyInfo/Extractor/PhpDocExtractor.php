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
use phpDocumentor\Reflection\Types\ContextFactory;
use Symfony\Component\PropertyInfo\PropertyDescriptionExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\PropertyInfo\Util\PhpDocTypeHelper;

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

    /**
     * @var PhpDocTypeHelper
     */
    private $phpDocTypeHelper;

    public function __construct(DocBlockFactoryInterface $docBlockFactory = null)
    {
        $this->docBlockFactory = $docBlockFactory ?: DocBlockFactory::createInstance();
        $this->contextFactory = new ContextFactory();
        $this->phpDocTypeHelper = new PhpDocTypeHelper();
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
            $types = array_merge($types, $this->phpDocTypeHelper->getTypes($tag->getType()));
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
     * @return array|null
     */
    private function getDocBlockFromMethod($class, $ucFirstProperty, $type)
    {
        $prefixes = $type === self::ACCESSOR ? ReflectionExtractor::$accessorPrefixes : ReflectionExtractor::$mutatorPrefixes;
        $prefix = null;

        foreach ($prefixes as $prefix) {
            $methodName = $prefix.$ucFirstProperty;

            try {
                $reflectionMethod = new \ReflectionMethod($class, $methodName);
                if ($reflectionMethod->isStatic()) {
                    continue;
                }

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
}
