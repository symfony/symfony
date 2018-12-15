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
 *
 * @final since version 3.3
 */
class PhpDocExtractor implements PropertyDescriptionExtractorInterface, PropertyTypeExtractorInterface
{
    const PROPERTY = 0;
    const ACCESSOR = 1;
    const MUTATOR = 2;

    /**
     * @var DocBlock[]
     */
    private $docBlocks = [];

    private $docBlockFactory;
    private $contextFactory;
    private $phpDocTypeHelper;
    private $mutatorPrefixes;
    private $accessorPrefixes;
    private $arrayMutatorPrefixes;

    /**
     * @param DocBlockFactoryInterface $docBlockFactory
     * @param string[]|null            $mutatorPrefixes
     * @param string[]|null            $accessorPrefixes
     * @param string[]|null            $arrayMutatorPrefixes
     */
    public function __construct(DocBlockFactoryInterface $docBlockFactory = null, array $mutatorPrefixes = null, array $accessorPrefixes = null, array $arrayMutatorPrefixes = null)
    {
        if (!class_exists(DocBlockFactory::class)) {
            throw new \RuntimeException(sprintf('Unable to use the "%s" class as the "phpdocumentor/reflection-docblock" package is not installed.', __CLASS__));
        }

        $this->docBlockFactory = $docBlockFactory ?: DocBlockFactory::createInstance();
        $this->contextFactory = new ContextFactory();
        $this->phpDocTypeHelper = new PhpDocTypeHelper();
        $this->mutatorPrefixes = null !== $mutatorPrefixes ? $mutatorPrefixes : ReflectionExtractor::$defaultMutatorPrefixes;
        $this->accessorPrefixes = null !== $accessorPrefixes ? $accessorPrefixes : ReflectionExtractor::$defaultAccessorPrefixes;
        $this->arrayMutatorPrefixes = null !== $arrayMutatorPrefixes ? $arrayMutatorPrefixes : ReflectionExtractor::$defaultArrayMutatorPrefixes;
    }

    /**
     * {@inheritdoc}
     */
    public function getShortDescription($class, $property, array $context = [])
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
    public function getLongDescription($class, $property, array $context = [])
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
    public function getTypes($class, $property, array $context = [])
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

        $types = [];
        /** @var DocBlock\Tags\Var_|DocBlock\Tags\Return_|DocBlock\Tags\Param $tag */
        foreach ($docBlock->getTagsByName($tag) as $tag) {
            if ($tag && null !== $tag->getType()) {
                $types = array_merge($types, $this->phpDocTypeHelper->getTypes($tag->getType()));
            }
        }

        if (!isset($types[0])) {
            return;
        }

        if (!\in_array($prefix, $this->arrayMutatorPrefixes)) {
            return $types;
        }

        return [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), $types[0])];
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
                $data = [$docBlock, self::PROPERTY, null];
                break;

            case list($docBlock) = $this->getDocBlockFromMethod($class, $ucFirstProperty, self::ACCESSOR):
                $data = [$docBlock, self::ACCESSOR, null];
                break;

            case list($docBlock, $prefix) = $this->getDocBlockFromMethod($class, $ucFirstProperty, self::MUTATOR):
                $data = [$docBlock, self::MUTATOR, $prefix];
                break;

            default:
                $data = [null, null, null];
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

        try {
            return $this->docBlockFactory->create($reflectionProperty, $this->contextFactory->createFromReflector($reflectionProperty->getDeclaringClass()));
        } catch (\InvalidArgumentException $e) {
            return null;
        }
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
        $prefixes = self::ACCESSOR === $type ? $this->accessorPrefixes : $this->mutatorPrefixes;
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

        try {
            return [$this->docBlockFactory->create($reflectionMethod, $this->contextFactory->createFromReflector($reflectionMethod)), $prefix];
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }
}
