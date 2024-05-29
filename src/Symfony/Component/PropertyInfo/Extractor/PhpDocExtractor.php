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
use phpDocumentor\Reflection\DocBlock\Tags\InvalidTag;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use phpDocumentor\Reflection\Types\Context;
use phpDocumentor\Reflection\Types\ContextFactory;
use Symfony\Component\PropertyInfo\PropertyDescriptionExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyDocBlockExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\PropertyInfo\Util\PhpDocTypeHelper;
use Symfony\Component\TypeInfo\Exception\LogicException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;

/**
 * Extracts data using a PHPDoc parser.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @final
 */
class PhpDocExtractor implements PropertyDescriptionExtractorInterface, PropertyTypeExtractorInterface, ConstructorArgumentTypeExtractorInterface, PropertyDocBlockExtractorInterface
{
    public const PROPERTY = 0;
    public const ACCESSOR = 1;
    public const MUTATOR = 2;

    /**
     * @var array<string, array{DocBlock|null, int|null, string|null}>
     */
    private array $docBlocks = [];

    /**
     * @var Context[]
     */
    private array $contexts = [];

    private DocBlockFactoryInterface $docBlockFactory;
    private ContextFactory $contextFactory;
    private TypeContextFactory $typeContextFactory;
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
            throw new \LogicException(sprintf('Unable to use the "%s" class as the "phpdocumentor/reflection-docblock" package is not installed. Try running composer require "phpdocumentor/reflection-docblock".', __CLASS__));
        }

        $this->docBlockFactory = $docBlockFactory ?: DocBlockFactory::createInstance();
        $this->contextFactory = new ContextFactory();
        $this->typeContextFactory = new TypeContextFactory();
        $this->phpDocTypeHelper = new PhpDocTypeHelper();
        $this->mutatorPrefixes = $mutatorPrefixes ?? ReflectionExtractor::$defaultMutatorPrefixes;
        $this->accessorPrefixes = $accessorPrefixes ?? ReflectionExtractor::$defaultAccessorPrefixes;
        $this->arrayMutatorPrefixes = $arrayMutatorPrefixes ?? ReflectionExtractor::$defaultArrayMutatorPrefixes;
    }

    public function getShortDescription(string $class, string $property, array $context = []): ?string
    {
        /** @var $docBlock DocBlock */
        [$docBlock] = $this->findDocBlock($class, $property);
        if (!$docBlock) {
            return null;
        }

        $shortDescription = $docBlock->getSummary();

        if ($shortDescription) {
            return $shortDescription;
        }

        foreach ($docBlock->getTagsByName('var') as $var) {
            if ($var && !$var instanceof InvalidTag) {
                $varDescription = $var->getDescription()->render();

                if ($varDescription) {
                    return $varDescription;
                }
            }
        }

        return null;
    }

    public function getLongDescription(string $class, string $property, array $context = []): ?string
    {
        /** @var $docBlock DocBlock */
        [$docBlock] = $this->findDocBlock($class, $property);
        if (!$docBlock) {
            return null;
        }

        $contents = $docBlock->getDescription()->render();

        return '' === $contents ? null : $contents;
    }

    public function getTypes(string $class, string $property, array $context = []): ?array
    {
        /** @var $docBlock DocBlock */
        [$docBlock, $source, $prefix] = $this->findDocBlock($class, $property);
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
                        case 'static':
                            $resolvedClass = $class;
                            break;

                        case 'parent':
                            if (false !== $resolvedClass = $parentClass ??= get_parent_class($class)) {
                                break;
                            }
                            // no break

                        default:
                            $types[] = $type;
                            continue 2;
                    }

                    $types[] = new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, $type->isNullable(), $resolvedClass, $type->isCollection(), $type->getCollectionKeyTypes(), $type->getCollectionValueTypes());
                }
            }
        }

        if (!isset($types[0])) {
            return null;
        }

        if (!\in_array($prefix, $this->arrayMutatorPrefixes, true)) {
            return $types;
        }

        return [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), $types[0])];
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

    /**
     * @experimental
     */
    public function getType(string $class, string $property, array $context = []): ?Type
    {
        /** @var $docBlock DocBlock */
        [$docBlock, $source, $prefix] = $this->findDocBlock($class, $property);
        if (!$docBlock) {
            return null;
        }

        $tag = match ($source) {
            self::PROPERTY => 'var',
            self::ACCESSOR => 'return',
            self::MUTATOR => 'param',
        };

        $types = [];
        $typeContext = $this->typeContextFactory->createFromClassName($class);

        /** @var DocBlock\Tags\Var_|DocBlock\Tags\Return_|DocBlock\Tags\Param $tag */
        foreach ($docBlock->getTagsByName($tag) as $tag) {
            if ($tag instanceof InvalidTag || !$tagType = $tag->getType()) {
                continue;
            }

            $type = $this->phpDocTypeHelper->getType($tagType);

            if (!$type instanceof ObjectType) {
                $types[] = $type;

                continue;
            }

            $normalizedClassName = match ($type->getClassName()) {
                'self' => $typeContext->getDeclaringClass(),
                'static' => $typeContext->getCalledClass(),
                default => $type->getClassName(),
            };

            if ('parent' === $normalizedClassName) {
                try {
                    $normalizedClassName = $typeContext->getParentClass();
                } catch (LogicException) {
                    // if there is no parent for the current class, we keep the "parent" raw string
                }
            }

            $types[] = $type->isNullable() ? Type::nullable(Type::object($normalizedClassName)) : Type::object($normalizedClassName);
        }

        if (null === $type = $types[0] ?? null) {
            return null;
        }

        if (!\in_array($prefix, $this->arrayMutatorPrefixes, true)) {
            return $type;
        }

        return Type::list($type);
    }

    /**
     * @experimental
     */
    public function getTypeFromConstructor(string $class, string $property): ?Type
    {
        if (!$docBlock = $this->getDocBlockFromConstructor($class, $property)) {
            return null;
        }

        $types = [];
        /** @var DocBlock\Tags\Var_|DocBlock\Tags\Return_|DocBlock\Tags\Param $tag */
        foreach ($docBlock->getTagsByName('param') as $tag) {
            if ($tag instanceof InvalidTag || !$tagType = $tag->getType()) {
                continue;
            }

            $types[] = $this->phpDocTypeHelper->getType($tagType);
        }

        return $types[0] ?? null;
    }

    public function getDocBlock(string $class, string $property): ?DocBlock
    {
        $output = $this->findDocBlock($class, $property);

        return $output[0];
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
        $tags = array_values(array_filter($docBlock->getTagsByName('param'), fn ($tag) => $tag instanceof DocBlock\Tags\Param && $allowedParam === $tag->getVariableName()));

        return new DocBlock($docBlock->getSummary(), $docBlock->getDescription(), $tags, $docBlock->getContext(),
            $docBlock->getLocation(), $docBlock->isTemplateStart(), $docBlock->isTemplateEnd());
    }

    /**
     * @return array{DocBlock|null, int|null, string|null}
     */
    private function findDocBlock(string $class, string $property): array
    {
        $propertyHash = sprintf('%s::%s', $class, $property);

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
            case $reflectionProperty?->isPromoted() && $docBlock = $this->getDocBlockFromConstructor($class, $property):
                $data = [$docBlock, self::MUTATOR, null];
                break;

            case $docBlock = $this->getDocBlockFromProperty($class, $property):
                $data = [$docBlock, self::PROPERTY, null];
                break;

            case [$docBlock] = $this->getDocBlockFromMethod($class, $ucFirstProperty, self::ACCESSOR):
                $data = [$docBlock, self::ACCESSOR, null];
                break;

            case [$docBlock, $prefix] = $this->getDocBlockFromMethod($class, $ucFirstProperty, self::MUTATOR):
                $data = [$docBlock, self::MUTATOR, $prefix];
                break;

            default:
                $data = [null, null, null];
        }

        return $this->docBlocks[$propertyHash] = $data;
    }

    private function getDocBlockFromProperty(string $class, string $property): ?DocBlock
    {
        // Use a ReflectionProperty instead of $class to get the parent class if applicable
        try {
            $reflectionProperty = new \ReflectionProperty($class, $property);
        } catch (\ReflectionException) {
            return null;
        }

        $reflector = $reflectionProperty->getDeclaringClass();

        foreach ($reflector->getTraits() as $trait) {
            if ($trait->hasProperty($property)) {
                return $this->getDocBlockFromProperty($trait->getName(), $property);
            }
        }

        try {
            return $this->docBlockFactory->create($reflectionProperty, $this->createFromReflector($reflector));
        } catch (\InvalidArgumentException|\RuntimeException) {
            return null;
        }
    }

    /**
     * @return array{DocBlock, string}|null
     */
    private function getDocBlockFromMethod(string $class, string $ucFirstProperty, int $type): ?array
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
                    (self::ACCESSOR === $type && 0 === $reflectionMethod->getNumberOfRequiredParameters())
                    || (self::MUTATOR === $type && $reflectionMethod->getNumberOfParameters() >= 1)
                ) {
                    break;
                }
            } catch (\ReflectionException) {
                // Try the next prefix if the method doesn't exist
            }
        }

        if (!isset($reflectionMethod)) {
            return null;
        }

        $reflector = $reflectionMethod->getDeclaringClass();

        foreach ($reflector->getTraits() as $trait) {
            if ($trait->hasMethod($methodName)) {
                return $this->getDocBlockFromMethod($trait->getName(), $ucFirstProperty, $type);
            }
        }

        try {
            return [$this->docBlockFactory->create($reflectionMethod, $this->createFromReflector($reflector)), $prefix];
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
