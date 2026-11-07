<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\TypeContext;

use PHPStan\PhpDocParser\Ast\PhpDoc\ExtendsTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ImplementsTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TypeAliasImportTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TypeAliasTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\UsesTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use Symfony\Component\TypeInfo\Exception\ExceptionInterface;
use Symfony\Component\TypeInfo\Exception\LogicException;
use Symfony\Component\TypeInfo\Exception\RuntimeException;
use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\TemplateType;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;

/**
 * Creates a type resolving context.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 * @author Pierre-Yves Landuré <landure@gmail.com>
 */
final class TypeContextFactory
{
    /**
     * @var array<class-string, \ReflectionClass>
     */
    private static array $reflectionClassCache = [];

    /**
     * @var array<string, array<string, TypeContext>>
     */
    private array $baseTypeContextCache = [];

    /**
     * @var array<string, array<string, TypeContext>>
     */
    private array $typeContextCache = [];

    /**
     * @var array<string, array<string, string>>
     */
    private array $usesCache = [];

    /**
     * @var array<class-string, true>
     */
    private array $collectingTypeAliases = [];

    /**
     * @var array<class-string, array<string, true>>
     */
    private array $unresolvableTypeAliases = [];

    /**
     * @var array<class-string, PhpDocNode|null>
     */
    private array $classDocNodeCache = [];

    /**
     * @var array<class-string, array<class-string, list<Type>>>
     */
    private array $parentVariableTypesCache = [];

    /**
     * @var array<class-string, array<class-string, list<Type>>>
     */
    private array $traitVariableTypesCache = [];

    private ?Lexer $phpstanLexer = null;
    private ?PhpDocParser $phpstanParser = null;

    /**
     * @param array<string, string> $extraTypeAliases
     */
    public function __construct(
        private readonly ?StringTypeResolver $stringTypeResolver = null,
        private readonly array $extraTypeAliases = [],
    ) {
    }

    public function createFromClassName(string $calledClassName, ?string $declaringClassName = null): TypeContext
    {
        $declaringClassName ??= $calledClassName;

        return $this->typeContextCache[$declaringClassName][$calledClassName] ??= $this->createNewInstanceFromClassName($calledClassName, $declaringClassName);
    }

    public function createFromReflection(\Reflector $reflection): ?TypeContext
    {
        $classReflection = match (true) {
            $reflection instanceof \ReflectionClass => $reflection,
            $reflection instanceof \ReflectionMethod => $reflection->getDeclaringClass(),
            $reflection instanceof \ReflectionProperty => $reflection->getDeclaringClass(),
            $reflection instanceof \ReflectionParameter => $reflection->getDeclaringClass(),
            $reflection instanceof \ReflectionFunctionAbstract => $reflection->getClosureScopeClass(),
            default => null,
        };

        if (null === $classReflection) {
            return null;
        }

        // a trait member is read in the scope of the trait, but "self" and "parent" refer to the class using the trait
        if ($traitReflection = $this->getDeclaringTrait($reflection, $classReflection)) {
            $typeContext = $this->createFromClassName($classReflection->getName(), $traitReflection->getName());

            $templates = match (true) {
                $reflection instanceof \ReflectionFunctionAbstract => $this->collectTemplates($this->getPhpDocNode($reflection), $typeContext),
                $reflection instanceof \ReflectionParameter => $this->collectTemplates($this->getPhpDocNode($reflection->getDeclaringFunction()), $typeContext),
                default => [],
            };

            return new TypeContext(
                $typeContext->calledClassName,
                $classReflection->getName(),
                $typeContext->namespace,
                $typeContext->uses,
                $templates + $typeContext->templates,
                $typeContext->typeAliases,
                $typeContext->inheritedTemplateTypes,
            );
        }

        $typeContext = $this->createBaseTypeContext($classReflection->getName(), $classReflection);
        $classDocNode = $this->getPhpDocNode($classReflection);

        $templates = match (true) {
            $reflection instanceof \ReflectionFunctionAbstract => $this->collectTemplates($this->getPhpDocNode($reflection), $typeContext) + $this->collectTemplates($classDocNode, $typeContext),
            $reflection instanceof \ReflectionParameter => $this->collectTemplates($this->getPhpDocNode($reflection->getDeclaringFunction()), $typeContext) + $this->collectTemplates($classDocNode, $typeContext),
            default => $this->collectTemplates($classDocNode, $typeContext),
        };

        $typeContext = new TypeContext(
            $typeContext->calledClassName,
            $typeContext->declaringClassName,
            $typeContext->namespace,
            $typeContext->uses,
            $templates,
        );

        return new TypeContext(
            $typeContext->calledClassName,
            $typeContext->declaringClassName,
            $typeContext->namespace,
            $typeContext->uses,
            $typeContext->templates,
            $this->collectTypeAliases($classDocNode, $typeContext),
        );
    }

    private function createNewInstanceFromClassName(string $calledClassName, string $declaringClassName): TypeContext
    {
        $calledClassNameReflection = self::$reflectionClassCache[$calledClassName] ??= new \ReflectionClass($calledClassName);
        $declaringClassReflection = self::$reflectionClassCache[$declaringClassName] ??= new \ReflectionClass($declaringClassName);

        $calledClassTypeContext = $this->createBaseTypeContext($calledClassNameReflection->getName(), $calledClassNameReflection);
        $typeContext = $this->createBaseTypeContext($calledClassNameReflection->getName(), $declaringClassReflection);
        $declaringClassDocNode = $this->getPhpDocNode($declaringClassReflection);
        $calledClassDocNode = $calledClassName === $declaringClassName ? $declaringClassDocNode : $this->getPhpDocNode($calledClassNameReflection);

        $templates = $this->collectTemplates($calledClassDocNode, $calledClassTypeContext);
        $inheritedTemplateTypes = [];

        if ($calledClassNameReflection->name !== $declaringClassReflection->name && $declaringClassTemplates = $this->collectTemplates($declaringClassDocNode, $typeContext)) {
            $inheritedTemplateTypes = $this->collectInheritedTemplateTypes($calledClassNameReflection, $declaringClassReflection, $templates, array_keys($declaringClassTemplates));
            $templates = $declaringClassTemplates + $templates;
        }

        $typeContext = new TypeContext(
            $typeContext->calledClassName,
            $typeContext->declaringClassName,
            $typeContext->namespace,
            $typeContext->uses,
            $templates,
            [],
            $inheritedTemplateTypes,
        );

        return new TypeContext(
            $typeContext->calledClassName,
            $typeContext->declaringClassName,
            $typeContext->namespace,
            $typeContext->uses,
            $typeContext->templates,
            $this->collectTypeAliases($declaringClassDocNode, $typeContext),
            $typeContext->inheritedTemplateTypes,
        );
    }

    private function createBaseTypeContext(string $calledClassName, \ReflectionClass $declaringClassReflection): TypeContext
    {
        $declaringClassName = $declaringClassReflection->getName();

        return $this->baseTypeContextCache[$declaringClassName][$calledClassName] ??= new TypeContext(
            $calledClassName,
            $declaringClassReflection->getName(),
            trim($declaringClassReflection->getNamespaceName(), '\\'),
            $this->collectUses($declaringClassReflection),
        );
    }

    /**
     * Finds the trait where a property, a method or a parameter is written, since reflection gives the class using the trait as their declaring class.
     */
    private function getDeclaringTrait(\Reflector $reflection, \ReflectionClass $classReflection): ?\ReflectionClass
    {
        if ($reflection instanceof \ReflectionParameter) {
            $reflection = $reflection->getDeclaringFunction();
        }

        if (!$reflection instanceof \ReflectionProperty && !$reflection instanceof \ReflectionFunctionAbstract) {
            return null;
        }

        foreach ($classReflection->getTraits() as $traitReflection) {
            if ($declaringTraitReflection = $this->getDeclaringTrait($reflection, $traitReflection)) {
                return $declaringTraitReflection;
            }

            if ($reflection instanceof \ReflectionProperty) {
                // a class can declare a property of its traits again, with another doc comment
                if ($traitReflection->hasProperty($reflection->name) && $traitReflection->getProperty($reflection->name)->getDocComment() === $reflection->getDocComment()) {
                    return $traitReflection;
                }
            } elseif ($traitReflection->getFileName() === $reflection->getFileName() && $traitReflection->getStartLine() <= $reflection->getStartLine() && $reflection->getEndLine() <= $traitReflection->getEndLine()) {
                return $traitReflection;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function collectUses(\ReflectionClass $reflection): array
    {
        if (isset($this->usesCache[$reflection->getName()])) {
            return $this->usesCache[$reflection->getName()];
        }

        $fileName = $reflection->getFileName();
        if (!\is_string($fileName) || !is_file($fileName)) {
            return [];
        }

        if (false === $lines = @file($fileName, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES)) {
            throw new RuntimeException(\sprintf('Unable to read file "%s".', $fileName));
        }

        $uses = [];
        $inUseSection = false;
        $inGroupedUse = false;
        $groupPrefix = '';

        foreach ($lines as $line) {
            $trimmed = trim($line, " \t");

            if ($inGroupedUse) {
                $this->parseGroupedUseMembers($trimmed, $groupPrefix, $uses);

                if (str_contains($trimmed, '}')) {
                    $inGroupedUse = false;
                }

                continue;
            }

            if (str_starts_with($line, 'use ')) {
                $inUseSection = true;
                $body = substr($trimmed, 4);

                if (str_contains($body, '{')) {
                    $groupPrefix = substr($body, 0, strpos($body, '{'));
                    $inGroupedUse = !str_contains($body, '}');
                    $segment = trim(substr($body, strpos($body, '{')), " \t\r\n{};");
                    $this->parseGroupedUseMembers($segment, $groupPrefix, $uses);
                } else {
                    $use = preg_split('/\s+as\s+/', rtrim($body, ';'), 2);
                    $fqcn = ltrim($use[0], '\\');
                    $alias = $use[1] ?? (false !== ($p = strrpos($fqcn, '\\')) ? substr($fqcn, 1 + $p) : $fqcn);
                    $uses[$alias] = $fqcn;
                }
            } elseif ($inUseSection) {
                break;
            }
        }

        $traitUses = [];
        foreach ($reflection->getTraits() as $traitReflection) {
            $traitUses[] = $this->collectUses($traitReflection);
        }

        return $this->usesCache[$reflection->getName()] = $uses + array_merge(...$traitUses);
    }

    /**
     * @param array<string, string> $uses
     */
    private function parseGroupedUseMembers(string $segment, string $prefix, array &$uses): void
    {
        foreach (explode(',', $segment) as $member) {
            if ('' === $member = trim($member, " \t\r\n};")) {
                continue;
            }

            $parts = preg_split('/\s+as\s+/', $member, 2);
            $fqcn = ltrim($prefix.$parts[0], '\\');
            $alias = $parts[1] ?? (false !== ($p = strrpos($fqcn, '\\')) ? substr($fqcn, 1 + $p) : $fqcn);
            $uses[$alias] = $fqcn;
        }
    }

    /**
     * @return array<string, Type>
     */
    private function collectTemplates(?PhpDocNode $docNode, TypeContext $typeContext): array
    {
        if (!$docNode) {
            return [];
        }

        $templateTags = [
            '@template',
            '@template-covariant',
            '@template-contravariant',
            '@psalm-template',
            '@psalm-template-covariant',
            '@psalm-template-contravariant',
            '@phpstan-template',
            '@phpstan-template-covariant',
            '@phpstan-template-contravariant',
        ];

        $templates = [];
        foreach ($docNode->getTags() as $tag) {
            if (!$tag->value instanceof TemplateTagValueNode || !\in_array($tag->name, $templateTags, true)) {
                continue;
            }

            $type = Type::mixed();
            $typeString = ((string) $tag->value->bound) ?: null;

            try {
                if (null !== $typeString) {
                    $type = $this->stringTypeResolver->resolve($typeString, $typeContext);
                }
            } catch (UnsupportedException) {
            }

            $templates[$tag->value->name] = $type;
        }

        return $templates;
    }

    /**
     * Resolves the types that the called class gives to the templates of the declaring class.
     *
     * The types come from the "@extends", "@implements" and "@use" tags of the classes in between. A parent that gets no types from such a tag gets the types of the templates that have the same name. The result is empty when no tag is involved. In that case, the templates of the called class already have precedence.
     *
     * @param array<string, Type> $calledClassTemplates
     * @param list<string>        $declaringClassTemplates
     *
     * @return array<string, Type>
     */
    private function collectInheritedTemplateTypes(\ReflectionClass $calledClassReflection, \ReflectionClass $declaringClassReflection, array $calledClassTemplates, array $declaringClassTemplates): array
    {
        $templateTypes = [$calledClassReflection->name => []];
        foreach ($calledClassTemplates as $template => $bound) {
            $templateTypes[$calledClassReflection->name][$template] = Type::template($template, $bound);
        }

        $isTagged = [$calledClassReflection->name => false];
        $classNames = [$calledClassReflection->name];

        while (null !== $className = array_shift($classNames)) {
            $classTemplateTypes = $templateTypes[$className];

            foreach ($this->getParents($className, $declaringClassReflection) as $parentClassName => $variableTypes) {
                if (isset($templateTypes[$parentClassName])) {
                    continue;
                }

                $templateTypes[$parentClassName] = [];
                $isTagged[$parentClassName] = $isTagged[$className] || null !== $variableTypes;

                $parentTemplates = $parentClassName === $declaringClassReflection->name ? $declaringClassTemplates : array_keys($this->createFromClassName($parentClassName)->templates);

                foreach ($parentTemplates as $i => $template) {
                    if (null === $variableTypes) {
                        if (isset($classTemplateTypes[$template])) {
                            $templateTypes[$parentClassName][$template] = $classTemplateTypes[$template];
                        }
                    } elseif (isset($variableTypes[$i])) {
                        $templateTypes[$parentClassName][$template] = $variableTypes[$i]->map(static fn (Type $t): Type => $t instanceof TemplateType ? $classTemplateTypes[$t->getName()] ?? $t : $t);
                    }
                }

                if ($parentClassName === $declaringClassReflection->name) {
                    return $isTagged[$parentClassName] ? $templateTypes[$parentClassName] : [];
                }

                $classNames[] = $parentClassName;
            }
        }

        return [];
    }

    /**
     * Returns the parent class, interfaces and traits of a class that can lead to the declaring class.
     *
     * Each parent comes with the variable types from the matching tag of the class, or with null when there is no such tag.
     *
     * @return array<class-string, list<Type>|null>
     */
    private function getParents(string $className, \ReflectionClass $declaringClassReflection): array
    {
        $reflection = self::$reflectionClassCache[$className] ??= new \ReflectionClass($className);
        $parents = $this->parentVariableTypesCache[$className] ??= $this->collectParentVariableTypes($reflection, $this->getPhpDocNode($reflection));
        $parentClassName = get_parent_class($className);

        if ($declaringClassReflection->isTrait()) {
            $parents = ($this->traitVariableTypesCache[$className] ??= $this->collectTraitVariableTypes($reflection)) + $parents;
            $untaggedParents = $reflection->getTraitNames();
        } elseif ($declaringClassReflection->isInterface()) {
            $untaggedParents = class_implements($className);

            // the walk reaches the interfaces of the parent class and of other interfaces from there
            if (false !== $parentClassName) {
                $untaggedParents = array_diff_key($untaggedParents, class_implements($parentClassName));
            }
            foreach ($untaggedParents as $interface) {
                $untaggedParents = array_diff_key($untaggedParents, class_implements($interface));
            }
        } else {
            $untaggedParents = [];
        }

        if (false !== $parentClassName) {
            $parents += [$parentClassName => null];
        }
        foreach ($untaggedParents as $untaggedParent) {
            $parents += [$untaggedParent => null];
        }

        foreach (array_keys($parents) as $parent) {
            if ($declaringClassReflection->isTrait() ? interface_exists($parent) : !is_a($parent, $declaringClassReflection->name, true)) {
                unset($parents[$parent]);
            }
        }

        return $parents;
    }

    /**
     * Returns the variable types that a class gives to its parent class, interfaces and traits in "@extends", "@implements" and "@use" tags.
     *
     * Like PHPStan does, "@phpstan-" tags have precedence over "@template-" tags, which have precedence over unprefixed tags.
     *
     * @return array<class-string, list<Type>>
     */
    private function collectParentVariableTypes(\ReflectionClass $reflection, ?PhpDocNode $docNode): array
    {
        if (!$docNode) {
            return [];
        }

        $typeContext = null;
        $parentVariableTypes = [];
        $priorities = [];

        foreach ($docNode->getTags() as $tag) {
            if (!$tag->value instanceof ExtendsTagValueNode && !$tag->value instanceof ImplementsTagValueNode && !$tag->value instanceof UsesTagValueNode) {
                continue;
            }

            try {
                $type = $this->stringTypeResolver->resolve((string) $tag->value->type, $typeContext ??= $this->createFromClassName($reflection->name));
            } catch (ExceptionInterface) {
                continue;
            }

            if ($type instanceof CollectionType) {
                $type = $type->getWrappedType();
            }

            if (!$type instanceof GenericType || !($parentType = $type->getWrappedType()) instanceof ObjectType) {
                continue;
            }

            $parentClassName = (self::$reflectionClassCache[$parentType->getClassName()] ??= new \ReflectionClass($parentType->getClassName()))->name;
            $priority = str_starts_with($tag->name, '@phpstan-') ? 2 : (str_starts_with($tag->name, '@template-') ? 1 : 0);

            if ($priority > ($priorities[$parentClassName] ?? -1) && (is_subclass_of($reflection->name, $parentClassName) || \in_array($parentClassName, $reflection->getTraitNames(), true))) {
                $parentVariableTypes[$parentClassName] = $type->getVariableTypes();
                $priorities[$parentClassName] = $priority;
            }
        }

        return $parentVariableTypes;
    }

    /**
     * Returns the variable types that a class gives to its traits in the "@use" tags of its "use" statements.
     *
     * @return array<class-string, list<Type>>
     */
    private function collectTraitVariableTypes(\ReflectionClass $reflection): array
    {
        if (!$reflection->getTraitNames() || !\is_string($fileName = $reflection->getFileName()) || false === $code = @file_get_contents($fileName)) {
            return [];
        }

        $traitVariableTypes = [];
        $docComment = null;
        $depth = 0;

        foreach (\PhpToken::tokenize($code) as $token) {
            if ($token->line < $reflection->getStartLine() || $token->is([\T_WHITESPACE, \T_COMMENT])) {
                continue;
            }

            if (1 === $depth && null !== $docComment && $token->is(\T_USE)) {
                $traitVariableTypes += $this->collectParentVariableTypes($reflection, $this->parsePhpDoc($docComment));
            }

            $docComment = 1 === $depth && $token->is(\T_DOC_COMMENT) ? $token->text : null;

            if ($token->is(['{', \T_CURLY_OPEN, \T_DOLLAR_OPEN_CURLY_BRACES])) {
                ++$depth;
            } elseif ($token->is('}') && 0 === --$depth) {
                break;
            }
        }

        return $traitVariableTypes;
    }

    /**
     * @return array<string, Type>
     */
    private function collectTypeAliases(?PhpDocNode $docNode, TypeContext $typeContext): array
    {
        if (!$this->stringTypeResolver || !class_exists(PhpDocParser::class)) {
            return [];
        }

        $extraAliases = array_map($this->stringTypeResolver->resolve(...), $this->extraTypeAliases);

        if (!$docNode) {
            return $extraAliases;
        }

        $aliases = [];
        $resolvedAliases = [];

        foreach ($docNode->getTagsByName('@psalm-import-type') + $docNode->getTagsByName('@phpstan-import-type') as $tag) {
            if (!$tag->value instanceof TypeAliasImportTagValueNode) {
                continue;
            }

            $importedFromType = $this->stringTypeResolver->resolve((string) $tag->value->importedFrom, $typeContext);
            if (!$importedFromType instanceof ObjectType) {
                throw new LogicException(\sprintf('Type alias "%s" is not imported from a valid class name.', $tag->value->importedAlias));
            }

            if (isset($this->collectingTypeAliases[$importedFromType->getClassName()])) {
                throw new LogicException(\sprintf('Cannot import "%s" type alias from "%s" as it is recursive.', $tag->value->importedAlias, $importedFromType->getClassName()));
            }

            $this->collectingTypeAliases[$typeContext->declaringClassName] = true;

            try {
                $importedFromContext = $this->createFromClassName($importedFromType->getClassName());
            } finally {
                unset($this->collectingTypeAliases[$typeContext->declaringClassName]);
            }

            $typeAlias = $importedFromContext->typeAliases[$tag->value->importedAlias] ?? null;
            if (!$typeAlias) {
                if (isset($this->unresolvableTypeAliases[$importedFromContext->declaringClassName][$tag->value->importedAlias])) {
                    $this->unresolvableTypeAliases[$typeContext->declaringClassName][$tag->value->importedAs ?? $tag->value->importedAlias] = true;

                    continue;
                }

                throw new LogicException(\sprintf('Cannot find any "%s" type alias in "%s".', $tag->value->importedAlias, $importedFromType->getClassName()));
            }

            $resolvedAliases[$tag->value->importedAs ?? $tag->value->importedAlias] = $typeAlias;
        }

        foreach ($docNode->getTagsByName('@psalm-type') + $docNode->getTagsByName('@phpstan-type') as $tag) {
            if (!$tag->value instanceof TypeAliasTagValueNode) {
                continue;
            }

            $aliases[$tag->value->alias] = (string) $tag->value->type;
        }

        return $this->resolveTypeAliases($aliases, $resolvedAliases, $typeContext) + $extraAliases;
    }

    /**
     * @param array<string, string> $toResolve
     * @param array<string, Type>   $resolved
     *
     * @return array<string, Type>
     */
    private function resolveTypeAliases(array $toResolve, array $resolved, TypeContext $typeContext): array
    {
        if (!$toResolve) {
            return $resolved;
        }

        $typeContext = new TypeContext(
            $typeContext->calledClassName,
            $typeContext->declaringClassName,
            $typeContext->namespace,
            $typeContext->uses,
            $typeContext->templates,
            $typeContext->typeAliases + $resolved,
            $typeContext->inheritedTemplateTypes,
        );

        $succeeded = false;

        foreach ($toResolve as $alias => $type) {
            try {
                $resolved[$alias] = $this->stringTypeResolver->resolve($type, $typeContext);
                unset($toResolve[$alias]);
                $succeeded = true;
            } catch (ExceptionInterface) {
            }
        }

        // nothing has succeeded, the result won't be different from the
        // previous one, so the remaining aliases are left out.
        if (!$succeeded) {
            foreach (array_keys($toResolve) as $alias) {
                $this->unresolvableTypeAliases[$typeContext->declaringClassName][$alias] = true;
            }

            return $resolved;
        }

        if ($toResolve) {
            return $this->resolveTypeAliases($toResolve, $resolved, $typeContext);
        }

        return $resolved;
    }

    private function getPhpDocNode(\ReflectionClass|\ReflectionFunctionAbstract $reflection): ?PhpDocNode
    {
        if ($reflection instanceof \ReflectionClass && \array_key_exists($reflection->name, $this->classDocNodeCache)) {
            return $this->classDocNodeCache[$reflection->name];
        }

        $docNode = null;
        if ($this->stringTypeResolver && class_exists(PhpDocParser::class) && $rawDocNode = $reflection->getDocComment()) {
            $docNode = $this->parsePhpDoc($rawDocNode);
        }

        if ($reflection instanceof \ReflectionClass) {
            $this->classDocNodeCache[$reflection->name] = $docNode;
        }

        return $docNode;
    }

    private function parsePhpDoc(string $rawDocNode): PhpDocNode
    {
        if (class_exists(ParserConfig::class)) {
            $this->phpstanLexer ??= new Lexer($config = new ParserConfig([]));
            $this->phpstanParser ??= new PhpDocParser($config, new TypeParser($config, new ConstExprParser($config)), new ConstExprParser($config));
        } else {
            $this->phpstanLexer ??= new Lexer();
            $this->phpstanParser ??= new PhpDocParser(new TypeParser($constExprParser = new ConstExprParser(true, true), true), $constExprParser);
        }

        return $this->phpstanParser->parse(new TokenIterator($this->phpstanLexer->tokenize($rawDocNode)));
    }
}
