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
use Symfony\Component\PropertyInfo\Util\PhpDocInheritance;
use Symfony\Component\PropertyInfo\Util\PhpDocTypeHelper;
use Symfony\Component\TypeInfo\Exception\LogicException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\CompositeTypeInterface;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;
use Symfony\Component\TypeInfo\TypeContext\TypeContext;
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
     * @var array<string, array{DocBlock|null, int|null, string|null, string|null, bool}>
     */
    private array $docBlocks = [];

    /**
     * @var array<string, array{DocBlock, string}|false>
     */
    private array $promotedPropertyDocBlocks = [];

    /**
     * @var array<string, array{DocBlock, string}|false>
     */
    private array $constructorDocBlocks = [];

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

        [$docBlock, $source, $prefix, $declaringClass, $inherited] = $this->findDocBlock($class, $property, true);
        if (!$docBlock) {
            return null;
        }

        return $this->getTypeFromDocBlock($docBlock, $source, $class, $declaringClass, $prefix, $inherited);
    }

    public function getTypeFromConstructor(string $class, string $property): ?Type
    {
        if ([$docBlock, $declaringClass, $inherited] = $this->getDocBlockFromConstructor($class, $property, true)) {
            if (null !== $type = $this->getTypeFromDocBlock($docBlock, self::MUTATOR, $class, $declaringClass, null, $inherited)) {
                return $type;
            }
        }

        // the doc block of a promoted property describes the constructor argument, the one of a plain property does not
        return $this->isPromotedProperty($class, $property) ? $this->getType($class, $property) : null;
    }

    public function getDocBlock(string $class, string $property): ?DocBlock
    {
        return $this->findDocBlock($class, $property)[0];
    }

    /**
     * @return array{DocBlock, string, bool}|null
     */
    private function getDocBlockFromConstructor(string $class, string $property, bool $inherit = false): ?array
    {
        if (!isset($this->constructorDocBlocks[$class])) {
            try {
                $reflectionConstructor = (new \ReflectionClass($class))->getConstructor();
            } catch (\ReflectionException) {
                return null;
            }

            try {
                $this->constructorDocBlocks[$class] = $reflectionConstructor ? [$this->docBlockFactory->create($reflectionConstructor, $this->createFromReflector(PhpDocInheritance::getDeclaringTrait($reflectionConstructor) ?? $reflectionConstructor->getDeclaringClass())), $reflectionConstructor->class] : false;
            } catch (\InvalidArgumentException) {
                $this->constructorDocBlocks[$class] = false;
            }
        }

        $data = null;
        if ($this->constructorDocBlocks[$class]) {
            [$docBlock, $declaringClass] = $this->constructorDocBlocks[$class];
            $data = [$this->filterDocBlockParams($docBlock, $property), $declaringClass, false];
        }

        if (!$inherit || ($data[0] ?? null)?->hasTag('param') || !$parameter = PhpDocInheritance::getConstructorParameter((new \ReflectionClass($class))->getConstructor(), $property)) {
            return $data;
        }

        if ($inheritedDocBlock = $this->getInheritedDocBlock($parameter)) {
            return [$inheritedDocBlock[0], $inheritedDocBlock[2], true];
        }

        return $data;
    }

    private function filterDocBlockParams(DocBlock $docBlock, string $allowedParam): DocBlock
    {
        $tags = array_values(array_filter($docBlock->getTagsByName('param'), static fn ($tag) => $tag instanceof DocBlock\Tags\Param && $allowedParam === $tag->getVariableName()));

        return new DocBlock($docBlock->getSummary(), $docBlock->getDescription(), $tags, $docBlock->getContext(),
            $docBlock->getLocation(), $docBlock->isTemplateStart(), $docBlock->isTemplateEnd());
    }

    /**
     * @param bool $inherit Whether a member without a type tag of its own inherits the docblock of the member that it overrides
     *
     * @return array{DocBlock|null, int|null, string|null, string|null, bool}
     */
    private function findDocBlock(string $class, string $property, bool $inherit = false): array
    {
        $propertyHash = \sprintf('%s::%s', $class, $property).($inherit ? ':inherit' : '');

        if (isset($this->docBlocks[$propertyHash])) {
            return $this->docBlocks[$propertyHash];
        }

        try {
            $reflectionProperty = new \ReflectionProperty($class, $property);
        } catch (\ReflectionException) {
            $reflectionProperty = null;
        }

        $ucFirstProperty = ucfirst($property);

        if ($reflectionProperty?->isPromoted() && [$docBlock, $declaringClass, $inherited] = $this->getDocBlockFromConstructor($reflectionProperty->class, $property, $inherit)) {
            $data = [$docBlock, self::MUTATOR, null, $declaringClass, $inherited];
        } elseif ($inherit && $reflectionProperty && !$reflectionProperty->isPromoted() && !str_contains((string) $reflectionProperty->getDocComment(), '@var') && [$docBlock, $source, $declaringClass] = $this->getInheritedDocBlock($reflectionProperty)) {
            $data = [$docBlock, $source, null, $declaringClass, true];
        } elseif ([$docBlock, $declaringClass] = $this->getDocBlockFromProperty($class, $property)) {
            $data = [$docBlock, self::PROPERTY, null, $declaringClass, false];
        } else {
            $data = $this->getDocBlockFromMethod($class, $ucFirstProperty, self::ACCESSOR, $inherit)
                ?? $this->getDocBlockFromMethod($class, $ucFirstProperty, self::MUTATOR, $inherit)
                ?? [null, null, null, null, false];
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
            // a class can declare a property of its traits again, with another doc comment
            if ($trait->hasProperty($property) && $trait->getProperty($property)->getDocComment() === $reflectionProperty->getDocComment()) {
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
     * @return array{DocBlock, int, ?string, string, bool}|null
     */
    private function getDocBlockFromMethod(string $class, string $ucFirstProperty, int $type, bool $inherit = false): ?array
    {
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

        $declaringClass = $method->class;
        $prefix = self::ACCESSOR === $type ? null : $prefix;

        if (!str_contains((string) $method->getDocComment(), self::ACCESSOR === $type ? '@return' : '@param')) {
            if ($inherit) {
                if ($inheritedDocBlock = $this->getInheritedDocBlock(self::ACCESSOR === $type ? $method : $method->getParameters()[0])) {
                    return [$inheritedDocBlock[0], $type, $prefix, $inheritedDocBlock[2], true];
                }
            } else {
                // a method that implements an abstract trait method uses the docblock of the trait when its own docblock has no tag for the type, for example when PHP gives it the docblock of a "use" statement
                foreach ($method->getDeclaringClass()->getTraits() as $trait) {
                    if ($trait->hasMethod($method->name) && $trait->getMethod($method->name)->isAbstract()) {
                        $method = $trait->getMethod($method->name);
                        break;
                    }
                }
            }
        }

        $context = $this->createFromReflector(PhpDocInheritance::getDeclaringTrait($method) ?? $method->getDeclaringClass());

        try {
            return [$this->docBlockFactory->create($method, $context), $type, $prefix, $declaringClass, false];
        } catch (\InvalidArgumentException) {
            return null;
        } catch (\RuntimeException) {
            // Workaround for phpdocumentor/reflection-docblock < 6 not supporting ?Type<...> syntax
            if (($rawDoc = $method->getDocComment()) && $docBlock = $this->getNullableGenericDocBlock($rawDoc, $context)) {
                return [$docBlock, $type, $prefix, $declaringClass, false];
            }

            return null;
        }
    }

    /**
     * Returns the docblock that a member without a type tag of its own inherits, with the tag to read and the class that "self" refers to in it.
     *
     * @return array{DocBlock, int, string}|null
     */
    private function getInheritedDocBlock(\ReflectionMethod|\ReflectionParameter|\ReflectionProperty $member): ?array
    {
        foreach (PhpDocInheritance::getOverriddenMembers($member) as [$overriddenMember, $docClass, $selfClass]) {
            try {
                $docBlock = $this->docBlockFactory->create($overriddenMember instanceof \ReflectionParameter ? $overriddenMember->getDeclaringFunction() : $overriddenMember, $this->createFromReflector($docClass));
            } catch (\InvalidArgumentException|\RuntimeException) {
                continue;
            }

            [$tag, $source] = match (true) {
                $overriddenMember instanceof \ReflectionMethod => ['return', self::ACCESSOR],
                $overriddenMember instanceof \ReflectionParameter => ['param', self::MUTATOR],
                default => ['var', self::PROPERTY],
            };

            if ($overriddenMember instanceof \ReflectionParameter) {
                $docBlock = $this->filterDocBlockParams($docBlock, $overriddenMember->name);
            }

            if ($docBlock->hasTag($tag)) {
                return PhpDocInheritance::hasSameNativeType($member, $overriddenMember, $selfClass) ? [$docBlock, $source, $selfClass] : null;
            }
        }

        return null;
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

    private function resolveClassKeywords(Type $type, TypeContext $typeContext): Type
    {
        if ($type instanceof NullableType || $type instanceof CollectionType) {
            $wrappedType = $this->resolveClassKeywords($type->getWrappedType(), $typeContext);

            if ($wrappedType === $type->getWrappedType()) {
                return $type;
            }

            return $type instanceof NullableType ? Type::nullable($wrappedType) : new CollectionType($wrappedType, $type->isList());
        }

        if ($type instanceof GenericType) {
            $wrappedType = $this->resolveClassKeywords($type->getWrappedType(), $typeContext);
            $variableTypes = array_map(fn (Type $variableType): Type => $this->resolveClassKeywords($variableType, $typeContext), $type->getVariableTypes());

            if ($wrappedType === $type->getWrappedType() && $variableTypes === $type->getVariableTypes()) {
                return $type;
            }

            return new GenericType($wrappedType, ...$variableTypes);
        }

        if ($type instanceof UnionType) {
            $types = array_map(fn (Type $unionType): Type => $this->resolveClassKeywords($unionType, $typeContext), $type->getTypes());

            if ($types === $type->getTypes()) {
                return $type;
            }

            $types = array_values(array_unique($types));

            return 1 === \count($types) ? $types[0] : Type::union(...$types);
        }

        if (!$type instanceof ObjectType) {
            return $type;
        }

        try {
            return match ($type->getClassName()) {
                'self' => Type::object($typeContext->getDeclaringClass()),
                'static', '$this' => Type::object($typeContext->getCalledClass()),
                'parent' => Type::object($typeContext->getParentClass()),
                default => $type,
            };
        } catch (LogicException) {
            // if there is no parent for the current class, we keep the "parent" raw string
            return $type;
        }
    }

    private function getTypeFromDocBlock(DocBlock $docBlock, int $source, string $class, ?string $declaringClass, ?string $prefix, bool $inherited = false): ?Type
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
            $types[] = $type ? $this->resolveClassKeywords($type, $typeContext) : null;
        }

        if (!$type = $types[0] ?? null) {
            return null;
        }

        // templates and type aliases are read as class names, so an inherited type that names a class that doesn't exist is ignored
        if ($inherited && self::hasUnknownClass($type)) {
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

    private function isPromotedProperty(string $class, string $property): bool
    {
        try {
            return (new \ReflectionProperty($class, $property))->isPromoted();
        } catch (\ReflectionException) {
            return false;
        }
    }

    private static function hasUnknownClass(Type $type): bool
    {
        return match (true) {
            $type instanceof ObjectType => !class_exists($type->getClassName()) && !interface_exists($type->getClassName()),
            $type instanceof GenericType => self::hasUnknownClass($type->getWrappedType()) || array_any($type->getVariableTypes(), self::hasUnknownClass(...)),
            $type instanceof WrappingTypeInterface => self::hasUnknownClass($type->getWrappedType()),
            $type instanceof CompositeTypeInterface => array_any($type->getTypes(), self::hasUnknownClass(...)),
            default => false,
        };
    }
}
