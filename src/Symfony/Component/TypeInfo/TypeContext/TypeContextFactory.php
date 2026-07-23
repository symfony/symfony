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

        $typeContext = $this->createBaseTypeContext($classReflection->getName(), $classReflection);

        $templates = match (true) {
            $reflection instanceof \ReflectionFunctionAbstract => $this->collectTemplates($reflection, $typeContext) + $this->collectTemplates($classReflection, $typeContext),
            $reflection instanceof \ReflectionParameter => $this->collectTemplates($reflection->getDeclaringFunction(), $typeContext) + $this->collectTemplates($classReflection, $typeContext),
            default => $this->collectTemplates($classReflection, $typeContext),
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
            $this->collectTypeAliases($classReflection, $typeContext),
        );
    }

    private function createNewInstanceFromClassName(string $calledClassName, string $declaringClassName): TypeContext
    {
        $calledClassNameReflection = self::$reflectionClassCache[$calledClassName] ??= new \ReflectionClass($calledClassName);
        $declaringClassReflection = self::$reflectionClassCache[$declaringClassName] ??= new \ReflectionClass($declaringClassName);

        $calledClassTypeContext = $this->createBaseTypeContext($calledClassNameReflection->getName(), $calledClassNameReflection);
        $typeContext = $this->createBaseTypeContext($calledClassNameReflection->getName(), $declaringClassReflection);

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

        return $this->usesCache[$reflection->getName()] = array_merge($uses, ...$traitUses);
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
    private function collectTemplates(\ReflectionClass|\ReflectionFunctionAbstract $reflection, TypeContext $typeContext): array
    {
        if (!$this->stringTypeResolver || !class_exists(PhpDocParser::class)) {
            return [];
        }

        if (!$rawDocNode = $reflection->getDocComment()) {
            return [];
        }

        $docNode = $this->getPhpDocNode($rawDocNode);

        $templateTags = [
            '@template',
            '@template-covariant',
            '@psalm-template',
            '@psalm-template-covariant',
            '@phpstan-template',
            '@phpstan-template-covariant',
        ];

        $tags = [];
        foreach ($templateTags as $tagName) {
            $tags = [...$tags, ...$docNode->getTagsByName($tagName)];
        }

        $templates = [];
        foreach ($tags as $tag) {
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
