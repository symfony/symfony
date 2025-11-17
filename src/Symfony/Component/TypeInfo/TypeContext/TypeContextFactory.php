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

use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TypeAliasImportTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TypeAliasTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use Symfony\Component\TypeInfo\Exception\LogicException;
use Symfony\Component\TypeInfo\Exception\RuntimeException;
use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ObjectType;
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
     * @var array<string,array<string,TypeContext>>
     */
    private array $intermediateTypeContextCache = [];

    /**
     * @var array<string,array<string,TypeContext>>
     */
    private array $typeContextCache = [];

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
        $declaringClassReflection = match (true) {
            $reflection instanceof \ReflectionClass => $reflection,
            $reflection instanceof \ReflectionMethod => $reflection->getDeclaringClass(),
            $reflection instanceof \ReflectionProperty => $reflection->getDeclaringClass(),
            $reflection instanceof \ReflectionParameter => $reflection->getDeclaringClass(),
            $reflection instanceof \ReflectionFunctionAbstract => $reflection->getClosureScopeClass(),
            default => null,
        };

        if (null === $declaringClassReflection) {
            return null;
        }

        $typeContext = $this->createIntermediateTypeContext($declaringClassReflection->getShortName(), $declaringClassReflection);

        $templates = match (true) {
            $reflection instanceof \ReflectionFunctionAbstract => $this->collectTemplates($reflection, $typeContext) + $this->collectTemplates($declaringClassReflection, $typeContext),
            $reflection instanceof \ReflectionParameter => $this->collectTemplates($reflection->getDeclaringFunction(), $typeContext) + $this->collectTemplates($declaringClassReflection, $typeContext),
            default => $this->collectTemplates($declaringClassReflection, $typeContext),
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
            $this->collectTypeAliases($declaringClassReflection, $typeContext),
        );
    }

    private function createNewInstanceFromClassName(string $calledClassName, string $declaringClassName): TypeContext
    {
        $calledClassNameReflection = self::$reflectionClassCache[$calledClassName] ??= new \ReflectionClass($calledClassName);
        $declaringClassReflection = self::$reflectionClassCache[$declaringClassName] ??= new \ReflectionClass($declaringClassName);

        $calledClassTypeContext = $this->createIntermediateTypeContext($calledClassNameReflection->getShortName(), $calledClassNameReflection);
        $typeContext = $this->createIntermediateTypeContext($calledClassNameReflection->getShortName(), $declaringClassReflection);

        $typeContext = new TypeContext(
            $typeContext->calledClassName,
            $typeContext->declaringClassName,
            $typeContext->namespace,
            $typeContext->uses,
            $this->collectTemplates($calledClassNameReflection, $calledClassTypeContext) + $this->collectTemplates($declaringClassReflection, $typeContext),
        );

        return new TypeContext(
            $typeContext->calledClassName,
            $typeContext->declaringClassName,
            $typeContext->namespace,
            $typeContext->uses,
            $typeContext->templates,
            $this->collectTypeAliases($declaringClassReflection, $typeContext),
        );
    }

    private function createIntermediateTypeContext(string $calledClassShortName, \ReflectionClass $declaringClassReflection): TypeContext
    {
        $declaringClassName = $declaringClassReflection->getName();

        return $this->intermediateTypeContextCache[$declaringClassName][$calledClassShortName] ??= new TypeContext(
            $calledClassShortName,
            $declaringClassReflection->getShortName(),
            trim($declaringClassReflection->getNamespaceName(), '\\'),
            $this->collectUses($declaringClassReflection),
        );
    }

    /**
     * @return array<string, string>
     */
    private function collectUses(\ReflectionClass $reflection): array
    {
        $fileName = $reflection->getFileName();
        if (!\is_string($fileName) || !is_file($fileName)) {
            return [];
        }

        if (false === $lines = @file($fileName, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES)) {
            throw new RuntimeException(\sprintf('Unable to read file "%s".', $fileName));
        }

        $uses = [];
        $inUseSection = false;

        foreach ($lines as $line) {
            if (str_starts_with($line, 'use ')) {
                $inUseSection = true;
                $use = explode(' as ', substr($line, 4, -1), 2);

                $alias = 1 === \count($use) ? substr($use[0], false !== ($p = strrpos($use[0], '\\')) ? 1 + $p : 0) : $use[1];
                $uses[$alias] = $use[0];
            } elseif ($inUseSection) {
                break;
            }
        }

        $traitUses = [];
        foreach ($reflection->getTraits() as $traitReflection) {
            $traitUses[] = $this->collectUses($traitReflection);
        }

        return array_merge($uses, ...$traitUses);
    }

    /**
     * @return array<string, Type>
     */
    private function collectTemplates(\ReflectionClass|\ReflectionFunctionAbstract $reflection, TypeContext $typeContext): array
    {
        if (!$this->stringTypeResolver || !class_exists(PhpDocParser::class)) {
            return [];
        }

        if (!$rawDocNode = $reflection->getDocComment()) {
            return [];
        }

        $templates = [];
        foreach ($this->getPhpDocNode($rawDocNode)->getTagsByName('@template') as $tag) {
            if (!$tag->value instanceof TemplateTagValueNode) {
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
     * @return array<string, Type>
     */
    private function collectTypeAliases(\ReflectionClass $reflection, TypeContext $typeContext): array
    {
        if (!$this->stringTypeResolver || !class_exists(PhpDocParser::class)) {
            return [];
        }

        $extraAliases = array_map($this->stringTypeResolver->resolve(...), $this->extraTypeAliases);

        if (!$rawDocNode = $reflection->getDocComment()) {
            return $extraAliases;
        }

        $aliases = [];
        $resolvedAliases = [];

        foreach ($this->getPhpDocNode($rawDocNode)->getTagsByName('@psalm-import-type') + $this->getPhpDocNode($rawDocNode)->getTagsByName('@phpstan-import-type') as $tag) {
            if (!$tag->value instanceof TypeAliasImportTagValueNode) {
                continue;
            }

            $importedFromType = $this->stringTypeResolver->resolve((string) $tag->value->importedFrom, $typeContext);
            if (!$importedFromType instanceof ObjectType) {
                throw new LogicException(\sprintf('Type alias "%s" is not imported from a valid class name.', $tag->value->importedAlias));
            }

            $importedFromContext = $this->createFromClassName($importedFromType->getClassName());

            $typeAlias = $importedFromContext->typeAliases[$tag->value->importedAlias] ?? null;
            if (!$typeAlias) {
                throw new LogicException(\sprintf('Cannot find any "%s" type alias in "%s".', $tag->value->importedAlias, $importedFromType->getClassName()));
            }

            $resolvedAliases[$tag->value->importedAs ?? $tag->value->importedAlias] = $typeAlias;
        }

        foreach ($this->getPhpDocNode($rawDocNode)->getTagsByName('@psalm-type') + $this->getPhpDocNode($rawDocNode)->getTagsByName('@phpstan-type') as $tag) {
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
        );

        $succeeded = false;
        $lastFailure = null;
        $lastFailingAlias = null;

        foreach ($toResolve as $alias => $type) {
            try {
                $resolved[$alias] = $this->stringTypeResolver->resolve($type, $typeContext);
                unset($toResolve[$alias]);
                $succeeded = true;
            } catch (UnsupportedException $lastFailure) {
                $lastFailingAlias = $alias;
            }
        }

        // nothing has succeeded, the result won't be different from the
        // previous one, we can stop here.
        if (!$succeeded) {
            throw new LogicException(\sprintf('Cannot resolve "%s" type alias.', $lastFailingAlias), 0, $lastFailure);
        }

        if ($toResolve) {
            return $this->resolveTypeAliases($toResolve, $resolved, $typeContext);
        }

        return $resolved;
    }

    private function getPhpDocNode(string $rawDocNode): PhpDocNode
    {
        if (class_exists(ParserConfig::class)) {
            $this->phpstanLexer ??= new Lexer($config = new ParserConfig([]));
            $this->phpstanParser ??= new PhpDocParser($config, new TypeParser($config, new ConstExprParser($config)), new ConstExprParser($config));
        } else {
            $this->phpstanLexer ??= new Lexer();
            $this->phpstanParser ??= new PhpDocParser(new TypeParser(new ConstExprParser()), new ConstExprParser());
        }

        return $this->phpstanParser->parse(new TokenIterator($this->phpstanLexer->tokenize($rawDocNode)));
    }
}
