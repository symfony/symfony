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

use PHPStan\PhpDocParser\Ast\PhpDoc\InvalidTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use Symfony\Component\PropertyInfo\PhpStan\NameScopeFactory;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\PropertyInfo\Util\PhpStanTypeHelper;

/**
 * Extracts data using PHPStan parser.
 *
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
final class PhpStanExtractor implements PropertyTypeExtractorInterface, ConstructorArgumentTypeExtractorInterface
{
    private const PROPERTY = 0;
    private const ACCESSOR = 1;
    private const MUTATOR = 2;

    /** @var PhpDocParser */
    private $phpDocParser;

    /** @var Lexer */
    private $lexer;

    /** @var NameScopeFactory */
    private $nameScopeFactory;

    /** @var array<string, array{PhpDocNode|null, int|null, string|null, string|null}> */
    private $docBlocks = [];
    private $phpStanTypeHelper;
    private $mutatorPrefixes;
    private $accessorPrefixes;
    private $arrayMutatorPrefixes;

    /**
     * @param list<string>|null $mutatorPrefixes
     * @param list<string>|null $accessorPrefixes
     * @param list<string>|null $arrayMutatorPrefixes
     */
    public function __construct(array $mutatorPrefixes = null, array $accessorPrefixes = null, array $arrayMutatorPrefixes = null)
    {
        $this->phpStanTypeHelper = new PhpStanTypeHelper();
        $this->mutatorPrefixes = $mutatorPrefixes ?? ReflectionExtractor::$defaultMutatorPrefixes;
        $this->accessorPrefixes = $accessorPrefixes ?? ReflectionExtractor::$defaultAccessorPrefixes;
        $this->arrayMutatorPrefixes = $arrayMutatorPrefixes ?? ReflectionExtractor::$defaultArrayMutatorPrefixes;

        $this->phpDocParser = new PhpDocParser(new TypeParser(new ConstExprParser()), new ConstExprParser());
        $this->lexer = new Lexer();
        $this->nameScopeFactory = new NameScopeFactory();
    }

    public function getTypes(string $class, string $property, array $context = []): ?array
    {
        /** @var PhpDocNode|null $docNode */
        [$docNode, $source, $prefix, $declaringClass] = $this->getDocBlock($class, $property, $context['normalization_outer_class_property'] ?? null);
        $nameScope = $this->nameScopeFactory->create($class, $declaringClass);
        if (null === $docNode) {
            return null;
        }

        switch ($source) {
            case self::PROPERTY:
                $tag = '@var';
                break;

            case self::ACCESSOR:
                $tag = '@return';
                break;

            case self::MUTATOR:
                $tag = '@param';
                break;
        }

        $parentClass = null;
        $types = [];
        foreach ($docNode->getTagsByName($tag) as $tagDocNode) {
            if ($tagDocNode->value instanceof InvalidTagValueNode) {
                continue;
            }

            if (
                $tagDocNode->value instanceof ParamTagValueNode
                && null === $prefix
                && $tagDocNode->value->parameterName !== '$'.$property
            ) {
                continue;
            }

            foreach ($this->phpStanTypeHelper->getTypes($tagDocNode->value, $nameScope) as $type) {
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

                $types[] = new Type(Type::BUILTIN_TYPE_OBJECT, $type->isNullable(), $resolvedClass, $type->isCollection(), $type->getCollectionKeyTypes(), $type->getCollectionValueTypes());
            }
        }

        if (!isset($types[0])) {
            return null;
        }

        if (!\in_array($prefix, $this->arrayMutatorPrefixes, true)) {
            return $types;
        }

        return [new Type(Type::BUILTIN_TYPE_ARRAY, false, null, true, new Type(Type::BUILTIN_TYPE_INT), $types[0])];
    }

    public function getTypesFromConstructor(string $class, string $property): ?array
    {
        [,$tagDocNode] = $this->getDocBlockFromConstructor($class, $property);

        if (null === $tagDocNode) {
            return null;
        }

        $types = [];
        foreach ($this->phpStanTypeHelper->getTypes($tagDocNode, $this->nameScopeFactory->create($class)) as $type) {
            $types[] = $type;
        }

        if (!isset($types[0])) {
            return null;
        }

        return $types;
    }

    /**
     * @param string $class
     * @return array{PhpDocNode|PhpDocTagNode}|null
     */
    private function getDocBlockFromConstructor(string $class, string $property): ?array
    {
        try {
            $reflectionClass = new \ReflectionClass($class);
        } catch (\ReflectionException) {
            return null;
        }

        if (null === $reflectionConstructor = $reflectionClass->getConstructor()) {
            return null;
        }

        $rawDocNode = $reflectionConstructor->getDocComment();

        if (!$rawDocNode) {
            return null;
        }

        $tokens = new TokenIterator($this->lexer->tokenize($rawDocNode));
        $phpDocNode = $this->phpDocParser->parse($tokens);
        $tokens->consumeTokenType(Lexer::TOKEN_END);

        return [$phpDocNode, $this->filterDocBlockParams($phpDocNode, $property)];
    }

    private function filterDocBlockParams(PhpDocNode $docNode, string $allowedParam): ?ParamTagValueNode
    {
        $tags = array_values(array_filter($docNode->getTagsByName('@param'), function ($tagNode) use ($allowedParam) {
            return $tagNode instanceof PhpDocTagNode && ('$'.$allowedParam) === $tagNode->value->parameterName;
        }));

        if (!$tags) {
            return null;
        }

        return $tags[0]->value;
    }

    /**
     * @return array{PhpDocNode|null, int|null, string|null, string|null}
     */
    private function getDocBlock(string $class, string $property, ?string $outerClassProperty = null): array
    {
        $propertyHash = $class.'::'.$property.'|'.$outerClassProperty;

        if (isset($this->docBlocks[$propertyHash])) {
            return $this->docBlocks[$propertyHash];
        }

        $ucFirstProperty = ucfirst($property);

        if ([$docBlock, $source, $declaringClass] = $this->getDocBlockFromProperty($class, $property, $outerClassProperty)) {
            $data = [$docBlock, $source, null, $declaringClass];
        } elseif ([$docBlock, $_, $declaringClass] = $this->getDocBlockFromMethod($class, $ucFirstProperty, self::ACCESSOR, $outerClassProperty)) {
            $data = [$docBlock, self::ACCESSOR, null, $declaringClass];
        } elseif ([$docBlock, $prefix, $declaringClass] = $this->getDocBlockFromMethod($class, $ucFirstProperty, self::MUTATOR, $outerClassProperty)) {
            $data = [$docBlock, self::MUTATOR, $prefix, $declaringClass];
        } else {
            $data = [null, null, null, null];
        }

        return $this->docBlocks[$propertyHash] = $data;
    }

    /**
     * @return array{PhpDocNode, int, string}|null
     */
    private function getDocBlockFromProperty(string $class, string $property, ?string $outerClassProperty = null): ?array
    {
        // Use a ReflectionProperty instead of $class to get the parent class if applicable
        try {
            $reflectionProperty = new \ReflectionProperty($class, $property);
        } catch (\ReflectionException) {
            return null;
        }

        $propertyDocNode = $reflectionProperty->getDocComment();

        if (!$propertyDocNode) {
            if ($reflectionProperty->isPromoted() && null !== [$phpDocNode, $propertyTagNode] = $this->getDocBlockFromConstructor($class, $property)) {
                if ($propertyTagNode !== null) {
                    $source = self::MUTATOR;
                } else {
                    return null;
                }
            } else {
                return null;
            }
        } else {
            $tokens = new TokenIterator($this->lexer->tokenize($propertyDocNode));
            $phpDocNode = $this->phpDocParser->parse($tokens);
            $source = self::PROPERTY;
            $tokens->consumeTokenType(Lexer::TOKEN_END);
        }

        if ($outerClassProperty !== null) {
            $this->resolveGenericTypes($class, $phpDocNode, $outerClassProperty);
        }

        return [$phpDocNode, $source, $reflectionProperty->class];
    }

    private function resolveGenericTypes(string $class, PhpDocNode $docBlock, string $outerClassProperty): void
    {
        if ($classDocBlock = $this->getDocBlockFromClass($class)) {
            // Search @var and @param tags to support promoted properties
            if(false !== $propertyTypeTag = current($docBlock->getVarTagValues() + $docBlock->getParamTagValues() + $docBlock->getReturnTagValues())) {
                if ([] !== $classDocBlockTemplateTags = $classDocBlock->getTemplateTagValues()) {
                    if (null === $templatePosition = $this->getTemplateDeclarationOrderPositionOfPropertyTag($classDocBlockTemplateTags, $propertyTypeTag)) {
                        return;
                    }

                    [$outerClass, $outerProperty] = explode('::', $outerClassProperty);
                    [$outerClassPropertyDocBlock] = $this->docBlocks[$outerClassProperty] ?? $this->getDocBlock($outerClass, $outerProperty);

                    if ($outerClassPropertyDocBlock === null) {
                        return;
                    }

                    $outerClassPropertyTypeTag = current($outerClassPropertyDocBlock->getVarTagValues() + $outerClassPropertyDocBlock->getParamTagValues() + $docBlock->getReturnTagValues());

                    if ($outerClassPropertyTypeTag === []) {
                        return;
                    }

                    $genericType = clone $outerClassPropertyTypeTag->type;
                    $nonNullableGenericType = $genericType instanceof NullableTypeNode ? $genericType->type : $genericType;

                    if ($nonNullableGenericType instanceof GenericTypeNode) {
                        $typeVariableType = $nonNullableGenericType->genericTypes[$templatePosition];

                        if (!\in_array($typeVariableType->name, Type::$builtinTypes, true)) {
                            $propertyTypeTag->name = '\\' . $this->nameScopeFactory->create(explode('::', $outerClassProperty)[0])->resolveStringName($typeVariableType->name);
                        }

                        $propertyTypeTag->type = $typeVariableType;
                    }
                }
            }
        }
    }

    /**
     * @return array{PhpDocNode, string, string}|null
     */
    private function getDocBlockFromMethod(string $class, string $ucFirstProperty, int $type, ?string $outerClassProperty = null): ?array
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

        if (null === $rawDocNode = $reflectionMethod->getDocComment() ?: null) {
            return null;
        }

        $tokens = new TokenIterator($this->lexer->tokenize($rawDocNode));
        $phpDocNode = $this->phpDocParser->parse($tokens);
        $tokens->consumeTokenType(Lexer::TOKEN_END);

        if (null !== $outerClassProperty) {
            $this->resolveGenericTypes($class, $phpDocNode, $outerClassProperty);
        }

        return [$phpDocNode, $prefix, $reflectionMethod->class];
    }

    private function getDocBlockFromClass(string $class): ?PhpDocNode
    {
        // Use a ReflectionProperty instead of $class to get the parent class if applicable
        try {
            $reflectionClass = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            return null;
        }

        if (null === $rawDocNode = $reflectionClass->getDocComment() ?: null) {
            return null;
        }

        $tokens = new TokenIterator($this->lexer->tokenize($rawDocNode));
        $phpDocNode = $this->phpDocParser->parse($tokens);

        $tokens->consumeTokenType(Lexer::TOKEN_END);

        return $phpDocNode;
    }

    /**
     * @param TemplateTagValueNode[] $classDocBlockTemplateTags
     * @param ParamTagValueNode|VarTagValueNode $propertyTypeTag
     */
    private function getTemplateDeclarationOrderPositionOfPropertyTag(array $classDocBlockTemplateTags, mixed $propertyTypeTag): int|null
    {
        foreach ($classDocBlockTemplateTags as $orderPosition => $classDocBlockTemplateTag) {
            if ($classDocBlockTemplateTag->name === $propertyTypeTag->type->name) {
                return (int) $orderPosition;
            }
        }

        return null;
    }
}
