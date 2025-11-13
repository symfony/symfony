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

use phpDocumentor\Reflection\Types\ContextFactory;
use PHPStan\PhpDocParser\Ast\PhpDoc\InvalidTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocChildNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use Symfony\Component\PropertyInfo\PropertyDescriptionExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\PropertyInfo\Util\PhpStanTypeHelper;
use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContext;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;

/**
 * Extracts data using PHPStan parser.
 *
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
final class PhpStanExtractor implements PropertyDescriptionExtractorInterface, PropertyTypeExtractorInterface, ConstructorArgumentTypeExtractorInterface
{
    private const PROPERTY = 0;
    private const ACCESSOR = 1;
    private const MUTATOR = 2;

    private PhpDocParser $phpDocParser;
    private Lexer $lexer;

    private StringTypeResolver $stringTypeResolver;
    private TypeContextFactory $typeContextFactory;

    /** @var array<string, array{PhpDocNode|null, int|null, string|null, string|null}> */
    private array $docBlocks = [];
    private PhpStanTypeHelper $phpStanTypeHelper;
    private array $mutatorPrefixes;
    private array $accessorPrefixes;
    private array $arrayMutatorPrefixes;

    /** @var array<string, TypeContext> */
    private array $contexts = [];

    /**
     * @param list<string>|null $mutatorPrefixes
     * @param list<string>|null $accessorPrefixes
     * @param list<string>|null $arrayMutatorPrefixes
     */
    public function __construct(?array $mutatorPrefixes = null, ?array $accessorPrefixes = null, ?array $arrayMutatorPrefixes = null, private bool $allowPrivateAccess = true)
    {
        if (!class_exists(ContextFactory::class)) {
            throw new \LogicException(\sprintf('Unable to use the "%s" class as the "phpdocumentor/type-resolver" package is not installed. Try running composer require "phpdocumentor/type-resolver".', __CLASS__));
        }

        if (!class_exists(PhpDocParser::class)) {
            throw new \LogicException(\sprintf('Unable to use the "%s" class as the "phpstan/phpdoc-parser" package is not installed. Try running composer require "phpstan/phpdoc-parser".', __CLASS__));
        }

        $this->phpStanTypeHelper = new PhpStanTypeHelper();
        $this->mutatorPrefixes = $mutatorPrefixes ?? ReflectionExtractor::$defaultMutatorPrefixes;
        $this->accessorPrefixes = $accessorPrefixes ?? ReflectionExtractor::$defaultAccessorPrefixes;
        $this->arrayMutatorPrefixes = $arrayMutatorPrefixes ?? ReflectionExtractor::$defaultArrayMutatorPrefixes;

        if (class_exists(ParserConfig::class)) {
            $parserConfig = new ParserConfig([]);
            $this->phpDocParser = new PhpDocParser($parserConfig, new TypeParser($parserConfig, new ConstExprParser($parserConfig)), new ConstExprParser($parserConfig));
            $this->lexer = new Lexer($parserConfig);
        } else {
            $this->phpDocParser = new PhpDocParser(new TypeParser(new ConstExprParser()), new ConstExprParser());
            $this->lexer = new Lexer();
        }
        $this->stringTypeResolver = new StringTypeResolver();
        $this->typeContextFactory = new TypeContextFactory($this->stringTypeResolver);
    }

    /**
     * @deprecated since Symfony 7.3, use "getType" instead
     */
    public function getTypes(string $class, string $property, array $context = []): ?array
    {
        trigger_deprecation('symfony/property-info', '7.3', 'The "%s()" method is deprecated, use "%s::getType()" instead.', __METHOD__, self::class);

        /** @var PhpDocNode|null $docNode */
        [$docNode, $source, $prefix, $declaringClass] = $this->getDocBlock($class, $property);
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

            $typeContext = $this->contexts[$class.'/'.$declaringClass] ??= $this->typeContextFactory->createFromClassName($class, $declaringClass);

            foreach ($this->phpStanTypeHelper->getTypes($tagDocNode->value, $typeContext) as $type) {
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

        if (!isset($types[0])) {
            return null;
        }

        if (!\in_array($prefix, $this->arrayMutatorPrefixes, true)) {
            return $types;
        }

        return [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY, false, null, true, new LegacyType(LegacyType::BUILTIN_TYPE_INT), $types[0])];
    }

    /**
     * @deprecated since Symfony 7.3, use "getTypeFromConstructor" instead
     *
     * @return LegacyType[]|null
     */
    public function getTypesFromConstructor(string $class, string $property): ?array
    {
        trigger_deprecation('symfony/property-info', '7.3', 'The "%s()" method is deprecated, use "%s::getTypeFromConstructor()" instead.', __METHOD__, self::class);

        if (null === $tagDocNode = $this->getDocBlockFromConstructor($class, $property)) {
            return null;
        }

        $types = [];
        foreach ($this->phpStanTypeHelper->getTypes($tagDocNode, $this->typeContextFactory->createFromClassName($class)) as $type) {
            $types[] = $type;
        }

        if (!isset($types[0])) {
            return null;
        }

        return $types;
    }

    public function getType(string $class, string $property, array $context = []): ?Type
    {
        /** @var PhpDocNode|null $docNode */
        [$docNode, $source, $prefix, $declaringClass] = $this->getDocBlock($class, $property);

        if (null === $docNode) {
            return null;
        }

        $typeContext = $this->typeContextFactory->createFromClassName($class, $declaringClass);

        $tag = match ($source) {
            self::PROPERTY => '@var',
            self::ACCESSOR => '@return',
            self::MUTATOR => '@param',
            default => 'invalid',
        };

        $types = [];

        foreach ($docNode->getTagsByName($tag) as $tagDocNode) {
            if (!$tagDocNode->value instanceof ParamTagValueNode && !$tagDocNode->value instanceof ReturnTagValueNode && !$tagDocNode->value instanceof VarTagValueNode) {
                continue;
            }

            if ($tagDocNode->value instanceof ParamTagValueNode && null === $prefix && $tagDocNode->value->parameterName !== '$'.$property) {
                continue;
            }

            try {
                $types[] = $this->stringTypeResolver->resolve((string) $tagDocNode->value->type, $typeContext);
            } catch (UnsupportedException) {
            }
        }

        if (!$type = $types[0] ?? null) {
            return null;
        }

        if (!\in_array($prefix, $this->arrayMutatorPrefixes, true)) {
            return $type;
        }

        return Type::list($type);
    }

    public function getTypeFromConstructor(string $class, string $property): ?Type
    {
        $declaringClass = $class;
        if (!$tagDocNode = $this->getDocBlockFromConstructor($declaringClass, $property)) {
            return null;
        }

        $typeContext = $this->typeContextFactory->createFromClassName($class, $declaringClass);

        return $this->stringTypeResolver->resolve((string) $tagDocNode->type, $typeContext);
    }

    public function getShortDescription(string $class, string $property, array $context = []): ?string
    {
        /** @var PhpDocNode|null $docNode */
        [$docNode] = $this->getDocBlockFromProperty($class, $property);
        if (null === $docNode) {
            return null;
        }

        if ($shortDescription = $this->getDescriptionsFromDocNode($docNode)[0]) {
            return $shortDescription;
        }

        foreach ($docNode->getVarTagValues() as $var) {
            if ($var->description) {
                return $var->description;
            }
        }

        return null;
    }

    public function getLongDescription(string $class, string $property, array $context = []): ?string
    {
        /** @var PhpDocNode|null $docNode */
        [$docNode] = $this->getDocBlockFromProperty($class, $property);
        if (null === $docNode) {
            return null;
        }

        return $this->getDescriptionsFromDocNode($docNode)[1];
    }

    /**
     * A docblock is split into a template marker, a short description, an optional long description and a tags section.
     *
     * - The template marker is either empty, #@+ or #@-.
     * - The short description is started from a non-tag character, and until one or multiple newlines.
     * - The long description (optional) is started from a non-tag character, and until a new line is encountered followed by a tag.
     * - Tags, and the remaining characters
     *
     * This method returns the short and the long descriptions.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function getDescriptionsFromDocNode(PhpDocNode $docNode): array
    {
        $isTemplateMarker = static fn (PhpDocChildNode $node): bool => $node instanceof PhpDocTextNode && ('#@+' === $node->text || '#@-' === $node->text);

        $shortDescription = '';
        $longDescription = '';
        $shortDescriptionCompleted = false;

        // BC layer for phpstan/phpdoc-parser < 2.0
        if (!class_exists(ParserConfig::class)) {
            $isNewLine = static fn (PhpDocChildNode $node): bool => $node instanceof PhpDocTextNode && '' === $node->text;

            foreach ($docNode->children as $child) {
                if (!$child instanceof PhpDocTextNode) {
                    break;
                }

                if ($isTemplateMarker($child)) {
                    continue;
                }

                if ($isNewLine($child) && !$shortDescriptionCompleted) {
                    if ($shortDescription) {
                        $shortDescriptionCompleted = true;
                    }

                    continue;
                }

                if (!$shortDescriptionCompleted) {
                    $shortDescription = \sprintf("%s\n%s", $shortDescription, $child->text);

                    continue;
                }

                $longDescription = \sprintf("%s\n%s", $longDescription, $child->text);
            }
        } else {
            foreach ($docNode->children as $child) {
                if (!$child instanceof PhpDocTextNode) {
                    break;
                }

                if ($isTemplateMarker($child)) {
                    continue;
                }

                foreach (explode("\n", $child->text) as $line) {
                    if ('' === $line && !$shortDescriptionCompleted) {
                        if ($shortDescription) {
                            $shortDescriptionCompleted = true;
                        }

                        continue;
                    }

                    if (!$shortDescriptionCompleted) {
                        $shortDescription = \sprintf("%s\n%s", $shortDescription, $line);

                        continue;
                    }

                    $longDescription = \sprintf("%s\n%s", $longDescription, $line);
                }
            }
        }

        $shortDescription = trim(preg_replace('/^#@[+-]{1}/m', '', $shortDescription), "\n");
        $longDescription = trim($longDescription, "\n");

        return [
            $shortDescription ?: null,
            $longDescription ?: null,
        ];
    }

    private function getDocBlockFromConstructor(string &$class, string $property): ?ParamTagValueNode
    {
        try {
            $reflectionClass = new \ReflectionClass($class);
        } catch (\ReflectionException) {
            return null;
        }

        if (null === $reflectionConstructor = $reflectionClass->getConstructor()) {
            return null;
        }

        if (!$rawDocNode = $reflectionConstructor->getDocComment()) {
            return null;
        }
        $class = $reflectionConstructor->class;

        $phpDocNode = $this->getPhpDocNode($rawDocNode);

        return $this->filterDocBlockParams($phpDocNode, $property);
    }

    private function filterDocBlockParams(PhpDocNode $docNode, string $allowedParam): ?ParamTagValueNode
    {
        $tags = array_values(array_filter($docNode->getTagsByName('@param'), fn ($tagNode) => $tagNode instanceof PhpDocTagNode && ('$'.$allowedParam) === $tagNode->value->parameterName));

        if (!$tags) {
            return null;
        }

        return $tags[0]->value;
    }

    /**
     * @return array{PhpDocNode|null, int|null, string|null, string|null}
     */
    private function getDocBlock(string $class, string $property): array
    {
        $propertyHash = $class.'::'.$property;

        if (isset($this->docBlocks[$propertyHash])) {
            return $this->docBlocks[$propertyHash];
        }

        $ucFirstProperty = ucfirst($property);

        if ([$docBlock, $constructorDocBlock, $source, $declaringClass] = $this->getDocBlockFromProperty($class, $property)) {
            if (!$docBlock?->getTagsByName('@var') && $constructorDocBlock) {
                $docBlock = $constructorDocBlock;
            }

            $data = [$docBlock, $source, null, $declaringClass];
        } elseif ([$docBlock, $_, $declaringClass] = $this->getDocBlockFromMethod($class, $ucFirstProperty, self::ACCESSOR)) {
            $data = [$docBlock, self::ACCESSOR, null, $declaringClass];
        } elseif ([$docBlock, $prefix, $declaringClass] = $this->getDocBlockFromMethod($class, $ucFirstProperty, self::MUTATOR)) {
            $data = [$docBlock, self::MUTATOR, $prefix, $declaringClass];
        } else {
            $data = [null, null, null, null];
        }

        return $this->docBlocks[$propertyHash] = $data;
    }

    /**
     * @return array{?PhpDocNode, ?PhpDocNode, int, string}|null
     */
    private function getDocBlockFromProperty(string $class, string $property): ?array
    {
        // Use a ReflectionProperty instead of $class to get the parent class if applicable
        try {
            $reflectionProperty = new \ReflectionProperty($class, $property);
        } catch (\ReflectionException) {
            return null;
        }

        if (!$this->canAccessMemberBasedOnItsVisibility($reflectionProperty)) {
            return null;
        }

        $reflector = $reflectionProperty->getDeclaringClass();

        foreach ($reflector->getTraits() as $trait) {
            if ($trait->hasProperty($property)) {
                return $this->getDocBlockFromProperty($trait->getName(), $property);
            }
        }

        $rawDocNode = $reflectionProperty->getDocComment();
        $phpDocNode = $rawDocNode ? $this->getPhpDocNode($rawDocNode) : null;

        $constructorPhpDocNode = null;
        if ($reflectionProperty->isPromoted()) {
            $constructorRawDocNode = (new \ReflectionMethod($class, '__construct'))->getDocComment();
            $constructorPhpDocNode = $constructorRawDocNode ? $this->getPhpDocNode($constructorRawDocNode) : null;
        }

        $source = self::PROPERTY;
        if (!$phpDocNode?->getTagsByName('@var') && $constructorPhpDocNode) {
            $source = self::MUTATOR;
        }

        if (!$phpDocNode && !$constructorPhpDocNode) {
            return null;
        }

        return [$phpDocNode, $constructorPhpDocNode, $source, $reflectionProperty->class];
    }

    /**
     * @return array{PhpDocNode, string, string}|null
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
                    (
                        (self::ACCESSOR === $type && 0 === $reflectionMethod->getNumberOfRequiredParameters())
                        || (self::MUTATOR === $type && $reflectionMethod->getNumberOfParameters() >= 1)
                    )
                    && $this->canAccessMemberBasedOnItsVisibility($reflectionMethod)
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

        $phpDocNode = $this->getPhpDocNode($rawDocNode);

        return [$phpDocNode, $prefix, $reflectionMethod->class];
    }

    private function getPhpDocNode(string $rawDocNode): PhpDocNode
    {
        $tokens = new TokenIterator($this->lexer->tokenize($rawDocNode));
        $phpDocNode = $this->phpDocParser->parse($tokens);
        $tokens->consumeTokenType(Lexer::TOKEN_END);

        return $phpDocNode;
    }

    private function canAccessMemberBasedOnItsVisibility(\ReflectionProperty|\ReflectionMethod $member): bool
    {
        return $this->allowPrivateAccess || $member->isPublic();
    }
}
