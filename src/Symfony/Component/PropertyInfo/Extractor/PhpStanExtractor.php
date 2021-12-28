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

    /** @var array<string, array{PhpDocNode|null, int|null, string|null}> */
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
        [$docNode, $source, $prefix] = $this->getDocBlock($class, $property);
        $nameScope = $this->nameScopeFactory->create($class);
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

            foreach ($this->phpStanTypeHelper->getTypes($tagDocNode->value, $nameScope) as $type) {
                switch ($type->getClassName()) {
                    case 'self':
                    case 'static':
                        $resolvedClass = $class;
                        break;

                    case 'parent':
                        if (false !== $resolvedClass = $parentClass ?? $parentClass = get_parent_class($class)) {
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
        if (null === $tagDocNode = $this->getDocBlockFromConstructor($class, $property)) {
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

    private function getDocBlockFromConstructor(string $class, string $property): ?ParamTagValueNode
    {
        try {
            $reflectionClass = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            return null;
        }

        if (null === $reflectionConstructor = $reflectionClass->getConstructor()) {
            return null;
        }

        $rawDocNode = $reflectionConstructor->getDocComment();
        $tokens = new TokenIterator($this->lexer->tokenize($rawDocNode));
        $phpDocNode = $this->phpDocParser->parse($tokens);
        $tokens->consumeTokenType(Lexer::TOKEN_END);

        return $this->filterDocBlockParams($phpDocNode, $property);
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
     * @return array{PhpDocNode|null, int|null, string|null}
     */
    private function getDocBlock(string $class, string $property): array
    {
        $propertyHash = $class.'::'.$property;

        if (isset($this->docBlocks[$propertyHash])) {
            return $this->docBlocks[$propertyHash];
        }

        $ucFirstProperty = ucfirst($property);

        if ($docBlock = $this->getDocBlockFromProperty($class, $property)) {
            $data = [$docBlock, self::PROPERTY, null];
        } elseif ([$docBlock] = $this->getDocBlockFromMethod($class, $ucFirstProperty, self::ACCESSOR)) {
            $data = [$docBlock, self::ACCESSOR, null];
        } elseif ([$docBlock, $prefix] = $this->getDocBlockFromMethod($class, $ucFirstProperty, self::MUTATOR)) {
            $data = [$docBlock, self::MUTATOR, $prefix];
        } else {
            $data = [null, null, null];
        }

        return $this->docBlocks[$propertyHash] = $data;
    }

    private function getDocBlockFromProperty(string $class, string $property): ?PhpDocNode
    {
        // Use a ReflectionProperty instead of $class to get the parent class if applicable
        try {
            $reflectionProperty = new \ReflectionProperty($class, $property);
        } catch (\ReflectionException $e) {
            return null;
        }

        if (null === $rawDocNode = $reflectionProperty->getDocComment() ?: null) {
            return null;
        }

        $tokens = new TokenIterator($this->lexer->tokenize($rawDocNode));
        $phpDocNode = $this->phpDocParser->parse($tokens);
        $tokens->consumeTokenType(Lexer::TOKEN_END);

        return $phpDocNode;
    }

    /**
     * @return array{PhpDocNode, string}|null
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
            } catch (\ReflectionException $e) {
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

        return [$phpDocNode, $prefix];
    }
}
