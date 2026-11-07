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

use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use Symfony\Component\TypeInfo\Exception\ExceptionInterface;
use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContext;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;

/**
 * Resolves type on reflection prioriziting PHP documentation.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class PhpDocAwareReflectionTypeResolver implements TypeResolverInterface
{
    private readonly PhpDocParser $phpDocParser;
    private readonly Lexer $lexer;

    public function __construct(
        private readonly TypeResolverInterface $reflectionTypeResolver,
        private readonly TypeResolverInterface $stringTypeResolver,
        private readonly TypeContextFactory $typeContextFactory,
        ?PhpDocParser $phpDocParser = null,
        ?Lexer $lexer = null,
    ) {
        if (class_exists(ParserConfig::class)) {
            $this->lexer = $lexer ?? new Lexer(new ParserConfig([]));
            $this->phpDocParser = $phpDocParser ?? new PhpDocParser(
                $config = new ParserConfig([]),
                new TypeParser($config, $constExprParser = new ConstExprParser($config)),
                $constExprParser,
            );
        } else {
            $this->lexer = $lexer ?? new Lexer();
            $this->phpDocParser = $phpDocParser ?? new PhpDocParser(
                new TypeParser($constExprParser = new ConstExprParser(true, true), true),
                $constExprParser,
            );
        }
    }

    public function resolve(mixed $subject, ?TypeContext $typeContext = null): Type
    {
        if (!$subject instanceof \ReflectionProperty && !$subject instanceof \ReflectionParameter && !$subject instanceof \ReflectionFunctionAbstract) {
            throw new UnsupportedException(\sprintf('Expected subject to be a "ReflectionProperty", a "ReflectionParameter" or a "ReflectionFunctionAbstract", "%s" given.', get_debug_type($subject)), $subject);
        }

        $typeContext ??= $this->typeContextFactory->createFromReflection($subject);

        if (null !== $tagValue = $this->getTagValue($subject)) {
            return $this->stringTypeResolver->resolve($tagValue, $typeContext);
        }

        return $this->resolveInheritedType($subject, $typeContext) ?? $this->reflectionTypeResolver->resolve($subject);
    }

    private function getTagValue(\ReflectionProperty|\ReflectionParameter|\ReflectionFunctionAbstract $subject): ?string
    {
        $docComments = match (true) {
            $subject instanceof \ReflectionProperty => $subject->isPromoted()
                ? ['@var' => $subject->getDocComment(), '@param' => $subject->getDeclaringClass()?->getConstructor()?->getDocComment()]
                : ['@var' => $subject->getDocComment()],
            $subject instanceof \ReflectionParameter => ['@param' => $subject->getDeclaringFunction()->getDocComment()],
            $subject instanceof \ReflectionFunctionAbstract => ['@return' => $subject->getDocComment()],
        };

        foreach ($docComments as $tagName => $docComment) {
            if (!$docComment) {
                continue;
            }

            $tokens = new TokenIterator($this->lexer->tokenize($docComment));
            $docNode = $this->phpDocParser->parse($tokens);

            foreach ($docNode->getTagsByName($tagName) as $tag) {
                $tagValue = $tag->value;

                if ('@var' === $tagName && $tagValue instanceof VarTagValueNode) {
                    return (string) $tagValue;
                }

                if ('@param' === $tagName && $tagValue instanceof ParamTagValueNode && '$'.$subject->getName() === $tagValue->parameterName) {
                    return (string) $tagValue;
                }

                if ('@return' === $tagName && $tagValue instanceof ReturnTagValueNode) {
                    return (string) $tagValue;
                }
            }
        }

        return null;
    }

    /**
     * Resolves the PHPDoc type that a member without a tag of its own inherits from the members that it overrides or implements, like PHPStan does.
     *
     * The type is read where its docblock is written, and it applies only when the member has the same native type as the member it comes from, or when that member has no native type.
     */
    private function resolveInheritedType(\ReflectionProperty|\ReflectionParameter|\ReflectionFunctionAbstract $subject, ?TypeContext $typeContext): ?Type
    {
        if ($subject instanceof \ReflectionFunction) {
            return null;
        }

        foreach ($this->getOverriddenMembers($subject) as [$source, $docClass, $selfClass]) {
            if (null === $tagValue = $this->getTagValue($source)) {
                continue;
            }

            if (!$this->hasSameNativeType($subject, $source, $selfClass)) {
                return null;
            }

            $calledClass = $typeContext->calledClassName ?? $subject->getDeclaringClass()->name;

            try {
                $sourceTypeContext = $this->typeContextFactory->createFromReflection($source);
                $sourceTypeContext = new TypeContext($calledClass, $selfClass, $sourceTypeContext->namespace, $sourceTypeContext->uses, $sourceTypeContext->templates, $sourceTypeContext->typeAliases, $this->typeContextFactory->createFromClassName($calledClass, $docClass->name)->inheritedTemplateTypes);

                return $this->stringTypeResolver->resolve($tagValue, $sourceTypeContext);
            } catch (ExceptionInterface) {
                // unlike an own tag, an inherited tag that cannot be resolved falls back to the native type
                return null;
            }
        }

        return null;
    }

    /**
     * Yields the members that a method, a parameter or a property overrides or implements, nearest first.
     *
     * The parent class comes first, then the interfaces in the order of their declaration, then the abstract methods of the traits, each one followed by the members that it overrides itself. Parameters are matched by position, except the ones of constructors, which are matched by name, like promoted properties. Each member comes with the class where its docblock is written and the class that "self" refers to in it.
     *
     * @return iterable<array{\ReflectionMethod|\ReflectionParameter|\ReflectionProperty, \ReflectionClass, string}>
     */
    private function getOverriddenMembers(\ReflectionMethod|\ReflectionParameter|\ReflectionProperty $member, ?string $selfClass = null): iterable
    {
        if ($member instanceof \ReflectionProperty && $member->isPromoted() && !$member = $this->getConstructorParameter($member->getDeclaringClass()->getConstructor(), $member->name)) {
            return;
        }

        if ($member instanceof \ReflectionParameter) {
            if (!($method = $member->getDeclaringFunction()) instanceof \ReflectionMethod) {
                return;
            }

            foreach ($this->getOverriddenMembers($method) as [$source, $docClass, $sourceSelfClass]) {
                if ($parameter = '__construct' === $method->name ? $this->getConstructorParameter($source, $member->name) : $source->getParameters()[$member->getPosition()] ?? null) {
                    yield [$parameter, $docClass, $sourceSelfClass];
                }
            }

            return;
        }

        $class = $member->getDeclaringClass();
        $sources = [];

        if (($parent = $class->getParentClass()) && ($source = $this->getMember($parent, $member)) && !$source->isPrivate()) {
            $sources[] = [$source, $source->class];
        }

        // the interfaces of the parent class and of other interfaces are reached from there
        $interfaces = $class->getInterfaceNames();
        if ($parent) {
            $interfaces = array_diff($interfaces, $parent->getInterfaceNames());
        }
        foreach ($interfaces as $interface) {
            $interfaces = array_diff($interfaces, class_implements($interface));
        }
        foreach ($interfaces as $interface) {
            if ($source = $this->getMember(new \ReflectionClass($interface), $member)) {
                $sources[] = [$source, $source->class];
            }
        }

        if ($member instanceof \ReflectionMethod) {
            foreach ($class->getTraits() as $trait) {
                if (($source = $this->getMember($trait, $member)) && $source->isAbstract()) {
                    $sources[] = [$source, $selfClass ?? $class->name];
                }
            }
        }

        foreach ($sources as [$source, $sourceSelfClass]) {
            yield [$source, $this->getDeclaringTrait($source) ?? $source->getDeclaringClass(), $sourceSelfClass];
            yield from $this->getOverriddenMembers($source, $sourceSelfClass);
        }
    }

    private function getMember(\ReflectionClass $class, \ReflectionMethod|\ReflectionProperty $member): \ReflectionMethod|\ReflectionProperty|null
    {
        if ($member instanceof \ReflectionMethod) {
            return $class->hasMethod($member->name) ? $class->getMethod($member->name) : null;
        }

        return $class->hasProperty($member->name) ? $class->getProperty($member->name) : null;
    }

    private function getConstructorParameter(?\ReflectionMethod $constructor, string $name): ?\ReflectionParameter
    {
        return array_find($constructor?->getParameters() ?? [], static fn (\ReflectionParameter $parameter): bool => $name === $parameter->name);
    }

    /**
     * Finds the trait where a method or a property is written, since reflection gives the class using the trait as their declaring class.
     */
    private function getDeclaringTrait(\ReflectionMethod|\ReflectionProperty $member, ?\ReflectionClass $class = null): ?\ReflectionClass
    {
        foreach (($class ?? $member->getDeclaringClass())->getTraits() as $trait) {
            if ($declaringTrait = $this->getDeclaringTrait($member, $trait)) {
                return $declaringTrait;
            }

            if ($member instanceof \ReflectionProperty) {
                // a class can declare a property of its traits again, with another doc comment
                if ($trait->hasProperty($member->name) && $trait->getProperty($member->name)->getDocComment() === $member->getDocComment()) {
                    return $trait;
                }
            } elseif ($trait->getFileName() === $member->getFileName() && $trait->getStartLine() <= $member->getStartLine() && $member->getEndLine() <= $trait->getEndLine()) {
                return $trait;
            }
        }

        return null;
    }

    private function hasSameNativeType(\ReflectionMethod|\ReflectionParameter|\ReflectionProperty $member, \ReflectionMethod|\ReflectionParameter|\ReflectionProperty $source, string $sourceSelfClass): bool
    {
        if (!$sourceType = $source instanceof \ReflectionMethod ? $source->getReturnType() : $source->getType()) {
            return true;
        }

        if (!$type = $member instanceof \ReflectionMethod ? $member->getReturnType() : $member->getType()) {
            return false;
        }

        // PHP resolves "self" and "parent" in the members of classes since 8.5, but not in the members of traits
        $resolve = static fn (\ReflectionType $type, string $selfClass): string => preg_replace_callback('/(?<![\w\\\\])(?:self|parent)(?![\w\\\\])/i', static fn (array $m): string => 'self' === strtolower($m[0]) ? $selfClass : (get_parent_class($selfClass) ?: $m[0]), (string) $type);

        return $resolve($type, $member->getDeclaringClass()->name) === $resolve($sourceType, $sourceSelfClass);
    }
}
