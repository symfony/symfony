<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\TypeResolver;

use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprArrayNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFalseNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFloatNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprNullNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprTrueNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstFetchNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\CallableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ObjectShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ThisTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\ParserException;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use Symfony\Component\TypeInfo\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Exception\LogicException;
use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Refinement;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ArrayShapeType;
use Symfony\Component\TypeInfo\Type\BackedEnumType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\RefinementTypeInterface;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeContext\TypeContext;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Resolves type for a given string.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
final class StringTypeResolver implements TypeResolverInterface
{
    /**
     * @var array<string, bool>
     */
    private static array $classExistCache = [];

    /**
     * @var array<string, true>
     */
    private array $resolvingExtraTypeAliases = [];

    private readonly Lexer $lexer;
    private readonly TypeParser $parser;

    /**
     * @var list<Refinement>
     */
    private readonly array $refinements;

    /**
     * @param array<string, string>      $extraTypeAliases
     * @param list<Refinement>|bool|null $refinements      The PHPDoc refinements to resolve instead of their builtin type, true for all of them, including those added later. Not passing it is deprecated since 8.2, all refinements will be resolved by default as of 9.0
     */
    public function __construct(
        ?Lexer $lexer = null,
        ?TypeParser $parser = null,
        private readonly array $extraTypeAliases = [],
        array|bool|null $refinements = null,
    ) {
        if (null === $refinements) {
            trigger_deprecation('symfony/type-info', '8.2', 'Not passing the "$refinements" argument of "%s()" is deprecated; all PHPDoc refinements will be resolved by default in 9.0. Pass true to resolve them now, or false to keep their builtin types.', __METHOD__);
        }

        $this->refinements = self::normalizeRefinements($refinements ?? false);

        if (class_exists(ParserConfig::class)) {
            $this->lexer = $lexer ?? new Lexer(new ParserConfig([]));
            $this->parser = $parser ?? new TypeParser($config = new ParserConfig([]), new ConstExprParser($config));
        } else {
            $this->lexer = $lexer ?? new Lexer();
            $this->parser = $parser ?? new TypeParser(new ConstExprParser(true, true), true);
        }
    }

    public function resolve(mixed $subject, ?TypeContext $typeContext = null): Type
    {
        if ($subject instanceof \Stringable) {
            $subject = (string) $subject;
        } elseif (!\is_string($subject)) {
            throw new UnsupportedException(\sprintf('Expected subject to be a "string", "%s" given.', get_debug_type($subject)), $subject);
        }

        try {
            return $this->getTypeFromNode($this->parse($subject), $typeContext);
        } catch (\DomainException|ParserException $e) {
            throw new UnsupportedException(\sprintf('Cannot resolve "%s".', $subject), $subject, previous: $e);
        }
    }

    private function getTypeFromNode(TypeNode $node, ?TypeContext $typeContext): Type
    {
        $typeIsCollectionObject = static fn (Type $type): bool => $type->isIdentifiedBy(\Traversable::class) || $type->isIdentifiedBy(\ArrayAccess::class);

        if ($node instanceof CallableTypeNode) {
            return Type::callable();
        }

        if ($node instanceof ArrayTypeNode) {
            return Type::array($this->getTypeFromNode($node->type, $typeContext));
        }

        if ($node instanceof ArrayShapeNode) {
            $shape = [];
            foreach ($node->items as $item) {
                $shapeItem = [
                    'type' => $this->getTypeFromNode($item->valueType, $typeContext),
                    'optional' => $item->optional,
                ];

                if (null === $item->keyName) {
                    $shape[] = $shapeItem;

                    continue;
                }

                $shape[$item->keyName instanceof ConstExprStringNode ? $item->keyName->value : (string) $item->keyName] = $shapeItem;
            }

            if (\in_array($node->kind, ['list', 'non-empty-list'], true)) {
                $extraValueType = $node->sealed ? null : ($node->unsealedType?->valueType ? $this->getTypeFromNode($node->unsealedType->valueType, $typeContext) : Type::mixed());

                try {
                    return new ArrayShapeType($shape, $extraValueType ? Type::int() : null, $extraValueType, true);
                } catch (InvalidArgumentException $e) {
                    throw new \DomainException($e->getMessage(), previous: $e);
                }
            }

            return Type::arrayShape(
                $shape,
                $node->sealed,
                $node->unsealedType?->keyType ? $this->getTypeFromNode($node->unsealedType->keyType, $typeContext) : null,
                $node->unsealedType?->valueType ? $this->getTypeFromNode($node->unsealedType->valueType, $typeContext) : null,
            );
        }

        if ($node instanceof ObjectShapeNode) {
            $shape = [];
            foreach ($node->items as $item) {
                $shape[$item->keyName instanceof ConstExprStringNode ? $item->keyName->value : (string) $item->keyName] = [
                    'type' => $this->getTypeFromNode($item->valueType, $typeContext),
                    'optional' => $item->optional,
                ];
            }

            return $shape ? Type::objectShape($shape) : Type::object();
        }

        if ($node instanceof ThisTypeNode) {
            if (null === $typeContext) {
                throw new InvalidArgumentException(\sprintf('A "%s" must be provided to resolve "$this".', TypeContext::class));
            }

            return Type::object($typeContext->getCalledClass());
        }

        if ($node instanceof ConstTypeNode) {
            if ($node->constExpr instanceof ConstFetchNode) {
                if (null === $className = $this->getConstantClassName($node->constExpr, $typeContext)) {
                    return Type::mixed();
                }

                $types = [];

                foreach ((new \ReflectionClass($className))->getReflectionConstants() as $const) {
                    if (preg_match('/^'.str_replace('\*', '.*', preg_quote($node->constExpr->name, '/')).'$/', $const->getName())) {
                        $types[] = Type::fromValue($const->getValue());
                    }
                }

                if (!$types) {
                    throw new \DomainException(\sprintf('No "%s" constant found in "%s".', $node->constExpr->name, $className));
                }

                return CollectionType::mergeCollectionValueTypes($types);
            }

            if ($this->resolves(Refinement::Literal)) {
                $literal = match (true) {
                    $node->constExpr instanceof ConstExprStringNode => $node->constExpr->value,
                    $node->constExpr instanceof ConstExprIntegerNode => self::parseInteger($node->constExpr->value),
                    $node->constExpr instanceof ConstExprFloatNode => (float) $node->constExpr->value,
                    default => null,
                };

                if (null !== $literal) {
                    try {
                        return Type::literal($literal);
                    } catch (InvalidArgumentException $e) {
                        throw new \DomainException($e->getMessage(), 0, $e);
                    }
                }
            }

            return match (true) {
                $node->constExpr instanceof ConstExprArrayNode => Type::array(),
                $node->constExpr instanceof ConstExprFalseNode => Type::false(),
                $node->constExpr instanceof ConstExprFloatNode => Type::float(),
                $node->constExpr instanceof ConstExprIntegerNode => Type::int(),
                $node->constExpr instanceof ConstExprNullNode => Type::null(),
                $node->constExpr instanceof ConstExprStringNode => Type::string(),
                $node->constExpr instanceof ConstExprTrueNode => Type::true(),
                default => throw new \DomainException(\sprintf('Unhandled "%s" constant expression.', $node->constExpr::class)),
            };
        }

        if ($node instanceof IdentifierTypeNode) {
            $type = $this->resolveBuiltinIdentifier($node->name, $typeContext) ?? $this->resolveCustomIdentifier($node->name, $typeContext);

            if ($typeIsCollectionObject($type)) {
                return Type::collection($type);
            }

            return $type;
        }

        if ($node instanceof NullableTypeNode) {
            $type = $this->getTypeFromNode($node->type, $typeContext);

            try {
                return Type::nullable($type);
            } catch (InvalidArgumentException $e) {
                throw new \DomainException($e->getMessage(), previous: $e);
            }
        }

        if ($node instanceof GenericTypeNode) {
            $type = null;
            $keyword = $node->type instanceof IdentifierTypeNode ? $node->type->name : null;
            if (null !== $keyword && ($lowercase = strtolower($keyword)) !== $keyword && \in_array($lowercase, ['value-of', 'key-of'], true)) {
                try {
                    $type = $this->getTypeFromNode($node->type, $typeContext);
                } catch (\DomainException) {
                    $keyword = $lowercase;
                }
            }

            if ('value-of' === $keyword) {
                $type = $this->getTypeFromNode($node->genericTypes[0], $typeContext);
                if ($type instanceof BackedEnumType) {
                    return $type->getBackingType();
                }

                if ($type instanceof CollectionType) {
                    return $type->getCollectionValueType();
                }

                throw new \DomainException(\sprintf('"%s" is not a valid type for "value-of".', $node->genericTypes[0]));
            }

            if ('key-of' === $keyword) {
                $type = $this->getTypeFromNode($node->genericTypes[0], $typeContext);
                if ($type instanceof CollectionType) {
                    return $type->getCollectionKeyType();
                }

                throw new \DomainException(\sprintf('"%s" is not a valid type for "key-of".', $node->genericTypes[0]));
            }

            $type ??= $this->getTypeFromNode($node->type, $typeContext);

            if ($type->isIdentifiedBy(TypeIdentifier::INT)) {
                if (!$this->resolves(Refinement::IntRange) || !$type instanceof BuiltinType || 2 !== \count($node->genericTypes)) {
                    return $type;
                }

                $min = $this->getIntRangeBound($node->genericTypes[0], 'min', $typeContext);
                $max = $this->getIntRangeBound($node->genericTypes[1], 'max', $typeContext);

                if (false === $min || false === $max) {
                    return $type;
                }

                // the integer limits are no bound at all
                if ((null === $min || \PHP_INT_MIN === $min) && (null === $max || \PHP_INT_MAX === $max)) {
                    return $type;
                }

                try {
                    return Type::intRange($min, $max);
                } catch (InvalidArgumentException $e) {
                    throw new \DomainException($e->getMessage(), 0, $e);
                }
            }

            $variableTypes = array_map(fn (TypeNode $t): Type => $this->getTypeFromNode($t, $typeContext), $node->genericTypes);

            if ($type instanceof CollectionType) {
                $asList = $type->isList();
                $keyType = $type->getCollectionKeyType();
                $type = $type->getWrappedType();

                if ($type instanceof GenericType) {
                    $type = $type->getWrappedType();
                }

                if (1 === \count($variableTypes)) {
                    return new CollectionType(Type::generic($type, $keyType, $variableTypes[0]), $asList);
                } elseif (2 === \count($variableTypes)) {
                    if ($asList) {
                        throw new \DomainException(\sprintf('"%s" type cannot have a key type defined.', $node->type));
                    }

                    return Type::collection($type, $variableTypes[1], $variableTypes[0], $asList);
                }
            }

            if ($typeIsCollectionObject($type)) {
                return match (\count($variableTypes)) {
                    1 => Type::collection($type, $variableTypes[0]),
                    2 => Type::collection($type, $variableTypes[1], $variableTypes[0]),
                    default => Type::collection($type),
                };
            }

            if ($type instanceof BuiltinType && TypeIdentifier::ARRAY !== $type->getTypeIdentifier() && TypeIdentifier::ITERABLE !== $type->getTypeIdentifier()) {
                return $type;
            }

            return Type::generic($type, ...$variableTypes);
        }

        if ($node instanceof UnionTypeNode) {
            $types = [];
            $seenTypes = [];
            $identifiers = [];

            foreach ($node->types as $nodeType) {
                $type = $this->getTypeFromNode($nodeType, $typeContext);

                if ($type instanceof BuiltinType && TypeIdentifier::MIXED === $type->getTypeIdentifier()) {
                    return Type::mixed();
                }

                foreach ($type instanceof UnionType ? $type->getTypes() : [$type] as $t) {
                    // as in UnionType: a loose comparison alone finds "'10'|'1e1'" equal, a string comparison alone merges types that only print the same
                    if (\in_array($t, $seenTypes[(string) $t] ?? [])) {
                        continue;
                    }

                    $seenTypes[(string) $t][] = $t;
                    $types[] = $t;

                    if ($t instanceof BuiltinType) {
                        $identifiers[$t->getTypeIdentifier()->value] = true;
                    }
                }
            }

            // "1|int" and "'a'|string" mean "int" and "string", as do "int|positive-int" and "'a'|'b'" without refinements
            $types = array_values(array_filter($types, static fn (Type $t): bool => !$t instanceof RefinementTypeInterface || !isset($identifiers[$t->getWrappedType()->getTypeIdentifier()->value])));

            // "true|false" and "bool|true" mean "bool", "object|Foo" means "object"
            if (isset($identifiers['bool']) || isset($identifiers['true'], $identifiers['false'])) {
                $types = [Type::bool(), ...array_filter($types, static fn (Type $t): bool => !$t instanceof BuiltinType || !$t->getTypeIdentifier()->isBool())];
            }

            if (isset($identifiers['object'])) {
                $types = array_values(array_filter($types, static fn (Type $t): bool => !$t instanceof ObjectType));
            }

            try {
                return 1 === \count($types) ? $types[0] : Type::union(...$types);
            } catch (InvalidArgumentException $e) {
                throw new \DomainException($e->getMessage(), previous: $e);
            }
        }

        if ($node instanceof IntersectionTypeNode) {
            $types = [];

            foreach ($node->types as $nodeType) {
                $type = $this->getTypeFromNode($nodeType, $typeContext);

                if (!\in_array($type, $types)) {
                    $types[] = $type;
                }
            }

            if (1 === \count($types)) {
                return $types[0];
            }

            $shapes = array_filter($types, static fn (Type $t): bool => $t instanceof ArrayShapeType);

            if (\count($shapes) === \count($types)) {
                return $this->mergeArrayShapes($types);
            }

            try {
                return Type::intersection(...$types);
            } catch (InvalidArgumentException $e) {
                throw new \DomainException($e->getMessage(), previous: $e);
            }
        }

        throw new \DomainException(\sprintf('Unhandled "%s" node.', $node::class));
    }

    private function resolveBuiltinIdentifier(string $identifier, ?TypeContext $typeContext): ?Type
    {
        return match ($identifier) {
            'bool', 'boolean' => Type::bool(),
            'true' => Type::true(),
            'false' => Type::false(),
            'int', 'integer' => Type::int(),
            'non-zero-int' => $this->resolves(Refinement::IntRange) ? Type::union(Type::intRange(null, -1), Type::intRange(1, null)) : Type::int(),
            'positive-int' => $this->resolves(Refinement::IntRange) ? Type::intRange(1, null) : Type::int(),
            'negative-int' => $this->resolves(Refinement::IntRange) ? Type::intRange(null, -1) : Type::int(),
            'non-positive-int' => $this->resolves(Refinement::IntRange) ? Type::intRange(null, 0) : Type::int(),
            'non-negative-int' => $this->resolves(Refinement::IntRange) ? Type::intRange(0, null) : Type::int(),
            'float', 'double' => Type::float(),
            'string',
            'class-string',
            'trait-string',
            'interface-string',
            'callable-string',
            'numeric-string',
            'lowercase-string',
            'non-empty-lowercase-string',
            'non-empty-string',
            'non-falsy-string',
            'truthy-string',
            'literal-string',
            'html-escaped-string' => Type::string(),
            'resource' => Type::resource(),
            'object' => Type::object(),
            'callable' => Type::callable(),
            'array', 'non-empty-array' => Type::array(),
            'list', 'non-empty-list' => Type::list(),
            'iterable' => Type::iterable(),
            'mixed' => Type::mixed(),
            'null' => Type::null(),
            'array-key' => Type::arrayKey(),
            'scalar' => Type::union(Type::int(), Type::float(), Type::string(), Type::bool()),
            'number' => Type::union(Type::int(), Type::float()),
            'numeric' => Type::union(Type::int(), Type::float(), Type::string()),
            'self' => $typeContext ? Type::object($typeContext->getDeclaringClass()) : throw new InvalidArgumentException(\sprintf('A "%s" must be provided to resolve "self".', TypeContext::class)),
            'static' => $typeContext ? Type::object($typeContext->getCalledClass()) : throw new InvalidArgumentException(\sprintf('A "%s" must be provided to resolve "static".', TypeContext::class)),
            'parent' => $typeContext ? Type::object($typeContext->getParentClass()) : throw new InvalidArgumentException(\sprintf('A "%s" must be provided to resolve "parent".', TypeContext::class)),
            'void' => Type::void(),
            'never', 'never-return', 'never-returns', 'no-return' => Type::never(),
            default => null,
        };
    }

    private function resolveCustomIdentifier(string $identifier, ?TypeContext $typeContext): Type
    {
        $className = $typeContext ? $typeContext->normalize($identifier) : $identifier;

        if (!isset(self::$classExistCache[$className])) {
            self::$classExistCache[$className] = false;

            if (class_exists($className) || interface_exists($className)) {
                self::$classExistCache[$className] = true;
            } else {
                try {
                    new \ReflectionClass($className);
                    self::$classExistCache[$className] = true;
                } catch (\Throwable) {
                }
            }
        }

        if (self::$classExistCache[$className]) {
            if (is_subclass_of($className, \UnitEnum::class) && !interface_exists($className)) {
                return Type::enum($className);
            }

            return Type::object($className);
        }

        if (null !== $type = $typeContext?->inheritedTemplateTypes[$identifier] ?? null) {
            return $type;
        }

        if (isset($typeContext?->templates[$identifier])) {
            return Type::template($identifier, $typeContext->templates[$identifier]);
        }

        if (isset($typeContext?->typeAliases[$identifier])) {
            return $typeContext->typeAliases[$identifier];
        }

        if (isset($this->extraTypeAliases[$identifier])) {
            if (isset($this->resolvingExtraTypeAliases[$identifier])) {
                throw new LogicException(\sprintf('Cannot resolve "%s" type alias as it is recursive.', $identifier));
            }

            $this->resolvingExtraTypeAliases[$identifier] = true;

            try {
                return $this->resolve($this->extraTypeAliases[$identifier]);
            } finally {
                unset($this->resolvingExtraTypeAliases[$identifier]);
            }
        }

        if (($keyword = strtolower($identifier)) !== $identifier && null !== $type = $this->resolveBuiltinIdentifier($keyword, $typeContext)) {
            return $type;
        }

        throw new \DomainException(\sprintf('Unhandled "%s" identifier.', $identifier));
    }

    private function parse(string $subject): TypeNode
    {
        $lexerTokens = $this->lexer->tokenize($subject);
        $tokens = new TokenIterator($lexerTokens);
        $node = $this->parser->parse($tokens);

        while ($tokens->tryConsumeTokenType(Lexer::TOKEN_PHPDOC_EOL)) {
        }

        $i = $tokens->currentTokenIndex();
        if (Lexer::TOKEN_OPEN_CURLY_BRACKET === ($lexerTokens[$i][Lexer::TYPE_OFFSET] ?? null)) {
            while (\in_array($lexerTokens[--$i][Lexer::TYPE_OFFSET] ?? null, [Lexer::TOKEN_HORIZONTAL_WS, Lexer::TOKEN_PHPDOC_EOL], true)) {
            }

            $value = $lexerTokens[$i][Lexer::VALUE_OFFSET] ?? '';
            if (($keyword = strtolower($value)) !== $value && \in_array($keyword, ['array', 'list', 'non-empty-array', 'non-empty-list'], true)) {
                throw new \DomainException(\sprintf('Use "%s{...}" instead of "%s{...}" for shape types.', $keyword, $value));
            }
        }

        return $node;
    }

    /**
     * @param list<ArrayShapeType> $types
     */
    private function mergeArrayShapes(array $types): ArrayShapeType
    {
        $shape = [];
        $extraKeyType = $types[0]->getExtraKeyType();
        $extraValueType = $types[0]->getExtraValueType();
        $isList = false;

        foreach ($types as $type) {
            if ((string) $extraKeyType !== (string) $type->getExtraKeyType() || (string) $extraValueType !== (string) $type->getExtraValueType()) {
                throw new \DomainException(\sprintf('Cannot merge "%s" and "%s".', $types[0], $type));
            }

            $isList = $isList || $type->isList();

            foreach ($type->getShape() as $key => $item) {
                if (isset($shape[$key]) && (string) $shape[$key]['type'] !== (string) $item['type']) {
                    throw new \DomainException(\sprintf('Cannot merge array shapes that declare the "%s" key with different types.', $key));
                }

                $shape[$key] = ['type' => $item['type'], 'optional' => $item['optional'] && ($shape[$key]['optional'] ?? true)];
            }
        }

        return new ArrayShapeType($shape, $extraKeyType, $extraValueType, $isList);
    }

    private function getConstantClassName(ConstFetchNode $node, ?TypeContext $typeContext): ?string
    {
        $classKeyword = strtolower($node->className);

        if (null === $typeContext && \in_array($classKeyword, ['self', 'static', 'parent'], true)) {
            throw new InvalidArgumentException(\sprintf('A "%s" must be provided to resolve "%s".', TypeContext::class, $classKeyword));
        }

        $className = match ($classKeyword) {
            'self' => $typeContext->getDeclaringClass(),
            'static' => $typeContext->getCalledClass(),
            'parent' => $typeContext->getParentClass(),
            default => null,
        };

        if (null === $className) {
            $classType = $this->resolveCustomIdentifier($node->className, $typeContext);
            if (!$classType instanceof ObjectType) {
                return null;
            }

            $className = $classType->getClassName();
        }

        if (!class_exists($className) && !interface_exists($className)) {
            return null;
        }

        return $className;
    }

    /**
     * @return int|false|null the bound, null when unbounded, or false when it is a constant that cannot be resolved
     */
    private function getIntRangeBound(TypeNode $node, string $unboundedKeyword, ?TypeContext $typeContext): int|false|null
    {
        if ($node instanceof ConstTypeNode && $node->constExpr instanceof ConstExprIntegerNode) {
            return self::parseInteger($node->constExpr->value);
        }

        if ($node instanceof IdentifierTypeNode && $unboundedKeyword === $node->name) {
            return null;
        }

        $value = false;

        if ($node instanceof ConstTypeNode && $node->constExpr instanceof ConstFetchNode && !str_contains($node->constExpr->name, '*')) {
            if (null === $className = $this->getConstantClassName($node->constExpr, $typeContext)) {
                return false;
            }

            // reflection reads private and protected constants as well
            if (false === $constant = (new \ReflectionClass($className))->getReflectionConstant($node->constExpr->name)) {
                throw new \DomainException(\sprintf('No "%s" constant found in "%s".', $node->constExpr->name, $className));
            }

            try {
                $value = $constant->getValue();
            } catch (\Error) {
                // its value may depend on a class that is not installed
                return false;
            }
        } elseif ($node instanceof IdentifierTypeNode && !\in_array($node->name, ['min', 'max'], true)) {
            $name = $node->name;

            if (str_contains($name, '\\')) {
                // like PHP, a qualified name resolves through the imports and the namespace, with no fallback
                $name = $typeContext?->normalize($name) ?? ltrim($name, '\\');
            } elseif ($typeContext?->namespace && \defined($typeContext->namespace.'\\'.$name)) {
                // an unqualified one falls back to the global constant
                $name = $typeContext->namespace.'\\'.$name;
            }

            // the constant may be defined by code that has not run yet
            if (!\defined($name)) {
                return false;
            }

            $value = \constant($name);
        }

        if (!\is_int($value)) {
            throw new \DomainException(\sprintf('"%s" is not a valid integer range bound.', $node));
        }

        return $value;
    }

    private function resolves(Refinement $refinement): bool
    {
        return \in_array($refinement, $this->refinements, true);
    }

    /**
     * @param list<Refinement>|bool $refinements
     *
     * @return list<Refinement>
     */
    private static function normalizeRefinements(array|bool $refinements): array
    {
        if (\is_bool($refinements)) {
            return $refinements ? Refinement::cases() : [];
        }

        foreach ($refinements as $refinement) {
            if (!$refinement instanceof Refinement) {
                throw new InvalidArgumentException(\sprintf('The "$refinements" argument must be a boolean or a list of "%s" cases, "%s" given in the list.', Refinement::class, get_debug_type($refinement)));
            }
        }

        return array_values($refinements);
    }

    private static function parseInteger(string $value): int
    {
        // the lexer allows a leading "+", which changes nothing
        preg_match('/^(?:\+|(-))?(0[xbo]|0(?=.))?(.*)$/i', $value, $matches);
        [, $sign, $prefix, $digits] = $matches;

        $base = match (strtolower($prefix)) {
            '0x' => 16,
            '0b' => 2,
            '0o', '0' => 8,
            default => 10,
        };

        // intval() stops at the first digit that is not valid in the base
        $invalidDigit = match ($base) {
            16 => '/[^0-9a-f]/i',
            8 => '/[^0-7]/',
            2 => '/[^01]/',
            default => '/[^0-9]/',
        };

        if (preg_match($invalidDigit, $digits)) {
            throw new \DomainException(\sprintf('"%s" is not a valid integer.', $value));
        }

        // the sign is parsed along, as the magnitude of PHP_INT_MIN does not fit in an integer on its own
        $integer = \intval($sign.$digits, $base);

        // intval() saturates on overflow, while PHP reads such a literal as a float
        if (\PHP_INT_MAX === $integer || \PHP_INT_MIN === $integer) {
            $limit = match ($base) {
                16 => dechex($integer),
                8 => decoct($integer),
                2 => decbin($integer),
                default => ltrim((string) $integer, '-'),
            };

            if (ltrim(strtolower($digits), '0') !== $limit) {
                throw new \DomainException(\sprintf('"%s" does not fit in an integer.', $value));
            }
        }

        return $integer;
    }
}
