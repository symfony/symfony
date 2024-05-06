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

use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use Symfony\Component\TypeInfo\Exception\RuntimeException;
use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;

/**
 * Creates a type resolving context.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 *
 * @experimental
 */
final class TypeContextFactory
{
    /**
     * @var array<class-string, \ReflectionClass>
     */
    private static array $reflectionClassCache = [];

    private ?Lexer $phpstanLexer = null;
    private ?PhpDocParser $phpstanParser = null;

    public function __construct(
        private readonly ?StringTypeResolver $stringTypeResolver = null,
    ) {
    }

    public function createFromClassName(string $calledClassName, ?string $declaringClassName = null): TypeContext
    {
        $declaringClassName ??= $calledClassName;

        $calledClassPath = explode('\\', $calledClassName);
        $declaringClassPath = explode('\\', $declaringClassName);

        $declaringClassReflection = self::$reflectionClassCache[$declaringClassName] ??= new \ReflectionClass($declaringClassName);

        $typeContext = new TypeContext(
            end($calledClassPath),
            end($declaringClassPath),
            trim($declaringClassReflection->getNamespaceName(), '\\'),
            $this->collectUses($declaringClassReflection),
        );

        return new TypeContext(
            $typeContext->calledClassName,
            $typeContext->declaringClassName,
            $typeContext->namespace,
            $typeContext->uses,
            $this->collectTemplates($declaringClassReflection, $typeContext),
        );
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

        $typeContext = new TypeContext(
            $declaringClassReflection->getShortName(),
            $declaringClassReflection->getShortName(),
            $declaringClassReflection->getNamespaceName(),
            $this->collectUses($declaringClassReflection),
        );

        $templates = match (true) {
            $reflection instanceof \ReflectionFunctionAbstract => $this->collectTemplates($reflection, $typeContext) + $this->collectTemplates($declaringClassReflection, $typeContext),
            $reflection instanceof \ReflectionParameter => $this->collectTemplates($reflection->getDeclaringFunction(), $typeContext) + $this->collectTemplates($declaringClassReflection, $typeContext),
            default => $this->collectTemplates($declaringClassReflection, $typeContext),
        };

        return new TypeContext(
            $typeContext->calledClassName,
            $typeContext->declaringClassName,
            $typeContext->namespace,
            $typeContext->uses,
            $templates,
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

        if (false === $lines = @file($fileName)) {
            throw new RuntimeException(sprintf('Unable to read file "%s".', $fileName));
        }

        $uses = [];
        $inUseSection = false;

        foreach ($lines as $line) {
            if (str_starts_with($line, 'use ')) {
                $inUseSection = true;
                $use = explode(' as ', substr($line, 4, -2), 2);

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

        $this->phpstanLexer ??= new Lexer();
        $this->phpstanParser ??= new PhpDocParser(new TypeParser(new ConstExprParser()), new ConstExprParser());

        $tokens = new TokenIterator($this->phpstanLexer->tokenize($rawDocNode));

        $templates = [];
        foreach ($this->phpstanParser->parse($tokens)->getTagsByName('@template') as $tag) {
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
}
