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
use phpDocumentor\Reflection\DocBlock\Tags\Factory\StaticMethod;
use phpDocumentor\Reflection\DocBlock\Tags\Generic;
use phpDocumentor\Reflection\DocBlock\Tags\InvalidTag;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use phpDocumentor\Reflection\Types\Context;
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
 * @final
 */
class PhpDocExtractor implements PropertyDescriptionExtractorInterface, PropertyTypeExtractorInterface, ConstructorArgumentTypeExtractorInterface
{
    public const PROPERTY = 0;
    public const ACCESSOR = 1;
    public const MUTATOR = 2;

    /**
     * @var array<string, array{DocBlock|null, int|null, string|null, string|null}>
     */
    private array $docBlocks = [];

    /**
     * @var Context[]
     */
    private array $contexts = [];

    private DocBlockFactoryInterface $docBlockFactory;
    private ContextFactory $contextFactory;
    private PhpDocTypeHelper $phpDocTypeHelper;
    private array $mutatorPrefixes;
    private array $accessorPrefixes;
    private array $arrayMutatorPrefixes;

    /**
     * @param string[]|null $mutatorPrefixes
     * @param string[]|null $accessorPrefixes
     * @param string[]|null $arrayMutatorPrefixes
     */
    public function __construct(?DocBlockFactoryInterface $docBlockFactory = null, ?array $mutatorPrefixes = null, ?array $accessorPrefixes = null, ?array $arrayMutatorPrefixes = null)
    {
        if (!class_exists(DocBlockFactory::class)) {
            throw new \LogicException(\sprintf('Unable to use the "%s" class as the "phpdocumentor/reflection-docblock" package is not installed. Try running composer require "phpdocumentor/reflection-docblock".', __CLASS__));
        }

        if (!is_subclass_of(Generic::class, StaticMethod::class)) {
            throw new \LogicException('symfony/property-info v6 does not support phpdocumentor/reflection-docblock v6. Please stick to ^5.2 in your composer.json file.');
        }

        $this->docBlockFactory = $docBlockFactory ?: DocBlockFactory::createInstance();
        $this->contextFactory = new ContextFactory();
        $this->phpDocTypeHelper = new PhpDocTypeHelper();
        $this->mutatorPrefixes = $mutatorPrefixes ?? ReflectionExtractor::$defaultMutatorPrefixes;
        $this->accessorPrefixes = $accessorPrefixes ?? ReflectionExtractor::$defaultAccessorPrefixes;
        $this->arrayMutatorPrefixes = $arrayMutatorPrefixes ?? ReflectionExtractor::$defaultArrayMutatorPrefixes;
    }

    public function getShortDescription(string $class, string $property, array $context = []): ?string
    {
        /** @var DocBlock $docBlock */
        [$docBlock] = $this->getDocBlock($class, $property);
        if (!$docBlock) {
            return null;
        }

        $shortDescription = $docBlock->getSummary();

        if (!empty($shortDescription)) {
            return $shortDescription;
        }

        foreach ($docBlock->getTagsByName('var') as $var) {
            if ($var && !$var instanceof InvalidTag) {
                $varDescription = $var->getDescription()->render();

                if (!empty($varDescription)) {
                    return $varDescription;
                }
            }
        }

        return null;
    }

    public function getLongDescription(string $class, string $property, array $context = []): ?string
    {
        /** @var DocBlock $docBlock */
        [$docBlock] = $this->getDocBlock($class, $property);
        if (!$docBlock) {
            return null;
        }

        $contents = $docBlock->getDescription()->render();

        return '' === $contents ? null : $contents;
    }

    public function getTypes(string $class, string $property, array $context = []): ?array
    {
        /** @var DocBlock $docBlock */
        [$docBlock, $source, $prefix, $declaringClass] = $this->getDocBlock($class, $property);
        if (!$docBlock) {
            return null;
        }

        $tag = match ($source) {
            self::PROPERTY => 'var',
            self::ACCESSOR => 'return',
            self::MUTATOR => 'param',
        };

        $parentClass = null;
        $types = [];
        /** @var DocBlock\Tags\Var_|DocBlock\Tags\Return_|DocBlock\Tags\Param $tag */
        foreach ($docBlock->getTagsByName($tag) as $tag) {
            if ($tag && !$tag instanceof InvalidTag && null !== $tag->getType()) {
                foreach ($this->phpDocTypeHelper->getTypes($tag->getType()) as $type) {
                    switch ($type->getClassName()) {
                        case 'self':
                            $resolvedClass = $declaringClass ?? $class;
                            break;

                        case 'static':
                            $resolvedClass = $class;
                            break;

                        case 'parent':
                            if (false !== $resolvedClass = $parentClass ??= get_parent_class($declaringClass ?? $class)) {
                                break;
                            }
                            // no break

                        default:
                            $types[] = $type;
                            continue 2;
                    }

                    $types[] = new Type(Type::BUILTIN_TYPE_OBJECT, $type->isNullable(), $resolvedClass, $type->isCollection(), $type->getCollectionKeyTypes(), $type->getCollectionValueTypes());
                }
            }
        }

        if (!isset($types[0])) {
            return null;
        }

        if (!\in_array($prefix, $this->arrayMutatorPrefixes)) {
            return $types;
        }

        return [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), $types[0])];
    }

    public function getTypesFromConstructor(string $class, string $property): ?array
    {
        $docBlock = $this->getDocBlockFromConstructor($class, $property);

        if (!$docBlock) {
            return null;
        }

        $types = [];
        /** @var DocBlock\Tags\Var_|DocBlock\Tags\Return_|DocBlock\Tags\Param $tag */
        foreach ($docBlock->getTagsByName('param') as $tag) {
            if ($tag && null !== $tag->getType()) {
                $types[] = $this->phpDocTypeHelper->getTypes($tag->getType());
            }
        }

        if (!isset($types[0]) || [] === $types[0]) {
            return null;
        }

        return array_merge([], ...$types);
    }

    private function getDocBlockFromConstructor(string $class, string $property): ?DocBlock
    {
        try {
            $reflectionClass = new \ReflectionClass($class);
        } catch (\ReflectionException) {
            return null;
        }
        $reflectionConstructor = $reflectionClass->getConstructor();
        if (!$reflectionConstructor) {
            return null;
        }

        try {
            $docBlock = $this->docBlockFactory->create($reflectionConstructor, $this->contextFactory->createFromReflector($reflectionConstructor));

            return $this->filterDocBlockParams($docBlock, $property);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function filterDocBlockParams(DocBlock $docBlock, string $allowedParam): DocBlock
    {
        $tags = array_values(array_filter($docBlock->getTagsByName('param'), static fn ($tag) => $tag instanceof DocBlock\Tags\Param && $allowedParam === $tag->getVariableName()));

        return new DocBlock($docBlock->getSummary(), $docBlock->getDescription(), $tags, $docBlock->getContext(),
            $docBlock->getLocation(), $docBlock->isTemplateStart(), $docBlock->isTemplateEnd());
    }

    /**
     * @return array{DocBlock|null, int|null, string|null, string|null}
     */
    private function getDocBlock(string $class, string $property): array
    {
        $propertyHash = \sprintf('%s::%s', $class, $property);

        if (isset($this->docBlocks[$propertyHash])) {
            return $this->docBlocks[$propertyHash];
        }

        try {
            $reflectionProperty = new \ReflectionProperty($class, $property);
        } catch (\ReflectionException) {
            $reflectionProperty = null;
        }

        $ucFirstProperty = ucfirst($property);

        switch (true) {
            case $reflectionProperty?->isPromoted() && $docBlock = $this->getDocBlockFromConstructor($reflectionProperty->class, $property):
                $data = [$docBlock, self::MUTATOR, null, $reflectionProperty->class];
                break;

            case [$docBlock, $declaringClass] = $this->getDocBlockFromProperty($class, $property):
                $data = [$docBlock, self::PROPERTY, null, $declaringClass];
                break;

            case [$docBlock, , $declaringClass] = $this->getDocBlockFromMethod($class, $ucFirstProperty, self::ACCESSOR):
                $data = [$docBlock, self::ACCESSOR, null, $declaringClass];
                break;

            case [$docBlock, $prefix, $declaringClass] = $this->getDocBlockFromMethod($class, $ucFirstProperty, self::MUTATOR):
                $data = [$docBlock, self::MUTATOR, $prefix, $declaringClass];
                break;

            default:
                $data = [null, null, null, null];
        }

        return $this->docBlocks[$propertyHash] = $data;
    }

    /**
     * @return array{DocBlock, string}|null
     */
    private function getDocBlockFromProperty(string $class, string $property, ?string $originalClass = null): ?array
    {
        $originalClass ??= $class;

        // Use a ReflectionProperty instead of $class to get the parent class if applicable
        try {
            $reflectionProperty = new \ReflectionProperty($class, $property);
        } catch (\ReflectionException) {
            return null;
        }

        $reflector = $reflectionProperty->getDeclaringClass();

        foreach ($reflector->getTraits() as $trait) {
            if ($trait->hasProperty($property)) {
                return $this->getDocBlockFromProperty($trait->getName(), $property, $reflector->isTrait() ? $originalClass : $reflector->getName());
            }
        }

        try {
            $declaringClass = $reflector->isTrait() ? $originalClass : $reflector->getName();

            return [$this->docBlockFactory->create($reflectionProperty, $this->createFromReflector($reflector)), $declaringClass];
        } catch (\InvalidArgumentException|\RuntimeException) {
            return null;
        }
    }

    /**
     * @return array{DocBlock, string, string}|null
     */
    private function getDocBlockFromMethod(string $class, string $ucFirstProperty, int $type, ?string $originalClass = null): ?array
    {
        $originalClass ??= $class;
        $prefixes = self::ACCESSOR === $type ? $this->accessorPrefixes : $this->mutatorPrefixes;
        $prefix = null;
        $method = null;

        foreach ($prefixes as $prefix) {
            $methodName = $prefix.$ucFirstProperty;

            try {
                $method = new \ReflectionMethod($class, $methodName);
                if ($method->isStatic()) {
                    $method = null;

                    continue;
                }

                if (self::ACCESSOR === $type && \in_array((string) $method->getReturnType(), ['void', 'never'], true)) {
                    $method = null;

                    continue;
                }

                if (
                    (self::ACCESSOR === $type && !$method->getNumberOfRequiredParameters())
                    || (self::MUTATOR === $type && $method->getNumberOfParameters() >= 1 && $method->getNumberOfRequiredParameters() <= 1)
                ) {
                    break;
                }

                $method = null;
            } catch (\ReflectionException) {
                // Try the next prefix if the method doesn't exist
            }
        }

        if (!$method) {
            return null;
        }

        $reflector = $method->getDeclaringClass();

        foreach ($reflector->getTraits() as $trait) {
            if ($trait->hasMethod($methodName)) {
                return $this->getDocBlockFromMethod($trait->getName(), $ucFirstProperty, $type, $reflector->isTrait() ? $originalClass : $reflector->getName());
            }
        }

        try {
            $declaringClass = $reflector->isTrait() ? $originalClass : $reflector->getName();

            return [$this->docBlockFactory->create($method, $this->createFromReflector($reflector)), $prefix, $declaringClass];
        } catch (\InvalidArgumentException|\RuntimeException) {
            return null;
        }
    }

    /**
     * Prevents a lot of redundant calls to ContextFactory::createForNamespace().
     */
    private function createFromReflector(\ReflectionClass $reflector): Context
    {
        $cacheKey = $reflector->getNamespaceName().':'.$reflector->getFileName();

        if (isset($this->contexts[$cacheKey])) {
            return $this->contexts[$cacheKey];
        }

        $this->contexts[$cacheKey] = $this->contextFactory->createFromReflector($reflector);

        return $this->contexts[$cacheKey];
    }
}
