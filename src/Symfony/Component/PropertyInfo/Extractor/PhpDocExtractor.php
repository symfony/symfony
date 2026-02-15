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
     * @var array<string, array{DocBlock|null, int|null, string|null, string|null}>
     */
    private array $docBlocks = [];

    /**
     * @var array<string, array{DocBlock, string}|false>
     */
    private array $promotedPropertyDocBlocks = [];

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
            throw new \LogicException(\sprintf('Unable to use the "%s" class as the "phpdocumentor/reflection-docblock" package is not installed. Try running composer require "phpdocumentor/reflection-docblock".', __CLASS__));
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
        $docBlockData = $this->getPromotedPropertyDocBlockData($class, $property);
        if ($docBlockData && $shortDescription = $this->getShortDescriptionFromDocBlock($docBlockData[0])) {
            return $shortDescription;
        }

        [$docBlock] = $this->findDocBlock($class, $property);
        if (!$docBlock) {
            return null;
        }

        return $this->getShortDescriptionFromDocBlock($docBlock);
    }

    public function getLongDescription(string $class, string $property, array $context = []): ?string
    {
        $docBlockData = $this->getPromotedPropertyDocBlockData($class, $property);
        if ($docBlockData && '' !== $contents = $docBlockData[0]->getDescription()->render()) {
            return $contents;
        }

        [$docBlock] = $this->findDocBlock($class, $property);
        if (!$docBlock) {
            return null;
        }

        $contents = $docBlock->getDescription()->render();

        return '' === $contents ? null : $contents;
    }

    public function getType(string $class, string $property, array $context = []): ?Type
    {
        if ([$propertyDocBlock, $propertyDeclaringClass] = $this->getPromotedPropertyDocBlockData($class, $property)) {
            if ($type = $this->getTypeFromDocBlock($propertyDocBlock, self::PROPERTY, $class, $propertyDeclaringClass, null)) {
                return $type;
            }
        }

        [$docBlock, $source, $prefix, $declaringClass] = $this->findDocBlock($class, $property);
        if (!$docBlock) {
            return null;
        }

        return $this->getTypeFromDocBlock($docBlock, $source, $class, $declaringClass, $prefix);
    }

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
        return $this->findDocBlock($class, $property)[0];
    }

    private function getDocBlockFromConstructor(string $class, string $property): ?DocBlock
    {
        try {
            $reflectionClass = new \ReflectionClass($class);
        } catch (\ReflectionException) {
            return null;
        }
        if (!$reflectionConstructor = $reflectionClass->getConstructor()) {
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
    private function findDocBlock(string $class, string $property): array
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

        if ($reflectionProperty?->isPromoted() && $docBlock = $this->getDocBlockFromConstructor($reflectionProperty->class, $property)) {
            $data = [$docBlock, self::MUTATOR, null, $reflectionProperty->class];
        } elseif ([$docBlock, $declaringClass] = $this->getDocBlockFromProperty($class, $property)) {
            $data = [$docBlock, self::PROPERTY, null, $declaringClass];
        } else {
            $data = $this->getDocBlockFromMethod($class, $ucFirstProperty, self::ACCESSOR)
                ?? $this->getDocBlockFromMethod($class, $ucFirstProperty, self::MUTATOR)
                ?? [null, null, null, null];
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

        $context = $this->createFromReflector($reflector);

        try {
            $declaringClass = $reflector->isTrait() ? $originalClass : $reflector->getName();

            return [$this->docBlockFactory->create($reflectionProperty, $context), $declaringClass];
        } catch (\InvalidArgumentException) {
            return null;
        } catch (\RuntimeException) {
            // Workaround for phpdocumentor/reflection-docblock < 6 not supporting ?Type<...> syntax
            if (($rawDoc = $reflectionProperty->getDocComment()) && $docBlock = $this->getNullableGenericDocBlock($rawDoc, $context)) {
                return [$docBlock, $declaringClass ?? ($reflector->isTrait() ? $originalClass : $reflector->getName())];
            }

            return null;
        }
    }

    /**
     * @return array{DocBlock, int, ?string, string}|null
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
                    continue;
                }

                if (self::ACCESSOR === $type && \in_array((string) $method->getReturnType(), ['void', 'never'], true)) {
                    continue;
                }

                if (
                    (self::ACCESSOR === $type && !$method->getNumberOfRequiredParameters())
                    || (self::MUTATOR === $type && $method->getNumberOfParameters() >= 1)
                ) {
                    break;
                }
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

        $context = $this->createFromReflector($reflector);
        $prefix = self::ACCESSOR === $type ? null : $prefix;

        try {
            $declaringClass = $reflector->isTrait() ? $originalClass : $reflector->getName();

            return [$this->docBlockFactory->create($method, $context), $type, $prefix, $declaringClass];
        } catch (\InvalidArgumentException) {
            return null;
        } catch (\RuntimeException) {
            // Workaround for phpdocumentor/reflection-docblock < 6 not supporting ?Type<...> syntax
            if (($rawDoc = $method->getDocComment()) && $docBlock = $this->getNullableGenericDocBlock($rawDoc, $context)) {
                return [$docBlock, $type, $prefix, $declaringClass ?? ($reflector->isTrait() ? $originalClass : $reflector->getName())];
            }

            return null;
        }
    }

    private function getNullableGenericDocBlock(string $rawDoc, Context $context): ?DocBlock
    {
        // Converts "?Type<...>" to "Type<...>|null"
        if ($rawDoc === $processedDoc = preg_replace('/@(var|param|return)\s+\?(\S+)/', '@$1 $2|null', $rawDoc)) {
            return null;
        }

        try {
            return $this->docBlockFactory->create($processedDoc, $context);
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

        return $this->contexts[$cacheKey] ??= $this->contextFactory->createFromReflector($reflector);
    }

    /**
     * @return array{DocBlock, string}|null
     */
    private function getPromotedPropertyDocBlockData(string $class, string $property): ?array
    {
        $propertyHash = $class.'::'.$property;

        if (isset($this->promotedPropertyDocBlocks[$propertyHash])) {
            return false === $this->promotedPropertyDocBlocks[$propertyHash] ? null : $this->promotedPropertyDocBlocks[$propertyHash];
        }

        try {
            $reflectionProperty = new \ReflectionProperty($class, $property);
        } catch (\ReflectionException) {
            $this->promotedPropertyDocBlocks[$propertyHash] = false;

            return null;
        }

        if (!$reflectionProperty->isPromoted() || !$data = $this->getDocBlockFromProperty($class, $property)) {
            $this->promotedPropertyDocBlocks[$propertyHash] = false;

            return null;
        }

        return $this->promotedPropertyDocBlocks[$propertyHash] = $data;
    }

    private function getTypeFromDocBlock(DocBlock $docBlock, int $source, string $class, ?string $declaringClass, ?string $prefix): ?Type
    {
        $tag = match ($source) {
            self::PROPERTY => 'var',
            self::ACCESSOR => 'return',
            self::MUTATOR => 'param',
        };

        $types = [];
        $typeContext = $this->typeContextFactory->createFromClassName($class, $declaringClass ?? $class);

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

        if (!$type = $types[0] ?? null) {
            return null;
        }

        if (self::MUTATOR !== $source || !\in_array($prefix, $this->arrayMutatorPrefixes, true)) {
            return $type;
        }

        return Type::list($type);
    }

    private function getShortDescriptionFromDocBlock(DocBlock $docBlock): ?string
    {
        if ($shortDescription = $docBlock->getSummary()) {
            return $shortDescription;
        }

        foreach ($docBlock->getTagsByName('var') as $var) {
            if ($var && !$var instanceof InvalidTag && $varDescription = $var->getDescription()->render()) {
                return $varDescription;
            }
        }

        foreach ($docBlock->getTagsByName('param') as $param) {
            if (!$param instanceof DocBlock\Tags\Param) {
                continue;
            }

            if ($paramDescription = $param->getDescription()?->render()) {
                return $paramDescription;
            }
        }

        return null;
    }
}
